<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Package;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Module\Package\PackageGate;
use Sandadmin\Module\Package\PackageOps;
use Sandadmin\Tests\Support\LifecycleFixture;

/**
 * 插件与系统升级网页入口的落库行为：目录、入队（RETURNING public_id::text
 * 与 CAST jsonb 是 PG 方言，只能在这里跑）、updateStatus 与 advance 推进。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL；
 * lifecycle_request 与插件行在 setUp 整清（测试库即用即弃）。
 */
final class PackageOpsTest extends TestCase
{
    private const PUBLIC_ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    private static Connection $conn;
    private PackageOps $ops;
    private OrgActor $super;
    /** @var array<string, string|false> */
    private array $envBackup = [];

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::wipe();
            self::$conn->close();
        }
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement('DELETE FROM sand_system_lifecycle_request');
        self::$conn->executeStatement("DELETE FROM sand_system_plugin WHERE code = 'flow'");
    }

    protected function setUp(): void
    {
        foreach (['APP_ENV', 'SAND_COMPILED', 'SAND_PACKAGE_OPS'] as $name) {
            $this->envBackup[$name] = getenv($name);
        }
        self::wipe();
        $this->ops = new PackageOps(self::$conn, sys_get_temp_dir() . '/sand-package-test-' . getmypid());
        $this->super = new OrgActor(7, '超管', null, true, ['*']);
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    private function opsOn(): void
    {
        putenv('APP_ENV');
        putenv('SAND_COMPILED=0');
        putenv('SAND_PACKAGE_OPS=1');
    }

    private function installPlugin(string $code = 'flow', string $version = '1.0.0', string $state = 'installed'): void
    {
        self::$conn->insert('sand_system_plugin', [
            'code' => $code,
            'name' => '流程审批',
            'version' => $version,
            'host_constraint' => '^1.0',
            'manifest_sha256' => hash('sha256', $code . '-' . $version),
            'state' => $state,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($item as $name => $field) {
                if (is_string($name)) {
                    $row[$name] = $field;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function marketRows(string $requestId): array
    {
        $data = $this->ops->market($requestId)['body']['data'] ?? null;
        self::assertIsArray($data);

        return self::rows($data['items'] ?? null);
    }

    /** @return list<array<string, mixed>> */
    private function installedRows(string $requestId): array
    {
        $data = $this->ops->installed($requestId)['body']['data'] ?? null;
        self::assertIsArray($data);

        return self::rows($data['items'] ?? null);
    }

    /** @return array<string, mixed> */
    private function requestRow(string $publicId): array
    {
        $row = self::$conn->fetchAssociative(
            'SELECT * FROM sand_system_lifecycle_request WHERE public_id = ?',
            [$publicId],
        );
        self::assertIsArray($row);

        return $row;
    }

    public function testMarketReturnsCatalogEntry(): void
    {
        $this->opsOn();
        $items = $this->marketRows('req-3');

        self::assertCount(1, $items);
        $flow = $items[0];
        self::assertSame('flow', $flow['code']);
        self::assertSame('流程审批', $flow['name']);
        self::assertSame('1.0.0', $flow['version']);
        self::assertSame('1.1.0', $flow['latest_version']);
        self::assertFalse($flow['installed']);
        self::assertFalse($flow['upgrade_available']);
        self::assertSame('uninstalled', $flow['state']);
        self::assertNull($flow['task_state']);
    }

    public function testMarketFlagsInstalledAndUpgradeAvailable(): void
    {
        $this->opsOn();
        $this->installPlugin('flow', '1.0.0');
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, ['target_code' => 'flow', 'state' => 'running']);
        $item = $this->marketRows('req-4')[0];

        self::assertTrue($item['installed']);
        self::assertTrue($item['upgrade_available'], '1.0.0 < 最新 1.1.0 时可升级');
        self::assertSame('installed', $item['state']);
        self::assertSame('running', $item['task_state'], '进行中的申请状态带出来');
    }

    public function testInstalledFiltersToInstalledOnly(): void
    {
        $this->opsOn();
        self::assertSame([], $this->installedRows('req-5'));
        $this->installPlugin('flow');
        self::assertCount(1, $this->installedRows('req-5'));
    }

    public function testShowPluginFoundAndMissing(): void
    {
        $this->opsOn();
        $found = $this->ops->showPlugin('flow', 'req-6');
        self::assertSame(200, $found['status']);
        $data = $found['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame('flow', $data['code']);

        $missing = $this->ops->showPlugin('ghost', 'req-6');
        self::assertSame(404, $missing['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_NOT_FOUND, $missing['body']['code']);
    }

    public function testInstallEnqueuesRequest(): void
    {
        $this->opsOn();
        $accepted = $this->ops->install('flow', $this->super, 'req-7');

        self::assertSame(200, $accepted['status']);
        $data = $accepted['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame('queued', $data['state']);
        $publicId = $data['request_id'] ?? null;
        self::assertIsString($publicId);
        $row = $this->requestRow($publicId);
        self::assertSame('plugin_install', $row['kind']);
        self::assertSame('flow', $row['target_code']);
        self::assertSame('queued', $row['state']);
        self::assertSame('package-ops', $row['executor_id']);
    }

    public function testInstallBusyWhenAnotherRequestRunning(): void
    {
        $this->opsOn();
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, ['state' => 'running']);
        $denied = $this->ops->install('flow', $this->super, 'req-8');

        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_BUSY, $denied['body']['code']);
    }

    public function testInstallUnknownAndAlreadyInstalled(): void
    {
        $this->opsOn();
        $denied = $this->ops->install('ghost', $this->super, 'req-9');
        self::assertSame(404, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_NOT_FOUND, $denied['body']['code']);

        $this->installPlugin('flow');
        $denied = $this->ops->install('flow', $this->super, 'req-9');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_ALREADY_INSTALLED, $denied['body']['code']);
    }

    public function testUpgradeGuardBranches(): void
    {
        $this->opsOn();
        $denied = $this->ops->upgrade('ghost', $this->super, 'req-10');
        self::assertSame(404, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_NOT_FOUND, $denied['body']['code']);

        $denied = $this->ops->upgrade('flow', $this->super, 'req-10');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_NOT_INSTALLED, $denied['body']['code']);

        $this->installPlugin('flow', '1.1.0');
        $denied = $this->ops->upgrade('flow', $this->super, 'req-10');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_VERSION_INCOMPATIBLE, $denied['body']['code']);
    }

    public function testUpgradeEnqueuesRequest(): void
    {
        $this->opsOn();
        $this->installPlugin('flow', '1.0.0');
        $accepted = $this->ops->upgrade('flow', $this->super, 'req-11');

        self::assertSame(200, $accepted['status']);
        $data = $accepted['body']['data'] ?? null;
        self::assertIsArray($data);
        $row = $this->requestRow(OrgSql::str($data['request_id'] ?? null));
        self::assertSame('plugin_upgrade', $row['kind']);
        self::assertSame('1.0.0', $row['from_version']);
        self::assertSame('1.1.0', $row['to_version']);
    }

    public function testUninstallRequiresInstalled(): void
    {
        $this->opsOn();
        $denied = $this->ops->uninstall('ghost', true, $this->super, 'req-12');
        self::assertSame(404, $denied['status']);

        $this->installPlugin('flow', '1.0.0', 'uninstalled');
        $denied = $this->ops->uninstall('flow', true, $this->super, 'req-12');
        self::assertSame(404, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_NOT_FOUND, $denied['body']['code']);
    }

    public function testUninstallEnqueuesWithPurgeFlag(): void
    {
        $this->opsOn();
        $this->installPlugin('flow', '1.0.0');
        $accepted = $this->ops->uninstall('flow', true, $this->super, 'req-13');

        self::assertSame(200, $accepted['status']);
        $data = $accepted['body']['data'] ?? null;
        self::assertIsArray($data);
        $row = $this->requestRow(OrgSql::str($data['request_id'] ?? null));
        self::assertSame('plugin_uninstall', $row['kind']);
        self::assertTrue(in_array($row['purge_data'], [true, 't', 1, '1'], true), 'purge_data 落库为真');
    }

    public function testUpgradeAllNeedsUpgradable(): void
    {
        $this->opsOn();
        $denied = $this->ops->upgradeAll($this->super, 'req-14');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::UPDATE_NOT_AVAILABLE, $denied['body']['code']);

        $this->installPlugin('flow', '1.1.0');
        $denied = $this->ops->upgradeAll($this->super, 'req-14');
        self::assertSame(409, $denied['status'], '已是最新同样算无可升级');
    }

    public function testUpgradeAllEnqueuesBatchWithItems(): void
    {
        $this->opsOn();
        $this->installPlugin('flow', '1.0.0');
        $accepted = $this->ops->upgradeAll($this->super, 'req-15');

        self::assertSame(200, $accepted['status']);
        $data = $accepted['body']['data'] ?? null;
        self::assertIsArray($data);
        $row = $this->requestRow(OrgSql::str($data['request_id'] ?? null));
        self::assertSame('plugin_upgrade_batch', $row['kind']);
        self::assertSame('*', $row['target_code']);
        $items = json_decode(OrgSql::str($row['items'] ?? null), true);
        self::assertSame(
            [['code' => 'flow', 'from_version' => '1.0.0', 'to_version' => '1.1.0', 'state' => 'queued']],
            $items,
        );
    }

    public function testUploadRejectsCompiledArtifacts(): void
    {
        $this->opsOn();
        foreach (['pack.phar', 'ext.so', 'PHAR.PHAR'] as $name) {
            $denied = $this->ops->upload($name, '{}', $this->super, 'req-16');
            self::assertSame(403, $denied['status'], $name);
            self::assertSame(ErrorCodeRegistry::PLUGIN_OPERATION_FORBIDDEN, $denied['body']['code']);
            self::assertSame(PackageGate::COMPILED_PLUGIN_MESSAGE, $denied['body']['message']);
        }
        $denied = $this->ops->upload('pack.json', '{"compiled":true,"code":"flow"}', $this->super, 'req-16');
        self::assertSame(403, $denied['status'], 'compiled 标记的包同样拒收');
        $denied = $this->ops->upload('pack.json', "abc\0def", $this->super, 'req-16');
        self::assertSame(403, $denied['status'], '前 512 字节含 NUL 按编译产物拒');
    }

    public function testUploadValidatesJson(): void
    {
        $this->opsOn();
        $denied = $this->ops->upload('pack.json', 'not-a-json', $this->super, 'req-17');
        self::assertSame(422, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_PACKAGE_INVALID, $denied['body']['code']);

        $denied = $this->ops->upload('pack.json', '{"code":"ghost"}', $this->super, 'req-17');
        self::assertSame(422, $denied['status'], '不在目录里的包按无效包拒');

        $this->installPlugin('flow');
        $denied = $this->ops->upload('pack.json', '{"code":"flow"}', $this->super, 'req-17');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_ALREADY_INSTALLED, $denied['body']['code']);
    }

    public function testUpdateStatusWrapsBody(): void
    {
        $this->opsOn();
        $result = $this->ops->updateStatus('req-18');

        self::assertSame(200, $result['status']);
        $data = $result['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertArrayHasKey('current_version', $data);
        self::assertTrue($data['package_ops_enabled']);
        self::assertFalse($data['compiled']);
    }

    public function testAdvanceIgnoresMalformedAndForeign(): void
    {
        $this->opsOn();
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, ['state' => 'queued', 'progress' => 0]);

        $this->ops->advance('not-an-id');
        $this->ops->advance('00000000-0000-4000-8000-000000000000');
        self::assertSame(
            'queued',
            self::$conn->fetchOne('SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?', [self::PUBLIC_ID]),
            '非法或未命中的编号不推动申请',
        );

        $other = 'aaaaaaaa-0000-4000-8000-000000000000';
        LifecycleFixture::insert(self::$conn, $other, ['state' => 'queued', 'progress' => 0, 'executor_id' => 'other-executor']);
        $this->ops->advance($other);
        self::assertSame(
            'queued',
            self::$conn->fetchOne('SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?', [$other]),
            '只推进本模块写下的申请',
        );
    }

    public function testAdvancePluginProgressLadder(): void
    {
        $this->opsOn();
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, [
            'kind' => 'plugin_install',
            'state' => 'queued',
            'progress' => 0,
        ]);

        foreach ([['正在下载', 30], ['正在安装', 60], ['正在检查', 90]] as [$stage, $progress]) {
            $this->ops->advance(self::PUBLIC_ID);
            $row = $this->requestRow(self::PUBLIC_ID);
            self::assertSame('running', $row['state']);
            self::assertSame($stage, $row['stage']);
            self::assertSame($progress, OrgSql::int($row['progress'] ?? null));
        }
    }

    public function testAdvancePluginDoneMarksContractConflict(): void
    {
        $this->opsOn();
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, [
            'kind' => 'plugin_install',
            'state' => 'running',
            'progress' => 90,
        ]);

        // repoRoot 指到临时空目录：契约收集报缺 host openapi → 判冲突标 failed
        $this->ops->advance(self::PUBLIC_ID);

        $row = $this->requestRow(self::PUBLIC_ID);
        self::assertSame('failed', $row['state']);
        self::assertSame('契约冲突', $row['stage']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_CONTRACT_CONFLICT, $row['error_code']);
        self::assertTrue(in_array($row['rolled_back'], [true, 't', 1, '1'], true), 'rolled_back 落库为真');
    }

    public function testAdvanceSystemUpgradeStageLadder(): void
    {
        $this->opsOn();
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, [
            'kind' => 'system_upgrade',
            'target_code' => 'host',
            'state' => 'queued',
            'progress' => 0,
            'stage_key' => null,
        ]);

        foreach ([['check', 20], ['backup', 40], ['download', 60], ['switch', 80], ['verify', 100]] as [$key, $progress]) {
            $this->ops->advance(self::PUBLIC_ID);
            $row = $this->requestRow(self::PUBLIC_ID);
            self::assertSame('running', $row['state']);
            self::assertSame($key, $row['stage_key']);
            self::assertSame($progress, OrgSql::int($row['progress'] ?? null));
        }
        // verify 之后再推进才进 done 分支（apply 写 sand_system_setting），
        // 本用例只覆盖阶段阶梯，不走收尾。
    }
}
