<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use Webman\Context;

/**
 * 请求/任务级日志上下文：把 request_id（任务执行时再带 task_id、attempt）
 * 存进 Webman\Context——webman 按请求 reset、结束 destroy；无协程运行时
 * 退化为进程内单槽，任务侧由 TaskExecutor 在 finally 中 clear()，
 * 不跨任务泄漏。本类不持有可变静态属性，满足 ResidentState 约束。
 *
 * Monolog processor（__invoke 并入 record.extra）与执行侧 fields()
 * 共用一份存储；CLI、task_worker 无请求上下文时 fields() 为空。
 */
final class RequestScope
{
    private const CONTEXT_KEY = 'sandadmin.log_context';

    /**
     * 绑定当前执行单元的日志字段；null 与空串一律不写入。
     * HTTP 请求由 RequestContextStore::ensure 在拿到 request_id 时调用；
     * 任务由 TaskExecutor 在认领任务后调用，执行结束必须 clear()。
     */
    public function bind(?string $requestId = null, ?string $taskId = null, ?int $attempt = null): void
    {
        $fields = [];
        if (is_string($requestId) && $requestId !== '') {
            $fields['request_id'] = $requestId;
        }
        if (is_string($taskId) && $taskId !== '') {
            $fields['task_id'] = $taskId;
        }
        if ($attempt !== null) {
            $fields['attempt'] = $attempt;
        }
        Context::set(self::CONTEXT_KEY, $fields);
    }

    /** 执行单元结束后清空，防止字段串到下一个请求/任务。 */
    public function clear(): void
    {
        Context::set(self::CONTEXT_KEY, []);
    }

    /**
     * 当前绑定的日志字段；无绑定时返回空数组。
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $value = Context::get(self::CONTEXT_KEY);
        if (!is_array($value)) {
            return [];
        }
        /** @var array<string, mixed> $value */

        return $value;
    }

    /**
     * Monolog processor：config/log.php 各通道 processors 注册本类实例。
     * 作用域字段优先于调用方 extra 中的同名键，保证串联编号不被覆盖。
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function __invoke(array $record): array
    {
        $fields = $this->fields();
        if ($fields === []) {
            return $record;
        }
        $extra = $record['extra'] ?? [];
        $record['extra'] = is_array($extra) ? $fields + $extra : $fields;

        return $record;
    }
}
