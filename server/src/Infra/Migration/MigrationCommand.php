<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Sandadmin\Infra\Migration\Exception\MigrationBusyException;
use Sandadmin\Infra\Migration\Exception\MigrationConsistencyException;
use Sandadmin\Infra\Migration\Exception\MigrationException;
use Sandadmin\Infra\Migration\Exception\MigrationFailedException;

/**
 * 迁移执行器 CLI（bin/migrate.php）：迁移只走 CLI，不开 HTTP。
 * 连接参数只从 --dsn、SAND_TEST_DB_URL、DB_URL 或 DB_* 环境变量取，不设默认地址。
 * 退出码：0 成功；2 用法错误；3 锁被占用；4 半成品库；5 迁移执行失败；1 其他错误。
 */
final class MigrationCommand
{
    /**
     * @param list<string> $argv
     */
    public static function main(array $argv, string $serverRoot): int
    {
        $command = $argv[1] ?? '';
        $options = self::options(array_slice($argv, 2));
        if (!in_array($command, ['up', 'down', 'status'], true)) {
            self::usage();

            return 2;
        }

        try {
            $dsn = self::dsn($options);
            if ($dsn === null) {
                fwrite(STDERR, "缺少连接参数：传 --dsn 或设 SAND_TEST_DB_URL / DB_URL / DB_* 环境变量\n");

                return 2;
            }
            $dir = $options['migrations'] ?? $serverRoot . '/database/migrations';
            $catalog = MigrationCatalog::load(['host' => $dir]);
            $conn = DriverManager::getConnection((new DsnParser())->parse($dsn));
            $actor = getenv('SAND_MIGRATION_ACTOR');
            $actor = is_string($actor) && $actor !== '' ? $actor : (get_current_user() ?: 'cli');
            $recovery = isset($options['recovery-point'])
                ? new ManualRecoveryPoint($options['recovery-point'])
                : self::emptyRecovery();

            $runner = new MigrationRunner($conn, $catalog, $recovery, $actor);

            if ($command === 'status') {
                return self::status($runner);
            }
            if (!isset($options['recovery-point'])) {
                fwrite(STDERR, "up/down 必须带 --recovery-point=<位点>：拿不到可用恢复点拒绝迁移\n");

                return 2;
            }
            if ($command === 'up') {
                $to = $options['to'] ?? null;
                $report = $runner->upgrade($to);
                self::printReport('升级', $report);

                return 0;
            }
            $to = $options['to'] ?? null;
            if ($to === null) {
                fwrite(STDERR, "down 必须带 --to=<版本>\n");

                return 2;
            }
            $namespace = $options['namespace'] ?? 'host';
            $report = $runner->downgrade($namespace, $to);
            self::printReport('回退', $report);

            return 0;
        } catch (MigrationBusyException $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return 3;
        } catch (MigrationConsistencyException $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return 4;
        } catch (MigrationFailedException $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return 5;
        } catch (\Throwable $error) {
            fwrite(STDERR, '迁移执行失败：' . $error->getMessage() . "\n");

            return 1;
        }
    }

    /**
     * @param list<string> $args
     * @return array<string, string>
     */
    private static function options(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/', $arg, $match) !== 1) {
                continue;
            }
            $options[$match[1]] = $match[2] ?? '';
        }

        return $options;
    }

    /**
     * @param array<string, string> $options
     */
    private static function dsn(array $options): ?string
    {
        if (isset($options['dsn']) && $options['dsn'] !== '') {
            return $options['dsn'];
        }
        foreach (['SAND_TEST_DB_URL', 'DB_URL'] as $name) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $database = getenv('DB_NAME');
        $user = getenv('DB_USER');
        if (!is_string($host) || $host === ''
            || !is_string($port) || $port === ''
            || !is_string($database) || $database === ''
            || !is_string($user) || $user === '') {
            return null;
        }
        $password = getenv('DB_PASSWORD');
        $password = is_string($password) ? $password : '';

        return sprintf(
            'pgsql://%s:%s@%s:%d/%s',
            $user,
            rawurlencode($password),
            $host,
            (int) $port,
            $database,
        );
    }

    private static function status(MigrationRunner $runner): int
    {
        $status = $runner->status();
        if ($status['rows'] === []) {
            echo "账本为空\n";
        }
        foreach ($status['rows'] as $row) {
            echo implode(' ', [
                $row->namespace,
                $row->version,
                $row->direction,
                $row->status,
                $row->filename,
                "release={$row->releaseVersion}",
                "by={$row->appliedBy}",
                "{$row->executionMs}ms",
            ]) . "\n";
        }
        if ($status['pending'] !== []) {
            echo '待执行：' . implode(', ', array_map(
                static fn (MigrationFile $file): string => $file->namespace . '/' . $file->version,
                $status['pending'],
            )) . "\n";
        }

        return 0;
    }

    /**
     * @param array{recovery_point: string, executed: list<array{namespace: string, version: string, execution_ms: int}>} $report
     */
    private static function printReport(string $label, array $report): void
    {
        if ($report['executed'] === []) {
            echo "无待执行迁移\n";

            return;
        }
        echo "恢复点：{$report['recovery_point']}\n";
        foreach ($report['executed'] as $item) {
            echo "{$label} {$item['namespace']}/{$item['version']} 完成 {$item['execution_ms']}ms\n";
        }
    }

    private static function emptyRecovery(): RecoveryPointProvider
    {
        return new ManualRecoveryPoint('');
    }

    private static function usage(): void
    {
        fwrite(STDERR, <<<'TXT'
            用法：php bin/migrate.php <命令> [选项]
              status                          打印账本与待执行清单
              up [--to=0007]                  按命名空间+版本顺序执行未应用迁移
              down --to=0003 [--namespace=host]  倒序执行 .down.sql 回退到目标版本（含）
            选项：
              --dsn=<pgsql://u:p@host:port/db>  连接串；缺省读 SAND_TEST_DB_URL、DB_URL，再按 DB_* 拼
              --migrations=<dir>              迁移目录，默认 server/database/migrations
              --recovery-point=<ref>          up/down 必填：恢复点位点（快照号/PITR/测试标识），空值拒绝执行
            退出码：0 成功；2 用法；3 锁被占用；4 半成品库；5 迁移失败；1 其他
            TXT);
    }
}
