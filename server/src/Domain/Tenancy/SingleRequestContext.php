<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Tenancy;

/**
 * 单组织部署的请求上下文默认实现。clear() 释放组织引用，
 * 此后 org() 立即报错，避免请求结束后读到残留上下文；
 * traceId 为不可变元数据，收尾日志仍可读。
 */
final class SingleRequestContext implements RequestContextInterface
{
    private ?OrgContextInterface $org;

    public function __construct(OrgContextInterface $org, private readonly string $traceId)
    {
        if ($traceId === '') {
            throw new \InvalidArgumentException('trace_id 不能为空');
        }
        $this->org = $org;
    }

    public function org(): OrgContextInterface
    {
        return $this->org ?? throw new \LogicException('请求上下文已清理');
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function clear(): void
    {
        $this->org = null;
    }
}
