<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Module\Package\PackageGate;
use Sandadmin\Module\Package\PackageOps;
use Sandadmin\Tests\Support\Factory\UserFactory;
use Sandadmin\Tests\Support\LifecycleFixture;

/**
 * 插件与系统升级网页入口的内存库用例：门禁、目录、守卫分支与 advance 阶段推进。
 * enqueue 的 `RETURNING public_id::text` 与 updateStatus 的 `public_id::text`
 * 是 PG 方言，sqlite 解析不了——成功入队与 updateStatus 由 Integration 覆盖，
 * 本类只断言方言中立的守卫、校验和推进分支。
 */
final class PackageOpsTest extends TestCase
{
    private const PUBLIC_ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    private Connection $conn;
    private PackageOps $ops;
    private OrgActor $super;
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (['APP_ENV', 'SAND_COMPILED', 'SAND_PACKAGE_OPS'] as $name) {
            $this->envBackup[$name] = getenv($name);
        }
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_plugin (code varchar(64) primary key,
                name varchar(128) not null, version varchar(32) not null,
                host_constraint varchar(32) null, manifest_sha256 varchar(64) null,
                state varchar(16) not null default \'installed\',
                install_time text null, uninstall_time text null,
                create_time text null, update_time text null)',
        );
        LifecycleFixture::createTable($this->conn);
        $this->ops = new PackageOps($this->conn, sys_get_temp_dir() . '/sand-package-test-' . getmypid());
        $user = UserFactory::make(id: 7, nickname: '超管', isSuper: true);
        $this->super = new OrgActor($user->id, $user->nickname, $user->deptId, $user->isSuper, ['*']);
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
        $this->conn->close();
    }

    private function opsOn(): void
    {
        putenv('APP_ENV');
        putenv('SAND_COMPILED=0');
        putenv('SAND_PACKAGE_OPS=1');
    }

    private function opsOff(): void
    {
        putenv('APP_ENV');
        putenv('SAND_COMPILED');
        putenv('SAND_PACKAGE_OPS');
    }

    private function installPlugin(string $code = 'flow', string $version = '1.0.0', string $state = 'installed'): void
    {
        $this->conn->insert('sand_system_plugin', [
            'code' => $code,
            'name' => '流程审批',
            'version' => $version,
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

    public function testOverviewShape(): void
    {
        $this->opsOn();
        $result = $this->ops->overview('req-1');

        self::assertSame(200, $result['status']);
        self::assertSame([
            'compiled' => false,
            'package_ops_enabled' => true,
            'signature_required' => true,
        ], $result['body']['data']);
    }

    public function testMarketEmptyWhenOpsDisabled(): void
    {
        $this->opsOff();
        $result = $this->ops->market('req-2');

        self::assertSame(200, $result['status']);
        self::assertSame(['items' => []], $result['body']['data']);
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
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, ['target_code' => 'flow', 'state' => 'running']);
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

    public function testInstallGuardOrder(): void
    {
        $this->opsOn();
        // 未登录
        $guest = new OrgActor(0, '游客', null, false, []);
        $denied = $this->ops->install('flow', $guest, 'req-7');
        self::assertSame(401, $denied['status']);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $denied['body']['code']);
        // 非超管
        $plain = new OrgActor(3, '普通管理员', null, false, ['*']);
        $denied = $this->ops->install('flow', $plain, 'req-7');
        self::assertSame(403, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_OPERATION_FORBIDDEN, $denied['body']['code']);
        // 有别的申请在跑
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, ['state' => 'running']);
        $denied = $this->ops->install('flow', $this->super, 'req-7');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_BUSY, $denied['body']['code']);
    }

    public function testInstallDeniedWhenOpsDisabled(): void
    {
        $this->opsOff();
        $denied = $this->ops->install('flow', $this->super, 'req-8');

        self::assertSame(403, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_OPERATION_FORBIDDEN, $denied['body']['code']);
        self::assertSame(PackageGate::COMPILED_MESSAGE, $denied['body']['message']);
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

    public function testUninstallRequiresInstalled(): void
    {
        $this->opsOn();
        $denied = $this->ops->uninstall('ghost', true, $this->super, 'req-11');
        self::assertSame(404, $denied['status']);

        $this->installPlugin('flow', '1.0.0', 'uninstalled');
        $denied = $this->ops->uninstall('flow', true, $this->super, 'req-11');
        self::assertSame(404, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_NOT_FOUND, $denied['body']['code']);
    }

    public function testUpgradeAllNeedsUpgradable(): void
    {
        $this->opsOn();
        $denied = $this->ops->upgradeAll($this->super, 'req-12');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::UPDATE_NOT_AVAILABLE, $denied['body']['code']);

        $this->installPlugin('flow', '1.1.0');
        $denied = $this->ops->upgradeAll($this->super, 'req-12');
        self::assertSame(409, $denied['status'], '已是最新同样算无可升级');
    }

    public function testUploadRejectsCompiledArtifacts(): void
    {
        $this->opsOn();
        foreach (['pack.phar', 'ext.so', 'PHAR.PHAR'] as $name) {
            $denied = $this->ops->upload($name, '{}', $this->super, 'req-13');
            self::assertSame(403, $denied['status'], $name);
            self::assertSame(ErrorCodeRegistry::PLUGIN_OPERATION_FORBIDDEN, $denied['body']['code']);
            self::assertSame(PackageGate::COMPILED_PLUGIN_MESSAGE, $denied['body']['message']);
        }
        $denied = $this->ops->upload('pack.json', '{"compiled":true,"code":"flow"}', $this->super, 'req-13');
        self::assertSame(403, $denied['status'], 'compiled 标记的包同样拒收');
        $denied = $this->ops->upload('pack.json', "abc\0def", $this->super, 'req-13');
        self::assertSame(403, $denied['status'], '前 512 字节含 NUL 按编译产物拒');
    }

    public function testUploadValidatesJson(): void
    {
        $this->opsOn();
        $denied = $this->ops->upload('pack.json', 'not-a-json', $this->super, 'req-14');
        self::assertSame(422, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_PACKAGE_INVALID, $denied['body']['code']);

        $denied = $this->ops->upload('pack.json', '{"code":"ghost"}', $this->super, 'req-14');
        self::assertSame(422, $denied['status'], '不在目录里的包按无效包拒');

        $this->installPlugin('flow');
        $denied = $this->ops->upload('pack.json', '{"code":"flow"}', $this->super, 'req-14');
        self::assertSame(409, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_ALREADY_INSTALLED, $denied['body']['code']);
    }

    public function testAdvanceIgnoresMalformedAndForeign(): void
    {
        $this->opsOn();
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, ['state' => 'queued', 'progress' => 0]);

        $this->ops->advance('not-an-id');
        $this->ops->advance('00000000-0000-4000-8000-000000000000');
        self::assertSame(
            'queued',
            $this->conn->fetchOne('SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?', [self::PUBLIC_ID]),
            '非法或未命中的编号不推动申请',
        );

        $other = 'aaaaaaaa-0000-4000-8000-000000000000';
        LifecycleFixture::insert($this->conn, $other, ['state' => 'queued', 'progress' => 0, 'executor_id' => 'other-executor']);
        $this->ops->advance($other);
        self::assertSame(
            'queued',
            $this->conn->fetchOne('SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?', [$other]),
            '只推进本模块写下的申请',
        );
    }

    public function testAdvanceDisabledIsNoop(): void
    {
        $this->opsOff();
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, ['state' => 'queued', 'progress' => 0]);

        $this->ops->advance(self::PUBLIC_ID);

        self::assertSame(
            'queued',
            $this->conn->fetchOne('SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?', [self::PUBLIC_ID]),
        );
    }

    public function testAdvancePluginProgressLadder(): void
    {
        $this->opsOn();
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, [
            'kind' => 'plugin_install',
            'state' => 'queued',
            'progress' => 0,
        ]);

        foreach ([['正在下载', 30], ['正在安装', 60], ['正在检查', 90]] as [$stage, $progress]) {
            $this->ops->advance(self::PUBLIC_ID);
            $row = $this->conn->fetchAssociative(
                'SELECT state, stage, progress FROM sand_system_lifecycle_request WHERE public_id = ?',
                [self::PUBLIC_ID],
            );
            self::assertIsArray($row);
            self::assertSame('running', $row['state']);
            self::assertSame($stage, $row['stage']);
            self::assertSame($progress, $row['progress']);
        }
    }

    public function testAdvancePluginDoneMarksContractConflict(): void
    {
        $this->opsOn();
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, [
            'kind' => 'plugin_install',
            'state' => 'running',
            'progress' => 90,
        ]);

        // repoRoot 指到临时空目录：契约收集报缺 host openapi → 判冲突标 failed
        $this->ops->advance(self::PUBLIC_ID);

        $row = $this->conn->fetchAssociative(
            'SELECT state, stage, error_code, rolled_back FROM sand_system_lifecycle_request WHERE public_id = ?',
            [self::PUBLIC_ID],
        );
        self::assertIsArray($row);
        self::assertSame('failed', $row['state']);
        self::assertSame('契约冲突', $row['stage']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_CONTRACT_CONFLICT, $row['error_code']);
        self::assertSame(1, OrgSql::int($row['rolled_back'] ?? null));
    }

    public function testAdvanceSystemUpgradeStageLadder(): void
    {
        $this->opsOn();
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, [
            'kind' => 'system_upgrade',
            'target_code' => 'host',
            'state' => 'queued',
            'progress' => 0,
            'stage_key' => null,
        ]);

        foreach ([['check', 20], ['backup', 40], ['download', 60], ['switch', 80], ['verify', 100]] as [$key, $progress]) {
            $this->ops->advance(self::PUBLIC_ID);
            $row = $this->conn->fetchAssociative(
                'SELECT state, stage_key, progress FROM sand_system_lifecycle_request WHERE public_id = ?',
                [self::PUBLIC_ID],
            );
            self::assertIsArray($row);
            self::assertSame('running', $row['state']);
            self::assertSame($key, $row['stage_key']);
            self::assertSame($progress, $row['progress']);
        }
        // verify 之后 done 分支走 apply()：写 host.version 用 CAST(:value AS jsonb)
        // 是 PG 方言，sqlite 跑不了，收尾写库由 Integration 覆盖。
    }
}
