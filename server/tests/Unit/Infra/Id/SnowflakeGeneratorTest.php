<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Id;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Id\ClockBackwardsException;
use Sandadmin\Infra\Id\SnowflakeConfigException;
use Sandadmin\Infra\Id\SnowflakeGenerator;
use Sandadmin\Tests\Support\FixedClock;

/** 雪花发号器：单实例唯一性、同毫秒序列溢出、时钟回拨策略与位布局。 */
final class SnowflakeGeneratorTest extends TestCase
{
    private const NODE = 7;
    private const BACKWARDS_MS = 10;

    /**
     * 默认注入推进假时钟的 sleep：等待逻辑在真实环境是 usleep，
     * 测试里直接把 FixedClock 拨到目标时刻，避免空转。
     *
     * @param callable(string): void|null $alarm
     */
    private function generator(FixedClock $clock, ?callable $alarm = null, int $node = self::NODE): SnowflakeGenerator
    {
        return new SnowflakeGenerator(
            $node,
            $clock,
            self::BACKWARDS_MS,
            static function (int $ms) use ($clock): void {
                $clock->move($clock->now()->modify("+{$ms} milliseconds"));
            },
            $alarm,
        );
    }

    private static function millis(\DateTimeImmutable $now): int
    {
        return ((int) $now->format('U')) * 1000 + intdiv((int) $now->format('u'), 1000);
    }

    public function testNextIdsUniqueInOneProcess(): void
    {
        $clock = new FixedClock('2026-06-01 00:00:00');
        $gen = $this->generator($clock);
        $ids = [];
        for ($i = 0; $i < 20000; $i++) {
            if ($i > 0 && $i % 400 === 0) {
                $clock->move($clock->now()->modify('+1 millisecond'));
            }
            $ids[] = $gen->next();
        }
        self::assertCount(20000, $ids);
        self::assertSame(20000, count(array_unique($ids)));
    }

    public function testSequenceOverflowWaitsNextMillisecond(): void
    {
        $clock = new FixedClock('2026-06-01 00:00:00');
        $gen = $this->generator($clock);
        $startMs = self::millis($clock->now());
        $ids = [];
        for ($i = 0; $i < SnowflakeGenerator::SEQUENCE_MAX + 2; $i++) {
            $ids[] = $gen->next();
        }
        for ($i = 0; $i <= SnowflakeGenerator::SEQUENCE_MAX; $i++) {
            $decoded = SnowflakeGenerator::decode($ids[$i]);
            self::assertSame($startMs, $decoded['ms']);
            self::assertSame($i, $decoded['sequence']);
        }
        $last = SnowflakeGenerator::decode($ids[SnowflakeGenerator::SEQUENCE_MAX + 1]);
        self::assertSame($startMs + 1, $last['ms']);
        self::assertSame(0, $last['sequence']);
        self::assertSame(SnowflakeGenerator::SEQUENCE_MAX + 2, count(array_unique($ids)));
    }

    public function testBackwardsBelowThresholdWaitsForCatchUp(): void
    {
        $clock = new FixedClock('2026-06-01 00:00:00');
        $alarms = [];
        $gen = $this->generator($clock, static function (string $message) use (&$alarms): void {
            $alarms[] = $message;
        });
        $firstMs = self::millis($clock->now());
        $gen->next();
        $clock->move($clock->now()->modify('-3 milliseconds'));
        $decoded = SnowflakeGenerator::decode($gen->next());
        self::assertSame($firstMs, $decoded['ms']);
        self::assertSame(1, $decoded['sequence']);
        self::assertSame([], $alarms);
    }

    public function testBackwardsAtThresholdRejectsAndAlarms(): void
    {
        $clock = new FixedClock('2026-06-01 00:00:00');
        $alarms = [];
        $gen = $this->generator($clock, static function (string $message) use (&$alarms): void {
            $alarms[] = $message;
        });
        $gen->next();
        $clock->move($clock->now()->modify('-10 milliseconds'));
        try {
            $gen->next();
            self::fail('回拨达到阈值应拒绝发号');
        } catch (ClockBackwardsException $error) {
            self::assertStringContainsString('回拨', $error->getMessage());
        }
        self::assertCount(1, $alarms);
        self::assertStringContainsString('回拨', $alarms[0]);
    }

    public function testBitLayoutMatchesFields(): void
    {
        $clock = new FixedClock('2026-06-01 12:34:56.789000');
        $gen = $this->generator($clock, null, 513);
        $id = $gen->next();
        $decoded = SnowflakeGenerator::decode($id);
        self::assertSame(self::millis($clock->now()), $decoded['ms']);
        self::assertSame(513, $decoded['node_id']);
        self::assertSame(0, $decoded['sequence']);
        self::assertGreaterThan(0, $id);
        self::assertLessThan(2 ** 63, $id);
        self::assertSame(1, SnowflakeGenerator::decode($gen->next())['sequence']);
    }

    public function testInvalidNodeRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        new SnowflakeGenerator(SnowflakeGenerator::NODE_MAX + 1, new FixedClock(), self::BACKWARDS_MS);
    }
}
