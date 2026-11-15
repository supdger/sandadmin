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
            // schedule 总是回显生效结构（宿主任务来自清单默认），不是库存覆盖
            self::assertIsArray($item['schedule']);
            self::assertSame('daily', $item['schedule']['freq']);
            self::assertFalse($item['schedule_overridden']);
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
        // 改完时间回显覆盖值并标记被改过
        self::assertIsArray($item['schedule']);
        self::assertSame('daily', $item['schedule']['freq']);
        self::assertSame('04:30', $item['schedule']['time']);
        self::assertTrue($item['schedule_overridden']);
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

    public function testIndexFiltersByStatusAndLastFailed(): void
    {
        $this->http()->index([], 'req-seed');
        $this->http()->update('host.backup', ['status' => 'disabled'], 'req-dis');

        $enabled = self::itemsOf($this->http()->index(['status' => 'enabled'], 'req-f1'));
        self::assertIsArray($enabled);
        self::assertCount(2, $enabled);
        $disabled = self::itemsOf($this->http()->index(['status' => 'disabled'], 'req-f2'));
        self::assertIsArray($disabled);
        self::assertCount(1, $disabled);
        self::assertSame('host.backup', is_array($disabled[0]) ? $disabled[0]['code'] : null);

        // 全部成功时 last_failed 过滤为空；把一条标记成失败再查
        $none = self::itemsOf($this->http()->index(['last_failed' => 'true'], 'req-f3'));
        self::assertIsArray($none);
        self::assertCount(0, $none);
        $this->conn->executeStatement(
            'UPDATE sand_system_schedule SET last_run_time = CURRENT_TIMESTAMP, last_run_succeeded = false WHERE code = ?',
            ['host.log-cleanup'],
        );
        $failed = self::itemsOf($this->http()->index(['last_failed' => 'true'], 'req-f4'));
        self::assertIsArray($failed);
        self::assertCount(1, $failed);
        self::assertSame('host.log-cleanup', is_array($failed[0]) ? $failed[0]['code'] : null);
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

    private function setTimezone(string $name): void
    {
        $this->conn->executeStatement(
            'INSERT INTO sand_system_setting (key, value, group_code) VALUES (\'timezone\', CAST(:value AS jsonb), \'basic\')'
            . ' ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value',
            ['value' => json_encode($name)],
        );
    }

    /** @return array<mixed> */
    private function fetchIndex(string $requestId): array
    {
        $result = $this->http()->index([], $requestId);
        self::assertSame(200, $result['status']);
        $data = self::dataOf($result);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $items
     *
     * @return array<mixed>
     */
    private static function itemOf(array $items, string $code): array
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['code'] ?? null) === $code) {
                return $item;
            }
        }
        self::fail('列表里没有 ' . $code);
    }

    public function testNextRunTimesFollowConfiguredTimezone(): void
    {
        // 「每天 03:00」按系统设置里的时区解释
        $this->setTimezone('America/Los_Angeles');
        $data = $this->fetchIndex('req-tz-1');
        self::assertSame('America/Los_Angeles', $data['timezone']);
        self::assertSame('洛杉矶时间（西八区）', $data['timezone_label']);
        self::assertIsArray($data['items']);
        $backup = self::itemOf($data['items'], 'host.backup');
        self::assertIsArray($backup['next_run_times']);
        self::assertCount(3, $backup['next_run_times']);
        $zone = new \DateTimeZone('America/Los_Angeles');
        foreach ($backup['next_run_times'] as $text) {
            self::assertIsString($text);
            $moment = new \DateTimeImmutable($text);
            // 字符串带的偏移与该时区一致，钟点落在当地 03:00
            self::assertSame($zone->getOffset($moment), $moment->getOffset());
            self::assertSame('03', $moment->format('H'));
            self::assertSame('00', $moment->format('i'));
        }

        $this->setTimezone('Asia/Shanghai');
        $data = $this->fetchIndex('req-tz-2');
        self::assertSame('Asia/Shanghai', $data['timezone']);
        self::assertSame('北京时间（东八区）', $data['timezone_label']);
        self::assertIsArray($data['items']);
        $backup = self::itemOf($data['items'], 'host.backup');
        self::assertIsArray($backup['next_run_times']);
        foreach ($backup['next_run_times'] as $text) {
            self::assertIsString($text);
            self::assertStringEndsWith('+08:00', $text);
            self::assertSame('03', (new \DateTimeImmutable($text))->format('H'));
        }
    }

    public function testPluginTaskNameFallsBackWithoutRawCode(): void
    {
        $this->conn->insert('sand_system_plugin', [
            'code' => 'flow',
            'name' => '流程审批',
            'version' => '1.0.0',
            'host_constraint' => '^1.1',
            'manifest_sha256' => str_repeat('a', 64),
            'state' => 'installed',
        ]);
        $this->conn->insert('sand_system_schedule', [
            'code' => 'flow.daily-sync',
            'plugin_code' => 'flow',
            'enabled' => 'true',
        ]);
        // 插件行都没有的任务：插件名也取不到，兜底「系统任务」，不露 code
        $this->conn->insert('sand_system_schedule', [
            'code' => 'ghost.nightly',
            'plugin_code' => null,
            'enabled' => 'true',
        ]);
        $items = self::itemsOf($this->http()->index([], 'req-names'));
        self::assertIsArray($items);

        $pluginItem = self::itemOf($items, 'flow.daily-sync');
        self::assertSame('流程审批', $pluginItem['name']);
        $description = $pluginItem['description'] ?? null;
        self::assertTrue($description === null || (is_string($description) && !str_contains($description, 'daily-sync')));

        $ghostItem = self::itemOf($items, 'ghost.nightly');
        self::assertSame('系统任务', $ghostItem['name']);
    }
}
