<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Http\ScheduleHttp;
use Sandadmin\Module\Org\OrgSql;

/** J11 定时任务：种子、列表、改时间、立即执行、执行记录、错误码。 */
final class ScheduleHttpTest extends ToolsCase
{
    private function http(): ScheduleHttp
    {
        return new ScheduleHttp($this->conn);
    }

    /** @param array{status: int, body: array<string, mixed>} $result */
    private static function dataOf(array $result): mixed
    {
        return $result['body']['data'] ?? null;
    }

    /** @param array{status: int, body: array<string, mixed>} $result */
    private static function itemsOf(array $result): mixed
    {
        $data = self::dataOf($result);

        return is_array($data) ? ($data['items'] ?? null) : null;
    }

    public function testIndexSeedsHostTasks(): void
    {
        $result = $this->http()->index([], 'req-1');
        self::assertSame(200, $result['status']);
        $items = self::itemsOf($result);
        self::assertIsArray($items);
        self::assertNotEmpty($items);
        $codes = array_column($items, 'code');
        self::assertContains('host.message-cleanup', $codes);
        // 下次三次预览
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['status'] ?? null) === 'enabled' && ($item['next_run_time'] ?? null) !== null) {
                self::assertIsArray($item['next_run_times']);
                self::assertCount(3, $item['next_run_times']);
            }
        }
    }

    public function testUpdateSchedulePersistsOverrideAndRecalculatesNext(): void
    {
        $this->http()->index([], 'req-1');
        $result = $this->http()->update(
            'host.message-cleanup',
            ['schedule' => ['freq' => 'daily', 'time' => '04:30']],
            'req-2',
            $this->userActor(),
            '127.0.0.1',
            'Mac',
        );
        self::assertSame(200, $result['status']);
        $item = self::dataOf($result);
        self::assertIsArray($item);
        self::assertNotNull($item['next_run_time']);
        self::assertIsArray($item['next_run_times']);
        self::assertCount(3, $item['next_run_times']);
        $stored = $this->conn->fetchOne('SELECT schedule::text FROM sand_system_schedule WHERE code = ?', ['host.message-cleanup']);
        self::assertStringContainsString('daily', is_string($stored) ? $stored : '');
        // 改了时间要落审计
        self::assertSame(1, OrgSql::int($this->conn->fetchOne("SELECT COUNT(*) FROM sand_system_audit_log WHERE module = 'schedule'")));
    }

    public function testInvalidIntervalGives422WithFieldError(): void
    {
        $this->http()->index([], 'req-1');
        $result = $this->http()->update(
            'host.message-cleanup',
            ['schedule' => ['freq' => 'interval', 'interval' => 3]],
            'req-3',
        );
        self::assertSame(422, $result['status']);
        self::assertSame('TASK_SCHEDULE_INVALID', $result['body']['code']);
        self::assertIsArray($result['body']['fields']);
        self::assertArrayHasKey('interval', $result['body']['fields']);
    }

    public function testMissingTaskGives404(): void
    {
        $result = $this->http()->update('ghost.task', ['status' => 'disabled'], 'req-4');
        self::assertSame(404, $result['status']);
        self::assertSame('TASK_NOT_FOUND', $result['body']['code']);
    }

    public function testManualRunWritesSucceededRunRow(): void
    {
        $this->http()->index([], 'req-1');
        $result = $this->http()->run('host.message-cleanup', 'req-run-1', $this->userActor(), '127.0.0.1', 'Mac');
        self::assertSame(200, $result['status']);
        $data = self::dataOf($result);
        self::assertIsArray($data);
        $runId = OrgSql::int($data['schedule_run_id'] ?? null);
        self::assertGreaterThan(0, $runId);
        $row = $this->conn->fetchAssociative('SELECT trigger, succeeded FROM sand_system_schedule_run WHERE id = ?', [$runId]);
        self::assertNotFalse($row);
        self::assertSame('manual', $row['trigger']);
        self::assertTrue($row['succeeded'] === true || $row['succeeded'] === 't' || $row['succeeded'] === '1');
        // 列表的 last_run 也被刷新
        $items = self::itemsOf($this->http()->index([], 'r'));
        self::assertIsArray($items);
        $item = array_values(array_filter($items, static fn (mixed $entry): bool => is_array($entry) && ($entry['code'] ?? null) === 'host.message-cleanup'))[0];
        self::assertNotNull($item['last_run_time']);
    }

    public function testRunsHistoryPaginationAndStatus(): void
    {
        $this->http()->index([], 'req-1');
        $this->conn->insert('sand_system_schedule_run', [
            'schedule_code' => 'host.message-cleanup',
            'trigger' => 'schedule',
            'start_time' => '2026-01-01 00:00:00+00',
            'finish_time' => '2026-01-01 00:00:02+00',
            'succeeded' => 'false',
            'error_message' => '磁盘满了',
            'error_code' => 'DISK_FULL',
            'request_id' => 'req-old',
        ]);
        $result = $this->http()->runs('host.message-cleanup', [], 'req-5');
        self::assertSame(200, $result['status']);
        $data = self::dataOf($result);
        self::assertIsArray($data);
        self::assertSame(1, $data['total']);
        self::assertIsArray($data['items']);
        $run = $data['items'][0];
        self::assertIsArray($run);
        self::assertSame('failed', $run['status']);
        self::assertSame(2000, $run['duration_ms']);
        self::assertSame('磁盘满了', $run['message']);
        self::assertSame('DISK_FULL', $run['error_code']);
    }

    public function testRunsOnMissingTaskGives404(): void
    {
        $result = $this->http()->runs('ghost.task', [], 'req-6');
        self::assertSame(404, $result['status']);
        self::assertSame('TASK_NOT_FOUND', $result['body']['code']);
    }
}
