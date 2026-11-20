<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Lifecycle;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Lifecycle\LifecycleActor;
use Sandadmin\Module\Lifecycle\LifecycleHttp;
use Sandadmin\Module\Lifecycle\LifecycleKind;
use Sandadmin\Module\Lifecycle\LifecycleRequest;
use Sandadmin\Module\Lifecycle\LifecycleRepository;
use Sandadmin\Module\Lifecycle\LifecycleStage;
use Sandadmin\Task\TaskState;
use Sandadmin\Tests\Support\Disconnected;

/**
 * IDOR（越权读）四象限样板：GET /lifecycle-requests/{request_id}，
 * 象限口径见 server/docs/module-tests.md「越权（IDOR）测试」一节。
 * Unit 只盖取数前分支与 visibleTo 纯判定；「他人→404」「本人/viewAll→200」
 * 经真实仓储，由 tests/Integration/Module/Lifecycle/LifecycleHttpTest 覆盖。
 */
final class LifecycleIdorTest extends TestCase
{
    private const PUBLIC_ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    /** @return array<string, array{string, bool, string}> 象限名 => [键, 期望可见, 说明] */
    public static function visibilityProvider(): array
    {
        // 「无身份」象限不在此列：actor 为 null 时 show 在取数前 401，
        // 由 testShowRejectsMissingActor 覆盖（visibleTo 本身不接收 null）。
        return [
            '他人资源拒绝' => ['stranger', false, 'requestedBy=9 的申请，userId=8 且无 viewAll'],
            '本人放行' => ['owner', true, 'requestedBy=9 的申请，userId=9'],
            'viewAll 持码放行' => ['view_all', true, 'userId=8 但 viewAll=true（system:plugin:* / system:update:* 等效）'],
        ];
    }

    /** 象限 2/3/4：visibleTo 纯判定。 */
    #[\PHPUnit\Framework\Attributes\DataProvider('visibilityProvider')]
    public function testVisibilityQuadrant(string $quadrant, bool $expected, string $note): void
    {
        $request = $this->requestOf(requestedBy: 9);
        $actor = match ($quadrant) {
            'stranger' => new LifecycleActor(8, false),
            'owner' => new LifecycleActor(9, false),
            'view_all' => new LifecycleActor(8, true),
            default => throw new \LogicException('未知象限 ' . $quadrant),
        };

        self::assertSame($expected, $request->visibleTo($actor), $note);
    }

    /** 象限 1：无身份 → 401，且不触达仓储（Disconnected 触达即 Error）。 */
    public function testShowRejectsMissingActor(): void
    {
        $repository = Disconnected::make(LifecycleRepository::class);

        $result = LifecycleHttp::show($repository, null, self::PUBLIC_ID, 'req-idor-1');

        self::assertSame(401, $result['status']);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $result['body']['code']);
    }

    private function requestOf(int $requestedBy): LifecycleRequest
    {
        return new LifecycleRequest(
            publicId: self::PUBLIC_ID,
            kind: LifecycleKind::PluginInstall,
            targetCode: 'flow',
            source: 'official',
            fromVersion: null,
            toVersion: '1.1.0',
            deletePluginRow: false,
            items: null,
            state: TaskState::Running,
            stage: '正在检查环境',
            stageKey: LifecycleStage::Check,
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
}
