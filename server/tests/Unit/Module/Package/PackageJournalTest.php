<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\PackageJournal;
use Sandadmin\Tests\Support\FileKit;

/** journal 生命周期与中断恢复：按已完成步骤反推文件回滚，账本状态不归它管。 */
final class PackageJournalTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = FileKit::scratchRoot();
        if (!mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            self::fail('无法创建临时目录');
        }
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            FileKit::removeTree($this->dir);
            $this->dir = '';
        }
    }

    public function testBeginMarkFinishLifecycle(): void
    {
        $journal = new PackageJournal($this->dir . '/work');
        $id = $journal->begin('install', 'sand-hello', 'keep', $this->dir . '/plugin/sand-hello');
        $file = $this->dir . '/work/' . $id . '.json';

        self::assertFileExists($file);
        $journal->setPaths($id, staged: $this->dir . '/work/staged-' . $id);
        $journal->mark($id, 'staged');
        $journal->mark($id, 'placed');
        $raw = file_get_contents($file);
        self::assertIsString($raw);
        $data = json_decode($raw, true);
        self::assertIsArray($data);
        self::assertSame(['staged', 'placed'], $data['steps']);

        $journal->finish($id);
        self::assertFileDoesNotExist($file);
    }

    public function testRecoverInstallRemovesStagedAndPlacedDirs(): void
    {
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $staged = $work . '/staged-x';
        mkdir($staged, 0755, true);
        mkdir($plugin, 0755, true);
        file_put_contents($staged . '/a.txt', 'x');
        file_put_contents($plugin . '/b.txt', 'x');
        $this->writeJournal($work, 'install-1', [
            'kind' => 'install',
            'app' => 'sand-hello',
            'steps' => ['staged', 'placed'],
            'staged' => $staged,
            'backup' => null,
            'plugin' => $plugin,
        ]);

        $notes = (new PackageJournal($work))->recoverPending();

        self::assertNotEmpty($notes);
        self::assertDirectoryDoesNotExist($staged);
        self::assertDirectoryDoesNotExist($plugin);
        self::assertSame([], glob($work . '/*.json') ?: []);
    }

    public function testRecoverUpgradeRestoresBackup(): void
    {
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $backup = $work . '/backup-x';
        mkdir($plugin, 0755, true);
        mkdir($backup, 0755, true);
        file_put_contents($plugin . '/v.txt', 'new');
        file_put_contents($backup . '/v.txt', 'old');
        $this->writeJournal($work, 'upgrade-1', [
            'kind' => 'upgrade',
            'app' => 'sand-hello',
            'steps' => ['staged', 'backup', 'placed'],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ]);

        (new PackageJournal($work))->recoverPending();

        self::assertFileExists($plugin . '/v.txt');
        self::assertSame('old', file_get_contents($plugin . '/v.txt'));
        self::assertDirectoryDoesNotExist($backup);
    }

    public function testRecoverUninstallBeforeRegistryRestoresDir(): void
    {
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $backup = $work . '/backup-x';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/v.txt', 'old');
        $this->writeJournal($work, 'uninstall-1', [
            'kind' => 'uninstall',
            'app' => 'sand-hello',
            'steps' => ['removed'],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ]);

        (new PackageJournal($work))->recoverPending();

        self::assertFileExists($plugin . '/v.txt');
        self::assertDirectoryDoesNotExist($backup);
    }

    public function testRecoverUninstallBackupRecordedButNoStepsRestoresDir(): void
    {
        // 进程死在 setPaths 之后、mark 之前：journal 记了 backup 路径、
        // steps 空，backup 目录真实存在 → 账本没卸，目录得挪回去
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $backup = $work . '/backup-sand-hello-uninstall-1';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/v.txt', 'old');
        $this->writeJournal($work, 'uninstall-1', [
            'kind' => 'uninstall',
            'app' => 'sand-hello',
            'steps' => [],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ]);

        $notes = (new PackageJournal($work))->recoverPending();

        self::assertFileExists($plugin . '/v.txt');
        self::assertDirectoryDoesNotExist($backup);
        self::assertStringContainsString('恢复插件目录', implode("\n", $notes));
    }

    public function testRecoverUninstallBackupRecordedButNotMovedKeepsPlugin(): void
    {
        // 进程死在 setPaths 之后、rename 之前：backup 只是路径，目录还没挪走
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $backup = $work . '/backup-sand-hello-uninstall-2';
        mkdir($plugin, 0755, true);
        file_put_contents($plugin . '/v.txt', 'old');
        $this->writeJournal($work, 'uninstall-2', [
            'kind' => 'uninstall',
            'app' => 'sand-hello',
            'steps' => [],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ]);

        (new PackageJournal($work))->recoverPending();

        self::assertFileExists($plugin . '/v.txt');
    }

    public function testRecoverUpgradeBackupRecordedButNoStepsRestoresOld(): void
    {
        // 升级同样死在 setPaths 之后、mark 之前：旧版已挪走、新版没落位 → 恢复旧版
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $backup = $work . '/backup-sand-hello-upgrade-1';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/v.txt', 'old');
        $this->writeJournal($work, 'upgrade-1', [
            'kind' => 'upgrade',
            'app' => 'sand-hello',
            'steps' => ['staged'],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ]);

        (new PackageJournal($work))->recoverPending();

        self::assertFileExists($plugin . '/v.txt');
        self::assertSame('old', file_get_contents($plugin . '/v.txt'));
        self::assertDirectoryDoesNotExist($backup);
    }

    public function testRecoverUninstallAfterRegistryPurgesBackup(): void
    {
        $work = $this->dir . '/work';
        $plugin = $this->dir . '/plugin/sand-hello';
        $backup = $work . '/backup-x';
        mkdir($backup, 0755, true);
        file_put_contents($backup . '/v.txt', 'old');
        $this->writeJournal($work, 'uninstall-1', [
            'kind' => 'uninstall',
            'app' => 'sand-hello',
            'steps' => ['removed', 'unregistered'],
            'staged' => null,
            'backup' => $backup,
            'plugin' => $plugin,
        ]);

        // 账本已记卸载，恢复只能向前把备份删完，plugin 目录保持缺失
        (new PackageJournal($work))->recoverPending();

        self::assertDirectoryDoesNotExist($backup);
        self::assertDirectoryDoesNotExist($plugin);
    }

    public function testMalformedJournalIsKeptAndReported(): void
    {
        $work = $this->dir . '/work';
        mkdir($work, 0755, true);
        file_put_contents($work . '/garbage.json', '{broken');

        $notes = (new PackageJournal($work))->recoverPending();

        self::assertFileExists($work . '/garbage.json');
        self::assertStringContainsString('无法解析', implode("\n", $notes));
    }

    public function testRemoveTreeUnlinksSymlinksWithoutFollowing(): void
    {
        // is_dir 跟随符号链接：不先判 is_link 会把指向外部的软链当真目录
        // 递归进去，删掉插件目录外面的内容
        $outside = $this->dir . '/outside';
        mkdir($outside, 0755, true);
        file_put_contents($outside . '/keep.txt', 'stay');
        $tree = $this->dir . '/tree';
        mkdir($tree . '/sub', 0755, true);
        file_put_contents($tree . '/sub/file.txt', 'x');
        self::assertTrue(symlink($outside, $tree . '/dir-link'));
        self::assertTrue(symlink($outside . '/keep.txt', $tree . '/file-link'));
        self::assertTrue(symlink($this->dir . '/missing', $tree . '/dangling'));

        PackageJournal::removeTree($tree);

        self::assertDirectoryDoesNotExist($tree);
        self::assertDirectoryExists($outside);
        self::assertSame('stay', file_get_contents($outside . '/keep.txt'));
    }

    public function testRemoveTreeOnSymlinkOnlyRemovesLink(): void
    {
        $target = $this->dir . '/target';
        mkdir($target, 0755, true);
        file_put_contents($target . '/keep.txt', 'stay');
        $link = $this->dir . '/link';
        self::assertTrue(symlink($target, $link));

        PackageJournal::removeTree($link);

        self::assertFalse(is_link($link));
        self::assertDirectoryExists($target);
        self::assertSame('stay', file_get_contents($target . '/keep.txt'));
    }

    /** @param array<string, mixed> $data */
    private function writeJournal(string $work, string $id, array $data): void
    {
        if (!is_dir($work) && !mkdir($work, 0755, true)) {
            self::fail('无法创建 journal 目录');
        }
        $encoded = json_encode($data);
        self::assertIsString($encoded);
        file_put_contents($work . '/' . $id . '.json', $encoded);
    }
}
