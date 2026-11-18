<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Tenancy;

/**
 * 单请求的组织上下文入口（J-1 预留：1.x 单租户恒返回固定值，
 * 只保证将来多租户版本不用改调用形状，不是多租户能力）。
 * 与 Domain\Auth\RequestContext 是两个东西：后者管本请求身份与
 * request_id，本接口管组织/分片与请求级清理。每请求新建、随请求对象传递，
 * 禁止放进进程级可变状态。
 */
interface RequestContextInterface
{
    public function org(): OrgContextInterface;

    /** 链路追踪标识，与日志里的 trace_id 同源 */
    public function traceId(): string;

    /**
     * 请求结束由中间件 finally 统一调用；调用后组织上下文不可再读，
     * 实现方应让越期访问立即报错，不允许返回上一条请求的残留值。
     */
    public function clear(): void;
}
