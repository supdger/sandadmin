<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Tenancy;

/**
 * 单组织部署的组织上下文：分片键与前缀恒 'default'；
 * 组织号由构造注入，传 null 表示未启用组织（orgId() 返回 null），
 * 两个分支都要能被测试构造。
 */
final class SingleOrgContext implements OrgContextInterface
{
    /** 单组织部署的固定组织号 */
    public const DEFAULT_ORG_ID = 1;

    public function __construct(private readonly ?int $orgId = self::DEFAULT_ORG_ID)
    {
        if ($this->orgId !== null && $this->orgId < 1) {
            throw new \InvalidArgumentException('组织号必须 ≥ 1');
        }
    }

    public function orgId(): ?int
    {
        return $this->orgId;
    }

    public function shardKey(): string
    {
        return ShardResolverInterface::DEFAULT_SHARD_KEY;
    }

    public function cachePrefix(): string
    {
        return $this->shardKey() . ':';
    }

    public function storagePrefix(): string
    {
        return $this->shardKey() . '/';
    }
}
