<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Dev;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Infra\Mcp\Dev\PhpunitTool;
use Sandadmin\Infra\Mcp\Dev\SubprocessOutcome;

/**
 * PHPUnit 目标白名单：注入式字符串一律拒绝；合法目标拼出的是固定 argv
 * 前缀 + 校验过的相对路径，runner 用桩确认不会真起子进程。
 */
final class PhpunitToolTest extends TestCase
{
    /** @var null|array{cmd: list<string>, cwd: string, timeout: int} */
    private ?array $captured = null;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phpunit-tool-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/tests/Unit', 0755, true);
        file_put_contents($this->root . '/tests/Unit/FooTest.php', "<?php\n");
        $this->captured = null;
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/tests/Unit/FooTest.php');
        @rmdir($this->root . '/tests/Unit');
        @rmdir($this->root . '/tests');
        @rmdir($this->root);
        parent::tearDown();
    }

    public function testRejectsInjectionStrings(): void
    {
        $tool = $this->tool();
        $identity = $this->identity();
        $bad = [
            'tests/Unit/FooTest.php; rm -rf /',
            'tests/Unit/FooTest.php && curl evil',
            'tests/../../etc/passwd.php',
            '../tests/Unit/FooTest.php',
            'tests/Unit/$(whoami).php',
            'tests/Unit/FooTest.php|nc',
            '/absolute/path/FooTest.php',
            'tests/Unit/FooTest.php --filter=x',
            'vendor/bin/phpunit',
            'tests/Unit/FooTest',
            'tests/Unit',
            '',
        ];
        foreach ($bad as $target) {
            $outcome = $tool->run($identity, ['target' => $target]);
            self::assertFalse($outcome->succeeded, '应拒绝：' . $target);
            self::assertContains($outcome->errorCode, ['MCP_TARGET_REJECTED', 'MCP_BAD_ARGUMENT']);
        }
        self::assertNull($this->captured);
    }

    public function testAcceptsRelativePath(): void
    {
        $outcome = $this->tool()->run($this->identity(), ['target' => 'tests/Unit/FooTest.php']);

        self::assertTrue($outcome->succeeded);
        self::assertNotNull($this->captured);
        self::assertSame('vendor/bin/phpunit', $this->captured['cmd'][1]);
        self::assertSame('tests/Unit/FooTest.php', $this->captured['cmd'][2]);
        self::assertSame($this->root, $this->captured['cwd']);
        self::assertSame(120, $this->captured['timeout']);
    }

    public function testAcceptsClassName(): void
    {
        $outcome = $this->tool()->run($this->identity(), ['target' => 'Sandadmin\\Tests\\Unit\\FooTest']);

        self::assertTrue($outcome->succeeded);
        self::assertNotNull($this->captured);
        self::assertSame('tests/Unit/FooTest.php', $this->captured['cmd'][2]);
    }

    public function testRejectsClassMappingMissingFile(): void
    {
        $outcome = $this->tool()->run($this->identity(), ['target' => 'Sandadmin\\Tests\\Unit\\MissingTest']);

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_TARGET_REJECTED', $outcome->errorCode);
        self::assertNull($this->captured);
    }

    private function tool(): PhpunitTool
    {
        return new PhpunitTool(
            $this->root,
            120,
            function (array $cmd, string $cwd, int $timeout): SubprocessOutcome {
                $this->captured = ['cmd' => $cmd, 'cwd' => $cwd, 'timeout' => $timeout];

                return new SubprocessOutcome(0, 'OK', false, 1);
            },
        );
    }

    private function identity(): McpIdentity
    {
        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']);
    }
}
