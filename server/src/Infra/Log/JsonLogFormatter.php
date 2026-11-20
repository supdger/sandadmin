<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use Monolog\Formatter\JsonFormatter;

/**
 * 单行 JSON 日志格式：在 Monolog JsonFormatter 基础上做两件归一化——
 * datetime 改名为 time（契约字段名），并把 RequestScope 注入 extra 的
 * request_id/task_id/attempt 提升为顶层字段，供日志平台直接按列检索。
 * 调用方 context 保持在 context 键下原样输出。
 */
final class JsonLogFormatter extends JsonFormatter
{
    /** @var list<string> 由 RequestScope 注入、需要提升到顶层的字段 */
    private const SCOPE_FIELDS = ['request_id', 'task_id', 'attempt'];

    public function __construct()
    {
        // 单行 JSON、不追加 JSON 数组包裹；空 context/extra 直接省略，
        // 保持每行字段最少化，request_id 存在时才出现。
        parent::__construct(self::BATCH_MODE_JSON, true, true);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function format(array $record): string
    {
        if (isset($record['datetime'])) {
            $record['time'] = $record['datetime'];
            unset($record['datetime']);
        }
        $extra = $record['extra'] ?? null;
        if (is_array($extra)) {
            foreach (self::SCOPE_FIELDS as $field) {
                if (array_key_exists($field, $extra)) {
                    $record[$field] = $extra[$field];
                    unset($extra[$field]);
                }
            }
            $record['extra'] = $extra;
        }

        return parent::format($record);
    }
}
