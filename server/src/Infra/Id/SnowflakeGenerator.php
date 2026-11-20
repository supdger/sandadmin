<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Id;

use Sandadmin\Task\Clock;
use support\Log;

/**
 * 雪花发号器（v4 J-3）：63 位 = 41 位毫秒 + 13 位节点 + 9 位序列，PHP int64 可放下。
 * 序列号只在单个实例内连续，同一发号进程必须共享一个实例；跨进程唯一性
 * 靠节点号按部署分段保证。
 * 时钟回拨小于 max_backwards_ms 时等待时钟追上，达到阈值拒发并告警。
 */
final class SnowflakeGenerator
{
    /** 自定义纪元 2026-01-01T00:00:00Z 的 Unix 毫秒数。 */
    public const EPOCH_MS = 1_767_225_600_000;
    public const NODE_BITS = 13;
    public const SEQUENCE_BITS = 9;
    public const NODE_MAX = (1 << self::NODE_BITS) - 1;
    public const SEQUENCE_MAX = (1 << self::SEQUENCE_BITS) - 1;

    private int $lastMs = -1;
    private int $sequence = 0;
    private readonly \Closure $sleep;
    private readonly \Closure $alarm;

    /**
     * @param int $maxBackwardsMs 回拨拒发阈值（毫秒），必填正整数
     * @param callable(int): void|null $sleep 测试注入，参数为需等待的毫秒数；默认 usleep
     * @param callable(string): void|null $alarm 回拨超阈值告警；默认写 default 日志通道
     *
     * @throws SnowflakeConfigException
     */
    public function __construct(
        private readonly int $nodeId,
        private readonly Clock $clock,
        private readonly int $maxBackwardsMs,
        ?callable $sleep = null,
        ?callable $alarm = null,
    ) {
        if ($nodeId < 0 || $nodeId > self::NODE_MAX) {
            throw new SnowflakeConfigException(sprintf('节点号 %d 超出 0-%d 范围', $nodeId, self::NODE_MAX));
        }
        if ($maxBackwardsMs <= 0) {
            throw new SnowflakeConfigException('回拨阈值 max_clock_backwards_ms 必须是正整数毫秒');
        }
        $this->sleep = $sleep === null ? self::sleepMs(...) : \Closure::fromCallable($sleep);
        $this->alarm = $alarm === null ? self::alarmLog(...) : \Closure::fromCallable($alarm);
    }

    /**
     * 取下一个号；返回 int，对外输出由调用侧 (string) 转换，避免 JS 精度丢失。
     *
     * @throws ClockBackwardsException 时钟回拨达到阈值或早于纪元
     */
    public function next(): int
    {
        $nowMs = $this->millis();
        if ($nowMs < $this->lastMs) {
            $backwardsMs = $this->lastMs - $nowMs;
            if ($backwardsMs >= $this->maxBackwardsMs) {
                ($this->alarm)(sprintf('发号器时钟回拨 %d ms 达到阈值 %d ms，拒绝发号', $backwardsMs, $this->maxBackwardsMs));
                throw new ClockBackwardsException(sprintf('时钟回拨 %d ms 达到阈值 %d ms，拒绝发号', $backwardsMs, $this->maxBackwardsMs));
            }
            $nowMs = $this->waitUntil($this->lastMs);
        }
        if ($nowMs === $this->lastMs) {
            $this->sequence = ($this->sequence + 1) & self::SEQUENCE_MAX;
            if ($this->sequence === 0) {
                $nowMs = $this->waitUntil($this->lastMs + 1);
            }
        } else {
            $this->sequence = 0;
        }
        $this->lastMs = $nowMs;
        $deltaMs = $nowMs - self::EPOCH_MS;
        if ($deltaMs < 0) {
            throw new ClockBackwardsException('当前时钟早于发号器纪元 2026-01-01，拒绝发号');
        }

        return ($deltaMs << (self::NODE_BITS + self::SEQUENCE_BITS))
            | ($this->nodeId << self::SEQUENCE_BITS)
            | $this->sequence;
    }

    /**
     * 反解号里的三个字段，供排障与测试断言。
     *
     * @return array{ms:int,node_id:int,sequence:int} ms 为 Unix 毫秒（非纪元吨）
     */
    public static function decode(int $id): array
    {
        return [
            'ms' => ($id >> (self::NODE_BITS + self::SEQUENCE_BITS)) + self::EPOCH_MS,
            'node_id' => ($id >> self::SEQUENCE_BITS) & self::NODE_MAX,
            'sequence' => $id & self::SEQUENCE_MAX,
        ];
    }

    private function millis(): int
    {
        $now = $this->clock->now();

        return ((int) $now->format('U')) * 1000 + intdiv((int) $now->format('u'), 1000);
    }

    /** 阻塞等时钟走到目标毫秒；测试注入的 sleep 直接推进假时钟。 */
    private function waitUntil(int $targetMs): int
    {
        $nowMs = $this->millis();
        while ($nowMs < $targetMs) {
            ($this->sleep)($targetMs - $nowMs);
            $nowMs = $this->millis();
        }

        return $nowMs;
    }

    private static function sleepMs(int $ms): void
    {
        usleep($ms * 1000);
    }

    private static function alarmLog(string $message): void
    {
        Log::channel('default')->warning($message);
    }
}
