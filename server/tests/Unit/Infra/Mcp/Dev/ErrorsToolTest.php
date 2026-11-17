<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Dev;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Infra\Mcp\Dev\ErrorsTool;

/** 错误摘读工具：kind/lines 校验、按类过滤、命中行脱敏。 */
final class ErrorsToolTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/errors-tool-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0755, true);
        file_put_contents($this->dir . '/webman.log', implode("\n", [
            '[2026-11-17] info normal line',
            '[2026-11-17] error query failed password=hunter2',
            '[2026-11-17] slow request took 3000ms',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/webman.log');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testBadKindRejected(): void
    {
        $outcome = (new ErrorsTool($this->dir))->run($this->identity(), ['kind' => 'whatever']);

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_BAD_ARGUMENT', $outcome->errorCode);
    }

    public function testBadLinesRejected(): void
    {
        $outcome = (new ErrorsTool($this->dir))->run($this->identity(), ['lines' => 9999]);

        self::assertFalse($outcome->succeeded);
    }

    public function testErrorEntriesRedacted(): void
    {
        $outcome = (new ErrorsTool($this->dir))->run($this->identity(), ['kind' => 'error']);

        self::assertTrue($outcome->succeeded);
        $entries = $this->entries($outcome);
        self::assertCount(1, $entries);
        $entry = $entries[0] ?? [];
        self::assertIsArray($entry);
        $line = $entry['line'] ?? '';
        self::assertIsString($line);
        self::assertStringNotContainsString('hunter2', $line);
    }

    public function testSlowEntries(): void
    {
        $outcome = (new ErrorsTool($this->dir))->run($this->identity(), ['kind' => 'slow']);

        self::assertTrue($outcome->succeeded);
        self::assertCount(1, $this->entries($outcome));
    }

    public function testMissingDirEmpty(): void
    {
        $outcome = (new ErrorsTool($this->dir . '/none'))->run($this->identity(), []);

        self::assertTrue($outcome->succeeded);
        self::assertIsArray($outcome->payload);
        self::assertSame([], $outcome->payload['entries']);
    }

    /** @return list<mixed> */
    private function entries(\Sandadmin\Domain\Mcp\ToolOutcome $outcome): array
    {
        self::assertIsArray($outcome->payload);
        $entries = $outcome->payload['entries'] ?? [];
        self::assertIsArray($entries);

        return array_values($entries);
    }

    private function identity(): McpIdentity
    {
        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']);
    }
}
