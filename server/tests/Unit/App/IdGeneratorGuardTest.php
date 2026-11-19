<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\App;

use app\bootstrap\IdGeneratorGuard;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Id\SnowflakeConfigException;
use Sandadmin\Infra\Id\SnowflakeGenerator;
use Sandadmin\Infra\Id\SnowflakeRuntime;

/**
 * 发号器启动自检：必填项缺失、基值加进程总数超出号段、进程不在布局内
 * 即抛异常（启动期拒绝启动）；通过后各 worker 按进程槽位拿到不同节点号。
 * 失败用例一律传数组形态的 worker 身份，避免触发 haltMaster 杀主进程。
 */
final class IdGeneratorGuardTest extends TestCase
{
    /** @var array<string,array<string,int>> */
    private const PROCESS_CONFIG = [
        'webman' => ['count' => 4],
        'task_worker' => ['count' => 2],
        'log_retention' => ['count' => 1],
    ];

    protected function tearDown(): void
    {
        SnowflakeRuntime::reset();
    }

    /** @return array<string,int|string> */
    private static function idgenConfig(): array
    {
        return [
            'node_range' => '0-1023',
            'node_id' => 7,
            'max_clock_backwards_ms' => 10,
        ];
    }

    public function testWebWorkerMountsBasePlusWorkerId(): void
    {
        IdGeneratorGuard::start(
            ['name' => 'webman', 'id' => 2],
            self::idgenConfig(),
            null,
            self::PROCESS_CONFIG,
        );
        $nodeId = SnowflakeGenerator::decode(SnowflakeRuntime::next())['node_id'];
        self::assertSame(7 + 2, $nodeId);
    }

    public function testTaskWorkerGetsSlotAfterWebWorkers(): void
    {
        IdGeneratorGuard::start(
            ['name' => 'task_worker', 'id' => 1],
            self::idgenConfig(),
            null,
            self::PROCESS_CONFIG,
        );
        $nodeId = SnowflakeGenerator::decode(SnowflakeRuntime::next())['node_id'];
        self::assertSame(7 + 4 + 1, $nodeId);
    }

    public function testDistinctWorkersGetDistinctNodes(): void
    {
        $nodes = [];
        foreach ([['webman', 0], ['webman', 3], ['task_worker', 0], ['task_worker', 1], ['log_retention', 0]] as [$name, $id]) {
            SnowflakeRuntime::reset();
            IdGeneratorGuard::start(
                ['name' => $name, 'id' => $id],
                self::idgenConfig(),
                null,
                self::PROCESS_CONFIG,
            );
            $nodes[] = SnowflakeGenerator::decode(SnowflakeRuntime::next())['node_id'];
        }
        self::assertSame($nodes, array_unique($nodes));
    }

    public function testWorkersBeyondRangeRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/超出号段上限/');
        IdGeneratorGuard::start(
            ['name' => 'webman', 'id' => 0],
            ['node_range' => '0-10', 'node_id' => 5, 'max_clock_backwards_ms' => 10],
            null,
            self::PROCESS_CONFIG,
        );
    }

    public function testNodeOutsideRangeRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/不在本部署号段/');
        IdGeneratorGuard::start(
            ['name' => 'webman', 'id' => 0],
            ['node_range' => '0-1023', 'node_id' => 2000, 'max_clock_backwards_ms' => 10],
            null,
            self::PROCESS_CONFIG,
        );
    }

    public function testUnknownProcessRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/不在发号布局内/');
        IdGeneratorGuard::start(
            ['name' => 'ghost', 'id' => 0],
            self::idgenConfig(),
            null,
            self::PROCESS_CONFIG,
        );
    }

    public function testWorkerIdBeyondSlotRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/越界/');
        IdGeneratorGuard::start(
            ['name' => 'webman', 'id' => 9],
            self::idgenConfig(),
            null,
            self::PROCESS_CONFIG,
        );
    }

    public function testMissingThresholdRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        IdGeneratorGuard::start(
            ['name' => 'webman', 'id' => 0],
            ['node_range' => '0-1023', 'node_id' => 7],
            null,
            self::PROCESS_CONFIG,
        );
    }

    public function testMissingNodeRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        IdGeneratorGuard::start(
            ['name' => 'webman', 'id' => 0],
            ['node_range' => '0-1023', 'max_clock_backwards_ms' => 10],
            null,
            self::PROCESS_CONFIG,
        );
    }
}
