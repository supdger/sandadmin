<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Migration;

use PHPUnit\Framework\TestCase;

/**
 * 基线入仓的哈希锁定（C7）：0001–0003 必须与定稿来源逐字节一致，
 * 基线不带回退文件，0004–0007 必须带同名 .down.sql。
 */
final class BaselineHashTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../../database/migrations';

    private const HASHES = [
        '0001_baseline_1_0_0.sql' => 'd2dc35f04f9f50494ac5250379ded6dd03f642ba70a18d60bc4f27bb529fe232',
        '0002_add_dict_type_extension_key.sql' => '34e94ce2e19cc67e01cd190f29ccfcf125070fc9c4414734b5bc4edaecc2dac8',
        '0003_add_lifecycle_request_stage_key.sql' => '0e33062d870a5242709857bfb4922a8bb442e6a6cbc0872727a45ca3f07c6616',
    ];

    public function testBaselineFilesMatchLockedHashes(): void
    {
        foreach (self::HASHES as $name => $expected) {
            $path = self::DIR . '/' . $name;
            self::assertFileExists($path);
            self::assertSame($expected, hash_file('sha256', $path), $name);
        }
    }

    public function testBaselineHasNoDownFiles(): void
    {
        foreach (['0001', '0002', '0003'] as $version) {
            self::assertSame(
                [],
                glob(self::DIR . '/' . $version . '_*.down.sql') ?: [],
                "基线 {$version} 不应有回退文件",
            );
        }
    }

    public function testIncrementalMigrationsHaveDownFiles(): void
    {
        foreach (glob(self::DIR . '/*.sql') ?: [] as $path) {
            $name = basename($path);
            if (str_ends_with($name, '.down.sql')) {
                continue;
            }
            if (preg_match('/^(000[4567])_/', $name) !== 1) {
                continue;
            }
            self::assertFileExists(
                self::DIR . '/' . substr($name, 0, -4) . '.down.sql',
                "{$name} 缺回退文件",
            );
        }
    }
}
