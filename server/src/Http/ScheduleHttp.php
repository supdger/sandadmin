<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Doctrine\DBAL\Connection;
use Sandadmin\Domain\Plugin\ExtensionCatalog;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\ScheduleOverride;
use Sandadmin\Domain\Plugin\ScheduleTitle;
use Sandadmin\Module\Org\OrgException;

/**
 * 定时任务覆盖写。时间结构不合法时返回 422 TASK_SCHEDULE_INVALID（J11），
 * 不落库。库里没有这个任务编码时返回 404。
 */
final class ScheduleHttp
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?ExtensionCatalog $catalog = null,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(string $code, array $body, string $requestId): array
    {
        if ($code === '' || strlen($code) > 128) {
            return ApiOutcome::fail(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['code' => ['任务编码不正确']], $requestId);
        }
        try {
            $override = ScheduleOverride::fromArray($body);
        } catch (InvalidExtensionException $error) {
            $fields = $error->fields;
            foreach (array_keys($fields) as $field) {
                if ($field === 'schedule' || str_starts_with($field, 'schedule.')) {
                    return ApiOutcome::fail(422, 'TASK_SCHEDULE_INVALID', '执行时间填写不正确', ['schedule' => ['执行时间填写不正确']], $requestId);
                }
            }

            return ApiOutcome::fail(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['status' => ['状态不正确']], $requestId);
        }
        $found = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, last_run_time, last_run_succeeded, next_run_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($found === false) {
            return ApiOutcome::fail(404, 'TASK_NOT_FOUND', '要找的内容不存在或已被删除', [], $requestId);
        }
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
        $row = $this->connection->fetchAssociative('SELECT code, plugin_code, enabled, schedule, last_run_time, last_run_succeeded, next_run_time FROM sand_system_schedule WHERE code = ?', [$code]);
        if ($row === false) {
            throw OrgException::invalid([]);
        }

        return ApiOutcome::ok($this->item($row), $requestId);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        $enabled = ($row['enabled'] ?? null) === true || ($row['enabled'] ?? null) === 't' || ($row['enabled'] ?? null) === 1 || ($row['enabled'] ?? null) === '1';
        $raw = $row['schedule'] ?? null;
        $schedule = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $schedule = is_array($decoded) ? $decoded : null;
        } elseif (is_array($raw)) {
            $schedule = $raw;
        }
        $plugin = $row['plugin_code'] ?? null;
        $code = is_string($row['code'] ?? null) ? $row['code'] : '';

        return [
            'code' => $code,
            'name' => ScheduleTitle::of($code, $this->catalog),
            'description' => null,
            'source_label' => $plugin === null ? '宿主' : (is_string($plugin) ? $plugin : null),
            'is_critical' => false,
            'plugin_code' => is_string($plugin) ? $plugin : null,
            'schedule' => $schedule,
            'status' => $enabled ? 'enabled' : 'disabled',
            'schedule_locked' => false,
            'last_run_time' => null,
            'last_run_succeeded' => null,
            'next_run_time' => null,
        ];
    }
}
