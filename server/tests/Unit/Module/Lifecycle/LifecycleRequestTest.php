<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Lifecycle;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Lifecycle\LifecycleActor;
use Sandadmin\Module\Lifecycle\LifecycleKind;
use Sandadmin\Module\Lifecycle\LifecycleRequest;
use Sandadmin\Module\Lifecycle\LifecycleStage;
use Sandadmin\Task\TaskState;

/** 生命周期申请值对象：可见性判定与对外 API 形状。 */
final class LifecycleRequestTest extends TestCase
{
    private const ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    private function request(int $requestedBy = 9, ?LifecycleStage $stageKey = LifecycleStage::Check): LifecycleRequest
    {
        return new LifecycleRequest(
            publicId: self::ID,
            kind: LifecycleKind::PluginInstall,
            targetCode: 'flow',
            source: 'official',
            fromVersion: null,
            toVersion: '1.1.0',
            deletePluginRow: false,
            items: [['code' => 'flow', 'from_version' => '1.0.0', 'to_version' => '1.1.0', 'state' => 'queued']],
            state: TaskState::Running,
            stage: '正在检查环境',
            stageKey: $stageKey,
            progress: 20,
            rolledBack: false,
            errorCode: null,
            errorMessage: null,
            requestedBy: $requestedBy,
            createTime: new \DateTimeImmutable('2026-01-02T03:04:05+00:00'),
            startTime: new \DateTimeImmutable('2026-01-02T03:05:00+00:00'),
            finishTime: null,
        );
    }

    public function testVisibleToOwner(): void
    {
        self::assertTrue($this->request()->visibleTo(new LifecycleActor(9, false)));
    }

    public function testInvisibleToStranger(): void
    {
        self::assertFalse($this->request()->visibleTo(new LifecycleActor(8, false)));
    }

    public function testVisibleToViewAllActor(): void
    {
        self::assertTrue($this->request()->visibleTo(new LifecycleActor(8, true)));
    }

    public function testToApiShape(): void
    {
        $api = $this->request()->toApi();

        self::assertSame(self::ID, $api['id'], '对外编号是 public_id 不是自增 id');
        self::assertSame('plugin_install', $api['kind']);
        self::assertSame('flow', $api['target_code']);
        self::assertSame('official', $api['source']);
        self::assertSame('1.1.0', $api['to_version']);
        self::assertFalse($api['purge_data']);
        self::assertSame('running', $api['state']);
        self::assertSame('check', $api['stage_key']);
        self::assertSame(20, $api['progress']);
        self::assertFalse($api['rolled_back']);
        self::assertSame('2026-01-02T03:04:05+00:00', $api['create_time']);
        self::assertSame('2026-01-02T03:05:00+00:00', $api['start_time']);
        self::assertNull($api['finish_time']);
        self::assertSame(
            [['code' => 'flow', 'from_version' => '1.0.0', 'to_version' => '1.1.0', 'state' => 'queued']],
            $api['items'],
        );
    }

    public function testToApiKeepsCancelledState(): void
    {
        $cancelled = new LifecycleRequest(
            publicId: self::ID,
            kind: LifecycleKind::SystemUpgrade,
            targetCode: 'host',
            source: 'official',
            fromVersion: '1.1.0',
            toVersion: '1.2.0',
            deletePluginRow: false,
            items: null,
            state: TaskState::Cancelled,
            stage: null,
            stageKey: null,
            progress: 40,
            rolledBack: true,
            errorCode: 'CANCELLED',
            errorMessage: '已取消',
            requestedBy: 9,
            createTime: new \DateTimeImmutable('2026-01-02T00:00:00+00:00'),
            startTime: null,
            finishTime: new \DateTimeImmutable('2026-01-02T01:00:00+00:00'),
        );
        $api = $cancelled->toApi();

        self::assertSame('cancelled', $api['state'], 'cancelled 态原样带出');
        self::assertNull($api['stage_key']);
        self::assertNull($api['items']);
        self::assertTrue($api['rolled_back']);
        self::assertSame('CANCELLED', $api['error_code']);
    }
}
