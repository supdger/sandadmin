<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Runtime;

/**
 * 请求或任务执行上下文的唯一取放口（3i §3 Domain/Runtime）。
 * 实现按执行单元隔离：HTTP 请求落在请求作用域，任务落在任务作用域，
 * 以后协程化时换成协程 Context，业务代码不感知。
 * 禁止把当前用户、租户、事务、请求号写进单例属性或静态属性。
 */
interface ContextStoreInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    /** @return list<string> */
    public function keys(): array;

    /**
     * 执行单元收尾时调用：仍有本存储写入的键即视为跨单元残留。
     *
     * @throws ContextResidueException
     */
    public function assertEmpty(): void;
}
