<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Tenancy;

/**
 * 分片键与连接名解析（J-1 预留：1.0 恒指向唯一分片与默认连接，
 * 不实现真实分库路由）。
 *
 * 广播约定：给不出分片键的调用方（跨分片统计、全量维护语句等）
 * 必须调 broadcastConnections() 显式声明「广播查询」；
 * 禁止把 null、空串或约定魔法值塞进 shardKeyOf()/connectionNameOf()
 * 冒充广播意图。
 */
interface ShardResolverInterface
{
    /** 1.x 唯一分片键 */
    public const DEFAULT_SHARD_KEY = 'default';

    /** 1.x 唯一连接名 */
    public const DEFAULT_CONNECTION = 'default';

    /** 按请求上下文取分片键；1.0 恒 'default' */
    public function shardKeyOf(RequestContextInterface $ctx): string;

    /**
     * 分片键 → 连接名；只接受实现方认识的真实分片键，
     * 未识别的键按调用方路由错误处理，不静默回落默认连接。
     */
    public function connectionNameOf(string $shardKey): string;

    /**
     * 显式声明广播查询：返回广播要到的全部连接名，
     * 1.0 只有默认连接一个。
     *
     * @return list<string>
     */
    public function broadcastConnections(): array;
}
