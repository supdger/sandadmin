<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Log;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Log\JsonLogFormatter;

final class JsonLogFormatterTest extends TestCase
{
    private JsonLogFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonLogFormatter();
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function format(array $record): array
    {
        $line = $this->formatter->format($record);
        $decoded = json_decode($line, true);
        self::assertIsArray($decoded);
        /** @var array<string, mixed> $decoded */

        return $decoded;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function record(array $extra = []): array
    {
        return [
            'message' => 'test message',
            'context' => ['user_id' => 7],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'default',
            'datetime' => new \DateTimeImmutable('2026-11-19T01:02:03+00:00'),
            'extra' => $extra,
        ];
    }

    public function testOutputsContractFields(): void
    {
        $out = $this->format($this->record());

        self::assertArrayHasKey('time', $out);
        self::assertArrayNotHasKey('datetime', $out);
        self::assertSame('INFO', $out['level_name']);
        self::assertSame(200, $out['level']);
        self::assertSame('default', $out['channel']);
        self::assertSame('test message', $out['message']);
        self::assertSame(['user_id' => 7], $out['context']);
        self::assertIsString($out['time']);
        self::assertStringStartsWith('2026-11-19T01:02:03', $out['time']);
    }

    public function testHoistsScopeFieldsToTopLevel(): void
    {
        $out = $this->format($this->record([
            'request_id' => 'req-abc12345',
            'task_id' => 'task-1',
            'attempt' => 2,
            'other' => 'keep',
        ]));

        self::assertSame('req-abc12345', $out['request_id']);
        self::assertSame('task-1', $out['task_id']);
        self::assertSame(2, $out['attempt']);
        self::assertSame(['other' => 'keep'], $out['extra']);
    }

    public function testOmitsEmptyContextAndExtra(): void
    {
        $record = $this->record();
        $record['context'] = [];
        $record['extra'] = [];
        $out = $this->format($record);

        self::assertArrayNotHasKey('context', $out);
        self::assertArrayNotHasKey('extra', $out);
        self::assertArrayNotHasKey('request_id', $out);
    }
}
