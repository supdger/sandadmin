<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Id;

/**
 * 发号器节点配置：解析 config('idgen') 并校验。
 * node_range 是本部署分到的节点号段（闭区间），node_id 是本机发号节点号，
 * 必须落在号段内——拿错配置的进程会和别的部署发出重复的号，启动自检拒启。
 * max_clock_backwards_ms 必填正整数，默认值待技术总监定（v4 J-3 待决项）。
 */
final class SnowflakeConfig
{
    private function __construct(
        public readonly int $nodeId,
        public readonly int $rangeStart,
        public readonly int $rangeEnd,
        public readonly int $maxBackwardsMs,
    ) {
    }

    /**
     * @param array<mixed> $config config('idgen') 原文
     *
     * @throws SnowflakeConfigException 任一必填项缺失或非法
     */
    public static function fromArray(array $config): self
    {
        [$rangeStart, $rangeEnd] = self::parseRange($config['node_range'] ?? null);
        $nodeId = self::intValue($config['node_id'] ?? null, 'node_id');
        $maxBackwardsMs = self::intValue($config['max_clock_backwards_ms'] ?? null, 'max_clock_backwards_ms');
        if ($maxBackwardsMs <= 0) {
            throw new SnowflakeConfigException('idgen.max_clock_backwards_ms 必须是正整数毫秒，默认值定稿前必填');
        }
        if ($nodeId < $rangeStart || $nodeId > $rangeEnd) {
            throw new SnowflakeConfigException(sprintf('节点号 %d 不在本部署号段 %d-%d 内，疑似拿错配置', $nodeId, $rangeStart, $rangeEnd));
        }

        return new self($nodeId, $rangeStart, $rangeEnd, $maxBackwardsMs);
    }

    /**
     * 号段接受数组 [起始, 结束] 或字符串 "起始-结束"（env 只能给字符串）。
     *
     * @return array{0:int,1:int}
     */
    private static function parseRange(mixed $value): array
    {
        if (is_string($value)) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($value), $match) !== 1) {
                throw new SnowflakeConfigException('idgen.node_range 必填，格式 "起始-结束"，如 "0-1023"');
            }
            $value = [(int) $match[1], (int) $match[2]];
        }
        if (!is_array($value) || !isset($value[0], $value[1])) {
            throw new SnowflakeConfigException('idgen.node_range 必填，格式 [起始, 结束] 或 "起始-结束"');
        }
        $start = self::intValue($value[0], 'node_range[0]');
        $end = self::intValue($value[1], 'node_range[1]');
        if ($start > $end) {
            throw new SnowflakeConfigException(sprintf('idgen.node_range 号段倒置：%d-%d', $start, $end));
        }
        if ($end > SnowflakeGenerator::NODE_MAX) {
            throw new SnowflakeConfigException(sprintf('idgen.node_range 结束号 %d 超出节点号上限 %d', $end, SnowflakeGenerator::NODE_MAX));
        }

        return [$start, $end];
    }

    private static function intValue(mixed $value, string $key): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            return (int) trim($value);
        }
        throw new SnowflakeConfigException(sprintf('idgen.%s 必填且必须是 0 或正整数', $key));
    }
}
