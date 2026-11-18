<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\PackageExecutor;
use Sandadmin\Tests\Support\FakePluginRegistry;
use Sandadmin\Tests\Support\FileKit;
use Sandadmin\Tests\Support\PackageKit;

/**
 * 离线包生命周期闭环（无库）：临时目录拼迷你仓 + 内存账本。
 * 覆盖安装闸各条拒装理由、落位/记账/契约收集顺序、失败回滚与 journal 恢复。
 * SAND_COMPILED=0 才放行执行器；测试结束恢复环境变量。
 */
final class PackageExecutorTest extends TestCase
{
    /** 与宿主已登记码撞名的冲突片段，契约收集必失败。 */
    private const CONFLICT_YAML = <<<'YAML'
        openapi: 3.1.0
        info: {title: bad, version: "0.1"}
        x-error-catalog:
          NOT_FOUND: {http_status: 404, summary: 重复声明宿主已登记的码}
        paths: {}
        YAML;

    private string $dir = '';
    private string $repoDir = '';

    /** @var string|false */
    private string|false $compiledEnv = false;

    /** @var string|false */
    private string|false $appEnv = false;

    private FakePluginRegistry $registry;

    protected function setUp(): void
    {
        $this->compiledEnv = getenv('SAND_COMPILED');
        $this->appEnv = getenv('APP_ENV');
        putenv('SAND_COMPILED=0');
        putenv('APP_ENV');

        $this->dir = FileKit::scratchRoot();
        $this->repoDir = $this->dir . '/repo';
        foreach (['contract', 'server/plugin', 'work'] as $sub) {
            if (!mkdir($this->repoDir . '/' . $sub, 0755, true) && !is_dir($this->repoDir . '/' . $sub)) {
                self::fail('无法创建临时仓目录 ' . $sub);
            }
        }
        $source = PackageKit::repoRoot() . '/contract/openapi.yaml';
        if (!copy($source, $this->repoDir . '/contract/openapi.yaml')) {
            self::fail('无法复制宿主契约到临时仓');
        }
        $this->registry = new FakePluginRegistry();
    }

    protected function tearDown(): void
    {
        if ($this->compiledEnv === false) {
            putenv('SAND_COMPILED');
        } else {
            putenv('SAND_COMPILED=' . $this->compiledEnv);
        }
        if ($this->appEnv === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->appEnv);
        }
        if ($this->dir !== '' && is_dir($this->dir)) {
            FileKit::removeTree($this->dir);
            $this->dir = '';
        }
    }

    public function testInstallSucceedsEndToEnd(): void
    {
        $zip = $this->zip('hello');
        $digest = hash_file('sha256', $zip);
        self::assertIsString($digest);

        $result = $this->executor()->install($zip, $digest);

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertSame('sand-hello', $result['app']);
        self::assertSame('0.1.0', $result['version']);
        self::assertTrue($result['sha256_verified']);
        self::assertFalse($result['signature_verified']);
        $plugin = $this->pluginPath('sand-hello');
        self::assertFileExists($plugin . '/manifest.json');
        self::assertFileExists($plugin . '/contract/openapi.yaml');
        self::assertSame('0.1.0', $this->registry->installedVersion('sand-hello'));
        $routes = file_get_contents($this->repoDir . '/server/config/plugin-routes.generated.json');
        self::assertIsString($routes);
        self::assertStringContainsString('plugin/sand-hello/config/route.php', $routes);
    }

    public function testInstallRejectsSha256Mismatch(): void
    {
        $result = $this->executor()->install($this->zip('hello'), str_repeat('0', 64));

        self::assertSame('failed', $result['result']);
        self::assertFalse($result['sha256_verified']);
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-hello'));
        self::assertNull($this->registry->installedVersion('sand-hello'));
    }

    public function testInstallRejectsKernelOutOfRange(): void
    {
        $zip = $this->zip('kernel', ['kernel_api_min' => '1.5.0']);

        $result = $this->executor()->install($zip, null);

        self::assertSame('failed', $result['result']);
        self::assertSame('gate', $result['stage']);
        self::assertStringContainsString('kernel_api', implode("\n", $result['problems']));
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-hello'));
    }

    public function testInstallRejectsUnsupportedDatabase(): void
    {
        $zip = $this->zip('db', ['databases' => ['mysql']]);

        $result = $this->executor()->install($zip, null);

        self::assertSame('failed', $result['result']);
        self::assertSame('gate', $result['stage']);
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-hello'));
    }

    public function testInstallRejectsMissingRequire(): void
    {
        $zip = $this->zip('requires', ['requires' => ['sand-iam' => '0.8.0,0.9.9']]);

        $result = $this->executor()->install($zip, null);

        self::assertSame('failed', $result['result']);
        self::assertSame('gate', $result['stage']);
        self::assertStringContainsString('sand-iam', implode("\n", $result['problems']));
    }

    public function testInstallPassesWithSatisfiedRequire(): void
    {
        $this->registry->recordInstall('sand-iam', 'sand-iam', '0.8.4', 'x');
        $zip = $this->zip('requires-ok', ['requires' => ['sand-iam' => '0.8.0,0.9.9']]);

        $result = $this->executor()->install($zip, null);

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
    }

    public function testInstallRejectsBannedEntries(): void
    {
        $zip = $this->zip('banned', null, ['plugin/sand-hello/install.sql' => 'select 1']);

        $result = $this->executor()->install($zip, null);

        self::assertSame('failed', $result['result']);
        self::assertSame('inspect', $result['stage']);
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-hello'));
    }

    public function testInstallTwiceRejected(): void
    {
        $first = $this->executor()->install($this->zip('hello'), null);
        self::assertSame('ok', $first['result'], implode("\n", $first['problems']));

        $result = $this->executor()->install($this->zip('hello'), null);

        self::assertSame('failed', $result['result']);
        self::assertSame('gate', $result['stage']);
        self::assertStringContainsString('已安装', implode("\n", $result['problems']));
    }

    public function testContractConflictRollsBackFilesAndRegistry(): void
    {
        $manifest = PackageKit::fixtureManifest('sand-hello', ['app' => 'sand-bad']);
        $file = $this->dir . '/bad.zip';
        PackageKit::makeZip($file, [
            'manifest.json' => $manifest,
            'plugin/sand-bad/contract/openapi.yaml' => self::CONFLICT_YAML,
            'plugin/sand-bad/app/thing.php' => '<?php',
        ]);

        $result = $this->executor()->install($file, null);

        self::assertSame('failed', $result['result']);
        self::assertSame('collect', $result['stage']);
        // 文件与账本全部回滚：目录不存在，账本不能停在 installed
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-bad'));
        self::assertNull($this->registry->installedVersion('sand-bad'));
        $routes = $this->repoDir . '/server/config/plugin-routes.generated.json';
        if (is_file($routes)) {
            $content = file_get_contents($routes);
            self::assertIsString($content);
            self::assertStringNotContainsString('sand-bad', $content);
        }
    }

    public function testUpgradeSwitchesVersion(): void
    {
        $first = $this->executor()->install($this->zip('hello'), null);
        self::assertSame('ok', $first['result'], implode("\n", $first['problems']));
        $zip = $this->zip('hello-2', ['version' => '0.2.0']);

        $result = $this->executor()->upgrade($zip, null);

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertSame('0.2.0', $this->registry->installedVersion('sand-hello'));
        $manifest = file_get_contents($this->pluginPath('sand-hello') . '/manifest.json');
        self::assertIsString($manifest);
        self::assertStringContainsString('0.2.0', $manifest);
    }

    public function testUpgradeConflictRestoresOldVersion(): void
    {
        $first = $this->executor()->install($this->zip('hello'), null);
        self::assertSame('ok', $first['result'], implode("\n", $first['problems']));
        $manifest = PackageKit::fixtureManifest('sand-hello', ['version' => '0.2.0']);
        $file = $this->dir . '/bad-upgrade.zip';
        PackageKit::fixtureZip($file, 'sand-hello', $manifest, [
            'plugin/sand-hello/contract/openapi.yaml' => self::CONFLICT_YAML,
        ]);

        $result = $this->executor()->upgrade($file, null);

        self::assertSame('failed', $result['result']);
        self::assertSame('collect', $result['stage']);
        // 文件与账本都回到旧版
        self::assertSame('0.1.0', $this->registry->installedVersion('sand-hello'));
        $restored = file_get_contents($this->pluginPath('sand-hello') . '/manifest.json');
        self::assertIsString($restored);
        self::assertStringContainsString('0.1.0', $restored);
    }

    public function testUninstallKeepRemovesDirAndMarksRow(): void
    {
        $first = $this->executor()->install($this->zip('hello'), null);
        self::assertSame('ok', $first['result'], implode("\n", $first['problems']));

        $result = $this->executor()->uninstall('sand-hello', 'keep');

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertFalse($result['data_removed']);
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-hello'));
        self::assertSame('uninstalled', $this->registry->stateOf('sand-hello'));
        $routes = file_get_contents($this->repoDir . '/server/config/plugin-routes.generated.json');
        self::assertIsString($routes);
        self::assertStringNotContainsString('sand-hello', $routes);
    }

    public function testUninstallDeletePolicyReportsPendingDataRemoval(): void
    {
        $first = $this->executor()->install($this->zip('hello'), null);
        self::assertSame('ok', $first['result'], implode("\n", $first['problems']));

        $result = $this->executor()->uninstall('sand-hello', 'delete');

        self::assertSame('ok', $result['result']);
        // 删数据要连库，迁移执行器另行执行：如实报 data_removed false
        self::assertFalse($result['data_removed']);
        self::assertStringContainsString('迁移执行器', implode("\n", $result['problems']));
    }

    public function testUninstallRejectsNotInstalled(): void
    {
        $result = $this->executor()->uninstall('sand-hello', 'keep');

        self::assertSame('failed', $result['result']);
        self::assertSame('gate', $result['stage']);
    }

    public function testRecoverCleansInterruptedInstall(): void
    {
        $work = $this->dir . '/work';
        $plugin = $this->pluginPath('sand-hello');
        $staged = $work . '/staged-x';
        mkdir($staged, 0755, true);
        mkdir($plugin, 0755, true);
        file_put_contents($plugin . '/a.txt', 'x');
        $journal = [
            'kind' => 'install',
            'app' => 'sand-hello',
            'data_policy' => 'keep',
            'steps' => ['staged', 'placed'],
            'staged' => $staged,
            'backup' => null,
            'plugin' => $plugin,
        ];
        if (!is_dir($work)) {
            mkdir($work, 0755, true);
        }
        $encoded = json_encode($journal);
        self::assertIsString($encoded);
        file_put_contents($work . '/install-x.json', $encoded);

        $result = $this->executor()->recover();

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertDirectoryDoesNotExist($plugin);
        self::assertDirectoryDoesNotExist($staged);
        self::assertNotEmpty($result['notes']);
    }

    public function testRecoverInterruptedUninstallRestoresFromJournalBackup(): void
    {
        // 中断现场：journal 记了 backup 路径、steps 空，backup 目录真实存在
        // （setPaths 已挪到 rename 之前的产物）→ 恢复后插件目录得回来
        $work = $this->dir . '/work';
        $plugin = $this->pluginPath('sand-hello');
        $backup = $work . '/backup-sand-hello-uninstall-a1b2c3d4e5f6';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/manifest.json', PackageKit::fixtureManifest('sand-hello'));
        $journal = [
            'kind' => 'uninstall',
            'app' => 'sand-hello',
            'data_policy' => 'keep',
            'steps' => [],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ];
        $encoded = json_encode($journal);
        self::assertIsString($encoded);
        file_put_contents($work . '/uninstall-a1b2c3d4e5f6.json', $encoded);

        $result = $this->executor()->recover();

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertFileExists($plugin . '/manifest.json');
        self::assertDirectoryDoesNotExist($backup);
        $notes = implode("\n", $result['notes']);
        self::assertStringContainsString('恢复插件目录', $notes);
        self::assertStringNotContainsString('无文件遗留', $notes);
    }

    public function testRecoverRestoresOrphanBackupWithoutJournal(): void
    {
        // 无 journal 的孤儿 backup-<app>-<id>：插件目录缺失 → 按目录名找回 app 挪回去
        $work = $this->dir . '/work';
        $plugin = $this->pluginPath('sand-hello');
        $backup = $work . '/backup-sand-hello-uninstall-001122334455';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/manifest.json', PackageKit::fixtureManifest('sand-hello'));

        $result = $this->executor()->recover();

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertFileExists($plugin . '/manifest.json');
        self::assertDirectoryDoesNotExist($backup);
        self::assertStringContainsString('孤儿备份', implode("\n", $result['notes']));
    }

    public function testRecoverPurgesOrphanBackupWhenPluginDirExists(): void
    {
        // 插件目录还在 → 孤儿备份是已完成操作的残留，直接清掉
        $work = $this->dir . '/work';
        $plugin = $this->pluginPath('sand-hello');
        $backup = $work . '/backup-sand-hello-upgrade-001122334455';
        mkdir($plugin, 0755, true);
        file_put_contents($plugin . '/manifest.json', PackageKit::fixtureManifest('sand-hello'));
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/v.txt', 'old');

        $result = $this->executor()->recover();

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        self::assertFileExists($plugin . '/manifest.json');
        self::assertDirectoryDoesNotExist($backup);
        self::assertStringContainsString('遗留备份', implode("\n", $result['notes']));
    }

    public function testRecoverKeepsBackupWithLiveJournal(): void
    {
        // 有对应 journal 的 backup 不是孤儿：rollback 自己处理，不由孤儿扫描代劳
        $work = $this->dir . '/work';
        $plugin = $this->pluginPath('sand-hello');
        $backup = $work . '/backup-sand-hello-upgrade-aabbccddeeff';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/v.txt', 'old');
        $journal = [
            'kind' => 'upgrade',
            'app' => 'sand-hello',
            'data_policy' => 'keep',
            'steps' => ['staged'],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ];
        $encoded = json_encode($journal);
        self::assertIsString($encoded);
        file_put_contents($work . '/upgrade-aabbccddeeff.json', $encoded);

        $result = $this->executor()->recover();

        self::assertSame('ok', $result['result'], implode("\n", $result['problems']));
        // journal rollback 恢复旧版，孤儿扫描不得再当残留清掉
        self::assertFileExists($plugin . '/v.txt');
        self::assertSame('old', file_get_contents($plugin . '/v.txt'));
        self::assertStringNotContainsString('孤儿备份', implode("\n", $result['notes']));
    }

    public function testCompiledHostRefusesInstall(): void
    {
        putenv('SAND_COMPILED=1');

        $result = $this->executor()->install($this->zip('hello'), null);

        self::assertSame('failed', $result['result']);
        self::assertSame('gate', $result['stage']);
        self::assertDirectoryDoesNotExist($this->pluginPath('sand-hello'));
    }

    private function executor(string $dbDriver = 'pgsql'): PackageExecutor
    {
        return new PackageExecutor(
            $this->registry,
            $this->repoDir,
            $this->repoDir . '/server/plugin',
            $this->dir . '/work',
            $dbDriver,
        );
    }

    private function pluginPath(string $app): string
    {
        return $this->repoDir . '/server/plugin/' . $app;
    }

    /**
     * @param array<string, mixed>|null $overrides
     * @param array<string, string> $extra
     */
    private function zip(string $name, ?array $overrides = null, array $extra = []): string
    {
        $file = $this->dir . '/' . $name . '.zip';
        PackageKit::fixtureZip($file, 'sand-hello', PackageKit::fixtureManifest('sand-hello', $overrides), $extra);

        return $file;
    }
}
