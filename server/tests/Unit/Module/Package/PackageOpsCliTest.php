<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Tests\Support\PackageKit;

/**
 * tools/package-ops 的进程级断言：连接串含口令时，协议不支持的
 * 报错只回显协议名，不把整条 SAND_DB_URL 打进 stderr（进日志即泄密）。
 */
final class PackageOpsCliTest extends TestCase
{
    private string|false $dbUrl = false;

    protected function setUp(): void
    {
        $this->dbUrl = getenv('SAND_DB_URL');
    }

    protected function tearDown(): void
    {
        if ($this->dbUrl === false) {
            putenv('SAND_DB_URL');
        } else {
            putenv('SAND_DB_URL=' . $this->dbUrl);
        }
    }

    public function testUnsupportedDbSchemeEchoesSchemeNotConnectionString(): void
    {
        putenv('SAND_DB_URL=oracle://deploy:s3cr3t-word@db.internal:1521/app');
        $script = PackageKit::repoRoot() . '/tools/package-ops';
        $zip = PackageKit::repoRoot() . '/does-not-matter.zip';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script)
            . ' install ' . escapeshellarg($zip) . ' --allow-unsigned 2>&1';

        exec($command, $output, $code);

        $stderr = implode("\n", $output);
        self::assertSame(2, $code, $stderr);
        self::assertStringContainsString('协议不支持：oracle', $stderr);
        foreach (['s3cr3t-word', 'deploy', 'db.internal'] as $secret) {
            self::assertStringNotContainsString($secret, $stderr);
        }
    }
}
