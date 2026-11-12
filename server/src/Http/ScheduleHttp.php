<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Doctrine\DBAL\Connection;
use Sandadmin\Domain\Plugin\ExtensionCatalog;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\HostSchedule;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\ScheduleDefinition;
use Sandadmin\Domain\Plugin\ScheduleNextRun;
use Sandadmin\Domain\Plugin\ScheduleOverride;
use Sandadmin\Domain\Plugin\ScheduleTitle;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/**
 * J11 定时任务：列表、改时间/启停、立即执行、执行记录。
 * 名字、说明、默认时间、是否重要、是否锁定来自清单（宿主写死在
 * HostSchedule，插件取签名清单）；库里 sand_system_schedule 只存启停
 * 与 schedule 覆盖。时间结构不合法返回 422 TASK_SCHEDULE_INVALID 且
 * fields 指明字段；清单锁定改时间返回 409 TASK_SCHEDULE_LOCKED；
 * 库里没有该编码返回 404 TASK_NOT_FOUND。
 */
final class ScheduleHttp
{
    private const MAX_PAGE_SIZE = 100;
    private const PREVIEW_COUNT = 3;

    private readonly OrgAudit $audit;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?ExtensionCatalog $catalog = null,
    ) {
        $this->audit = new OrgAudit($connection);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(array $query, string $requestId): array
    {
        $this->seedHost();
        $keyword = self::text($query['keyword'] ?? null);
        $status = self::text($query['status'] ?? null);
        $onlyFailed = self::truthy($query['last_failed'] ?? null);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.code, s.plugin_code, s.enabled, s.schedule, s.last_run_time,'
            . ' s.last_run_succeeded, s.next_run_time, s.suspend_time, p.name AS plugin_name'
            . ' FROM sand_system_schedule s'
            . ' LEFT JOIN sand_system_plugin p ON p.code = s.plugin_code'
            . ' ORDER BY s.code',
        );
        $items = [];
        foreach ($rows as $row) {
            $item = $this->item($row);
            $nameText = is_string($item['name']) ? $item['name'] : '';
            $codeText = is_string($item['code']) ? $item['code'] : '';
            if ($keyword !== null
                && mb_stripos($nameText, $keyword) === false
                && mb_stripos($codeText, $keyword) === false) {
                continue;
            }
            if ($status !== null && $item['status'] !== $status) {
                continue;
            }
            if ($onlyFailed && $item['last_run_succeeded'] !== false) {
                continue;
            }
            $items[] = $item;
        }

        return ApiOutcome::ok(['items' => $items], $requestId);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(string $code, array $body, string $requestId, ?OrgActor $actor = null, ?string $ip = null, ?string $device = null): array
    {
        if ($code === '' || strlen($code) > 128) {
            return ApiOutcome::fail(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['code' => ['任务编码不正确']], $requestId);
        }
        try {
            $override = ScheduleOverride::fromArray($body);
        } catch (InvalidExtensionException $error) {
            return $this->scheduleError($error, $requestId);
        }
        $found = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, last_run_time, last_run_succeeded, next_run_time, suspend_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($found === false) {
            return ApiOutcome::fail(404, 'TASK_NOT_FOUND', '要找的内容不存在或已被删除', [], $requestId);
        }
        $meta = $this->meta($code, self::str($found['plugin_code'] ?? null));
        if (array_key_exists('schedule', $body) && $meta['schedule_locked']) {
            return ApiOutcome::fail(409, 'TASK_SCHEDULE_LOCKED', '这个任务的时间是系统定好的，不能改', [], $requestId);
        }

        $wasEnabled = self::truthy($found['enabled'] ?? null);
        if ($override->enabled !== null) {
            $this->connection->executeStatement(
                'UPDATE sand_system_schedule SET enabled = :enabled, update_time = CURRENT_TIMESTAMP WHERE code = :code',
                ['enabled' => $override->enabled ? 'true' : 'false', 'code' => $code],
            );
        }
        if (array_key_exists('schedule', $body)) {
            if ($override->schedule === null) {
                $this->connection->executeStatement(
                    'UPDATE sand_system_schedule SET schedule = NULL, update_time = CURRENT_TIMESTAMP WHERE code = :code',
                    ['code' => $code],
                );
            } else {
                $encoded = json_encode($override->schedule, JSON_UNESCAPED_UNICODE);
                $this->connection->executeStatement(
                    'UPDATE sand_system_schedule SET schedule = CAST(:schedule AS jsonb), update_time = CURRENT_TIMESTAMP WHERE code = :code',
                    ['schedule' => is_string($encoded) ? $encoded : '{}', 'code' => $code],
                );
            }
        }

        // 启停或时间覆盖变化后重算下次执行时间，保持调度认领与页面「下次」一致
        $row = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, last_run_time, last_run_succeeded, next_run_time, suspend_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($row === false) {
            throw OrgException::invalid([]);
        }
        $this->refreshNextRun($row, $meta);
        $row = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, last_run_time, last_run_succeeded, next_run_time, suspend_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($row === false) {
            throw OrgException::invalid([]);
        }

        if ($actor !== null) {
            $nowEnabled = self::truthy($row['enabled'] ?? null);
            $name = $this->nameOf($code);
            $actionText = match (true) {
                $wasEnabled !== $nowEnabled && $override->enabled !== null && !array_key_exists('schedule', $body) =>
                    $nowEnabled ? '启用了定时任务「' . $name . '」' : '暂停了定时任务「' . $name . '」',
                array_key_exists('schedule', $body) && $wasEnabled !== $nowEnabled => '修改了定时任务「' . $name . '」的执行时间和启停',
                default => '修改了定时任务「' . $name . '」的执行时间',
            };
            $this->audit->record($actor, 'schedule', 'update', $actionText, 'schedule', $code, $name, $requestId, $ip, $device, [
                'schedule' => $this->scheduleOf($row),
                'status' => $nowEnabled ? 'enabled' : 'disabled',
            ]);
        }

        return ApiOutcome::ok($this->item($row), $requestId);
    }

    /**
     * 立即执行一次：写一条 trigger=manual 的执行记录并同步标完成。
     * 当前宿主任务没有可调用的处理类，记录即为执行结果；真实执行器
     * 接入后这里改为写 running 记录交由调度认领。
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function run(string $code, string $requestId, ?OrgActor $actor = null, ?string $ip = null, ?string $device = null): array
    {
        if ($code === '' || strlen($code) > 128) {
            return ApiOutcome::fail(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['code' => ['任务编码不正确']], $requestId);
        }
        $row = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, suspend_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($row === false) {
            return ApiOutcome::fail(404, 'TASK_NOT_FOUND', '要找的内容不存在或已被删除', [], $requestId);
        }
        $runId = $this->connection->fetchOne(
            'INSERT INTO sand_system_schedule_run (schedule_code, trigger, start_time, finish_time, succeeded, request_id)'
            . ' VALUES (:code, \'manual\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, true, :request_id) RETURNING id',
            ['code' => $code, 'request_id' => mb_substr($requestId, 0, 64)],
        );
        $this->connection->executeStatement(
            'UPDATE sand_system_schedule SET last_run_time = CURRENT_TIMESTAMP, last_run_succeeded = true, update_time = CURRENT_TIMESTAMP WHERE code = :code',
            ['code' => $code],
        );
        $row = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, suspend_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($row !== false) {
            $this->refreshNextRun($row, $this->meta($code, self::str($row['plugin_code'] ?? null)));
        }
        if ($actor !== null) {
            $name = $this->nameOf($code);
            $this->audit->record($actor, 'schedule', 'run', '手动执行了定时任务「' . $name . '」', 'schedule', $code, $name, $requestId, $ip, $device);
        }

        return ApiOutcome::ok(['schedule_run_id' => OrgSql::int($runId)], $requestId);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function runs(string $code, array $query, string $requestId): array
    {
        if ($code === '' || strlen($code) > 128) {
            return ApiOutcome::fail(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['code' => ['任务编码不正确']], $requestId);
        }
        $exists = $this->connection->fetchOne('SELECT 1 FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($exists === false) {
            return ApiOutcome::fail(404, 'TASK_NOT_FOUND', '要找的内容不存在或已被删除', [], $requestId);
        }
        $page = max(1, is_numeric($query['page'] ?? null) ? (int) $query['page'] : 1);
        $size = min(self::MAX_PAGE_SIZE, max(1, is_numeric($query['page_size'] ?? null) ? (int) $query['page_size'] : 20));
        $total = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_schedule_run WHERE schedule_code = ?',
            [$code],
        ));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, schedule_code, trigger, start_time, finish_time, succeeded, error_message, error_code, request_id'
            . ' FROM sand_system_schedule_run WHERE schedule_code = :code'
            . ' ORDER BY start_time DESC, id DESC LIMIT :limit OFFSET :offset',
            ['code' => $code, 'limit' => $size, 'offset' => ($page - 1) * $size],
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->runItem($row);
        }

        return ApiOutcome::ok(['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $size], $requestId);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function runItem(array $row): array
    {
        $finished = ($row['finish_time'] ?? null) !== null;
        $succeeded = ($row['succeeded'] ?? null) === true || ($row['succeeded'] ?? null) === 't' || ($row['succeeded'] ?? null) === 1;
        $status = !$finished ? 'running' : ($succeeded ? 'succeeded' : 'failed');
        $duration = null;
        $start = self::str($row['start_time'] ?? null);
        $finish = self::str($row['finish_time'] ?? null);
        if ($start !== null && $finish !== null) {
            $duration = max(0, (int) round((strtotime($finish) - strtotime($start)) * 1000));
        }

        return [
            'id' => OrgSql::int($row['id'] ?? 0),
            'schedule_code' => self::str($row['schedule_code'] ?? null),
            'trigger' => self::str($row['trigger'] ?? null),
            'status' => $status,
            'duration_ms' => $duration,
            'message' => ($row['error_message'] ?? null) === null ? null : self::str($row['error_message']),
            'error_code' => ($row['error_code'] ?? null) === null ? null : self::str($row['error_code']),
            'request_id' => ($row['request_id'] ?? null) === null ? null : self::str($row['request_id']),
            'start_time' => self::atom($row['start_time'] ?? null),
            'finish_time' => self::atom($row['finish_time'] ?? null),
        ];
    }

    /**
     * 清单元数据：宿主任务取 HostSchedule，插件任务取签名清单的
     * CronTask 草稿（description/schedule_locked/is_critical/默认时间）。
     *
     * @return array{description: ?string, schedule_locked: bool, is_critical: bool, schedule: ?ScheduleDefinition, source_label: string}
     */
    private function meta(string $code, ?string $pluginCode): array
    {
        $host = HostSchedule::find($code);
        if ($host !== null) {
            return [
                'description' => $host->description,
                'schedule_locked' => false,
                'is_critical' => $host->isCritical,
                'schedule' => $host->schedule,
                'source_label' => '系统',
            ];
        }
        $label = $pluginCode ?? $this->pluginName($code);
        if ($pluginCode !== null && $this->catalog !== null) {
            $dot = strpos($code, '.');
            if ($dot !== false) {
                $key = substr($code, $dot + 1);
                $plugin = $this->catalog->plugin($pluginCode);
                if ($plugin !== null) {
                    foreach ($plugin->extensions as $draft) {
                        if ($draft->kind === ExtensionKind::CronTask && $draft->key === $key) {
                            return [
                                'description' => $draft->description !== '' ? $draft->description : null,
                                'schedule_locked' => $draft->scheduleLocked,
                                'is_critical' => $draft->isCritical,
                                'schedule' => $draft->schedule,
                                'source_label' => $label ?? $pluginCode,
                            ];
                        }
                    }
                }
            }
        }

        return [
            'description' => null,
            'schedule_locked' => false,
            'is_critical' => false,
            'schedule' => null,
            'source_label' => $pluginCode === null ? '系统' : ($label ?? $pluginCode),
        ];
    }

    private function pluginName(string $code): ?string
    {
        $dot = strpos($code, '.');
        if ($dot === false) {
            return null;
        }
        $name = $this->connection->fetchOne(
            'SELECT name FROM sand_system_plugin WHERE code = ?',
            [substr($code, 0, $dot)],
        );

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * 下次执行时间回写：启用且有有效时间结构时算最近一个，其余清空。
     *
     * @param array<string, mixed>                        $row
     * @param array{schedule: ?ScheduleDefinition} $meta
     */
    private function refreshNextRun(array $row, array $meta): void
    {
        $enabled = self::truthy($row['enabled'] ?? null);
        $suspended = ($row['suspend_time'] ?? null) !== null;
        $schedule = $this->scheduleOf($row) ?? $meta['schedule'];
        $next = null;
        if ($enabled && !$suspended && $schedule !== null) {
            $next = ScheduleNextRun::next($schedule, new \DateTimeImmutable());
        }
        $this->connection->executeStatement(
            'UPDATE sand_system_schedule SET next_run_time = :next, update_time = CURRENT_TIMESTAMP WHERE code = :code',
            [
                'next' => $next === null ? null : $next->format('Y-m-d H:i:sP'),
                'code' => $row['code'] ?? '',
            ],
        );
    }

    /** 宿主任务行兜底：装系统时未播种的话，列表时补出三条宿主任务。 */
    private function seedHost(): void
    {
        foreach (HostSchedule::all() as $host) {
            $next = ScheduleNextRun::next($host->schedule, new \DateTimeImmutable());
            $this->connection->executeStatement(
                'INSERT INTO sand_system_schedule (code, enabled, schedule, next_run_time)'
                . ' VALUES (:code, true, NULL, :next) ON CONFLICT (code) DO NOTHING',
                ['code' => $host->code, 'next' => $next->format('Y-m-d H:i:sP')],
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        $code = self::str($row['code'] ?? null) ?? '';
        $pluginCode = ($row['plugin_code'] ?? null) === null ? null : self::str($row['plugin_code']);
        $meta = $this->meta($code, $pluginCode);
        $enabled = self::truthy($row['enabled'] ?? null);
        $suspended = ($row['suspend_time'] ?? null) !== null;
        $override = $this->scheduleOf($row);
        $effective = $override ?? $meta['schedule'];
        $nextTimes = [];
        if ($enabled && !$suspended && $effective !== null) {
            $cursor = new \DateTimeImmutable();
            for ($i = 0; $i < self::PREVIEW_COUNT; $i++) {
                $next = ScheduleNextRun::next($effective, $cursor);
                $nextTimes[] = $next->format(\DateTimeInterface::ATOM);
                $cursor = $next->modify('+1 second');
            }
        }
        $pluginName = self::str($row['plugin_name'] ?? null) ?? $this->pluginName($code);

        return [
            'code' => $code,
            'name' => ScheduleTitle::of($code, $this->catalog),
            'description' => $meta['description'],
            'source_label' => $pluginName !== null && $pluginName !== '' ? $pluginName : $meta['source_label'],
            'is_critical' => $meta['is_critical'],
            'plugin_code' => $pluginCode,
            'schedule' => $override?->jsonSerialize(),
            'status' => $enabled ? 'enabled' : 'disabled',
            'schedule_locked' => $meta['schedule_locked'],
            'last_run_time' => self::atom($row['last_run_time'] ?? null),
            'last_run_succeeded' => ($row['last_run_succeeded'] ?? null) === null ? null : self::truthy($row['last_run_succeeded']),
            'next_run_time' => $nextTimes === [] ? null : $nextTimes[0],
            'next_run_times' => $nextTimes,
        ];
    }

    /**
     * 库里的时间覆盖；没有或不合法返回 null。
     *
     * @param array<string, mixed> $row
     */
    private function scheduleOf(array $row): ?ScheduleDefinition
    {
        $raw = $row['schedule'] ?? null;
        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }
        if (!is_array($decoded) || $decoded === []) {
            return null;
        }
        try {
            return ScheduleDefinition::fromArray($decoded);
        } catch (InvalidExtensionException) {
            return null;
        }
    }

    private function nameOf(string $code): string
    {
        return ScheduleTitle::of($code, $this->catalog);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function scheduleError(InvalidExtensionException $error, string $requestId): array
    {
        $fields = [];
        foreach ($error->fields as $field => $rule) {
            [$key, $message] = $this->scheduleFieldError($field, $rule);
            $existing = $fields[$key] ?? [];
            $existing[] = $message;
            $fields[$key] = $existing;
        }
        if ($fields === []) {
            $fields = ['schedule' => ['执行时间填写不正确']];
        }
        foreach (array_keys($fields) as $field) {
            if ($field === 'schedule' || str_starts_with($field, 'schedule.') || in_array($field, ['freq', 'interval', 'time', 'weekday', 'monthday'], true)) {
                return ApiOutcome::fail(422, 'TASK_SCHEDULE_INVALID', '执行时间填写不正确', $fields, $requestId);
            }
        }

        return ApiOutcome::fail(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
    }

    /** @return array{0: string, 1: string} fields 键与中文提示；键去掉 schedule. 前缀对齐弹窗字段名 */
    private function scheduleFieldError(string $field, string $rule): array
    {
        $short = str_starts_with($field, 'schedule.') ? substr($field, 9) : $field;

        return match (true) {
            $short === 'interval' => ['interval', '间隔只能填 5 到 60 分钟'],
            $short === 'time' => ['time', '时刻按 09:30 这样的写法填'],
            $short === 'weekday' => ['weekday', '星期只能选 1 到 7'],
            $short === 'monthday' => ['monthday', '日期只能选 1 到 31'],
            $short === 'freq' => ['freq', '频率只能选每隔几分钟、每小时、每天、每周或每月'],
            $short === 'status' => ['status', '状态只能选启用或暂停'],
            $short === 'schedule' => ['schedule', '这种频率下这些字段填法不对'],
            str_starts_with($short, '.') || $short === '' => ['schedule', '时间结构里有不认识的字段'],
            default => [$short, '执行时间填写不正确'],
        };
    }

    private static function text(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function str(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1' || $value === 'true';
    }

    private static function atom(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
    }
}
