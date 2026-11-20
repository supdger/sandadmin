<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Id;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Id\SnowflakeConfigException;
use Sandadmin\Infra\Id\SnowflakeNodePlan;

/** 发号节点布局：进程槽位顺序、worker 序号解析与越界拒分。 */
final class SnowflakeNodePlanTest extends TestCase
{
    public function testSlotsFollowProcessConfigOrder(): void
    {
        $plan = SnowflakeNodePlan::fromConfigs([
            'webman' => ['count' => 4],
            'monitor' => [],
            'task_worker' => ['count' => 2],
        ]);
        self::assertSame(7, $plan->workerTotal);
        self::assertSame(10, $plan->resolveNodeId(10, 'webman', 0));
        self::assertSame(13, $plan->resolveNodeId(10, 'webman', 3));
        self::assertSame(14, $plan->resolveNodeId(10, 'monitor', 0));
        self::assertSame(15, $plan->resolveNodeId(10, 'task_worker', 0));
        self::assertSame(16, $plan->resolveNodeId(10, 'task_worker', 1));
    }

    public function testDisabledProcessTakesNoSlot(): void
    {
        $plan = SnowflakeNodePlan::fromConfigs([
            'webman' => ['count' => 2],
            'monitor' => ['enable' => false],
            'task_worker' => ['count' => 1],
        ]);
        self::assertSame(3, $plan->workerTotal);
        self::assertSame(2, $plan->resolveNodeId(0, 'task_worker', 0));
    }

    public function testPluginProcessesGetTrailingSlots(): void
    {
        $plan = SnowflakeNodePlan::fromConfigs(
            ['webman' => ['count' => 1]],
            [
                'sandadmin' => ['process' => ['task' => ['count' => 1]]],
                'webman' => ['channel' => ['process' => ['server' => ['count' => 1]]]],
            ],
        );
        self::assertSame(3, $plan->workerTotal);
        self::assertSame(1, $plan->resolveNodeId(0, 'plugin.sandadmin.task', 0));
        self::assertSame(2, $plan->resolveNodeId(0, 'plugin.webman.channel.server', 0));
    }

    public function testUnknownProcessRejected(): void
    {
        $plan = SnowflakeNodePlan::fromConfigs(['webman' => ['count' => 1]]);
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/不在发号布局内/');
        $plan->resolveNodeId(0, 'ghost', 0);
    }

    public function testWorkerIdBeyondCountRejected(): void
    {
        $plan = SnowflakeNodePlan::fromConfigs(['webman' => ['count' => 2]]);
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/越界/');
        $plan->resolveNodeId(0, 'webman', 2);
    }
}
