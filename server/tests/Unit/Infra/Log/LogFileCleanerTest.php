<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Log;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Log\LogFileCleaner;
use Sandadmin\Infra\Log\LogRetentionPolicy;

/** 文件日志清理：按通道天数删过期滚动文件，符号链接与越界路径一律不删。 */
final class LogFileCleanerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/logclean-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file instanceof \SplFileInfo && ($file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname()));
        }
        rmdir($this->dir);
    }

    /** @return array<string, mixed> */
    private function policyConfig(string $channel, int $days, string $file): array
    {
        return [
            $channel => [
                'retention' => ['kind' => 'general', 'days' => $days],
                'handlers' => [['constructor' => [$file, 0]]],
            ],
        ];
    }

    private function writeLog(string $name): void
    {
        file_put_contents($this->dir . '/' . $name, 'x');
    }

    public function testDeletesOnlyExpiredFiles(): void
    {
        $today = new DateTimeImmutable('2026-11-17');
        $this->writeLog('app-2026-10-17.log');  // 31 天前：days=30 过期
        $this->writeLog('app-2026-10-18.log');  // 边界日：恰好 30 天前，保留
        $this->writeLog('app-2026-11-16.log');  // 留存期内
        $this->writeLog('app.log');             // 活动文件
        $this->writeLog('other-2026-10-01.log'); // 非本通道前缀
        $this->writeLog('app-2026-1-1.log');    // 日期段不规范，不匹配

        $policy = LogRetentionPolicy::fromArray(
            $this->policyConfig('default', 30, $this->dir . '/app.log'),
        );
        $rows = (new LogFileCleaner())->clean($policy, $today);

        self::assertCount(1, $rows);
        self::assertSame(1, $rows[0]['deleted_count']);
        self::assertFileDoesNotExist($this->dir . '/app-2026-10-17.log');
        self::assertFileExists($this->dir . '/app-2026-10-18.log');
        self::assertFileExists($this->dir . '/app-2026-11-16.log');
        self::assertFileExists($this->dir . '/app.log');
        self::assertFileExists($this->dir . '/other-2026-10-01.log');
        self::assertFileExists($this->dir . '/app-2026-1-1.log');
    }

    public function testChannelsUseTheirOwnDays(): void
    {
        $today = new DateTimeImmutable('2026-11-17');
        $this->writeLog('app-2026-10-01.log'); // 47 天前
        $this->writeLog('sec-2026-10-01.log'); // 同日：security 183 天内保留

        $config = $this->policyConfig('default', 30, $this->dir . '/app.log')
            + $this->policyConfig('security', 183, $this->dir . '/sec.log');
        $policy = LogRetentionPolicy::fromArray($config);
        $rows = (new LogFileCleaner())->clean($policy, $today);

        self::assertFileDoesNotExist($this->dir . '/app-2026-10-01.log');
        self::assertFileExists($this->dir . '/sec-2026-10-01.log');
        self::assertSame(1, $rows[0]['deleted_count']);
        self::assertSame(0, $rows[1]['deleted_count']);
    }

    public function testSymlinkEscapingDirIsNotDeleted(): void
    {
        $today = new DateTimeImmutable('2026-11-17');
        $outside = $this->dir . '/outside-secret.txt';
        file_put_contents($outside, 'keep');
        // 指向目录外目标的符号链接：名字命中过期模式，但绝不跟随删除
        symlink($outside, $this->dir . '/app-2026-01-01.log');
        $this->writeLog('app-2026-02-01.log'); // 真过期文件作对照

        $policy = LogRetentionPolicy::fromArray(
            $this->policyConfig('default', 30, $this->dir . '/app.log'),
        );
        $rows = (new LogFileCleaner())->clean($policy, $today);

        self::assertFileExists($outside);
        self::assertFileExists($this->dir . '/app-2026-01-01.log');
        self::assertFileDoesNotExist($this->dir . '/app-2026-02-01.log');
        self::assertSame(1, $rows[0]['skipped_count']);
        self::assertSame(1, $rows[0]['deleted_count']);
    }

    public function testMissingLogDirIsNoop(): void
    {
        $policy = LogRetentionPolicy::fromArray(
            $this->policyConfig('default', 30, $this->dir . '/nosuch/app.log'),
        );
        $rows = (new LogFileCleaner())->clean($policy);
        self::assertSame(0, $rows[0]['deleted_count']);
        self::assertSame(0, $rows[0]['skipped_count']);
    }
}
