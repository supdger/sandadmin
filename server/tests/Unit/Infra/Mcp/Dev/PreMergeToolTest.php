<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Dev;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Infra\Mcp\Dev\PreMergeTool;
use Sandadmin\Infra\Mcp\Dev\SubprocessOutcome;

/** 快速门禁工具：脚本缺失报未就绪，命中时 argv 是固定三段，不接任何入参。 */
final class PreMergeToolTest extends TestCase
{
    /** @var null|array{cmd: list<string>, cwd: string, timeout: int} */
    private ?array $captured = null;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/premerge-tool-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/tools', 0755, true);
        $this->captured = null;
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/tools/pre-merge.sh');
        @rmdir($this->root . '/tools');
        @rmdir($this->root);
        parent::tearDown();
    }

    public function testMissingScript(): void
    {
        $outcome = $this->tool()->run($this->identity(), []);

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_GATE_MISSING', $outcome->errorCode);
        self::assertNull($this->captured);
    }

    public function testFixedArgv(): void
    {
        file_put_contents($this->root . '/tools/pre-merge.sh', "#!/bin/sh\nexit 0\n");
        // 入参里放什么都不得进入 argv
        $outcome = $this->tool()->run($this->identity(), ['inject' => 'evil; rm -rf /']);

        self::assertTrue($outcome->succeeded);
        self::assertNotNull($this->captured);
        self::assertSame(['bash', 'tools/pre-merge.sh', '--fast'], $this->captured['cmd']);
        self::assertSame($this->root, $this->captured['cwd']);
        self::assertSame(180, $this->captured['timeout']);
    }

    private function tool(): PreMergeTool
    {
        return new PreMergeTool(
            $this->root,
            180,
            function (array $cmd, string $cwd, int $timeout): SubprocessOutcome {
                $this->captured = ['cmd' => $cmd, 'cwd' => $cwd, 'timeout' => $timeout];

                return new SubprocessOutcome(0, 'PASS', false, 1);
            },
        );
    }

    private function identity(): McpIdentity
    {
        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']);
    }
}
