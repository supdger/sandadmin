<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Migration;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Migration\MigrationCatalog;
use Sandadmin\Infra\Migration\MigrationFile;

/**
 * 迁移目录扫描与文件解析：版本提取、头注 release_version、down 配对、
 * 非法文件名与孤儿回退文件拒绝。
 */
final class MigrationCatalogTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/migrations';

    public function testLoadOrdersAndPairsDownFiles(): void
    {
        $catalog = MigrationCatalog::load(['host' => self::FIXTURE_DIR]);
        $files = $catalog->all();

        self::assertSame(['0001', '0002', '0003'], array_map(
            static fn (MigrationFile $file): string => $file->version,
            $files,
        ));
        self::assertSame('9.9.9', $files[0]->releaseVersion);
        self::assertFalse($files[0]->hasDown());
        self::assertTrue($files[1]->hasDown());
        self::assertSame('9.9.10', $files[1]->releaseVersion);
        self::assertNull($files[2]->releaseVersion);
        self::assertSame(
            hash('sha256', (string) file_get_contents(self::FIXTURE_DIR . '/0001_create_alpha.sql')),
            $files[0]->checksum,
        );
        self::assertSame(
            hash('sha256', (string) file_get_contents(self::FIXTURE_DIR . '/0002_add_beta.down.sql')),
            $files[1]->downChecksum(),
        );
    }

    public function testForNamespaceFilters(): void
    {
        $catalog = MigrationCatalog::load(['host' => self::FIXTURE_DIR]);
        self::assertCount(3, $catalog->forNamespace('host'));
        self::assertSame([], $catalog->forNamespace('sand-ai'));
    }

    public function testRejectsStraySqlName(): void
    {
        $dir = $this->scratchDir();
        file_put_contents($dir . '/readme.sql', '-- 不是迁移文件');
        $this->expectException(\InvalidArgumentException::class);
        MigrationCatalog::load(['host' => $dir]);
    }

    public function testRejectsOrphanDownFile(): void
    {
        $dir = $this->scratchDir();
        file_put_contents($dir . '/0001_create_alpha.sql', 'SELECT 1;');
        file_put_contents($dir . '/0002_add_beta.down.sql', 'SELECT 1;');
        $this->expectException(\InvalidArgumentException::class);
        MigrationCatalog::load(['host' => $dir]);
    }

    public function testRejectsBadNamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MigrationCatalog::load(['Host_Bad' => self::FIXTURE_DIR]);
    }

    public function testRejectsMissingDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MigrationCatalog::load(['host' => self::FIXTURE_DIR . '/nope']);
    }

    private function scratchDir(): string
    {
        $dir = sys_get_temp_dir() . '/sand_migration_test_' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        return $dir;
    }
}
