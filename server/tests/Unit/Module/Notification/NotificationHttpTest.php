<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Notification;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Announcement\AnnouncementActor;
use Sandadmin\Module\Notification\NotificationException;
use Sandadmin\Module\Notification\NotificationHttp;
use Sandadmin\Module\Notification\NotificationRepository;
use Sandadmin\Tests\Support\Disconnected;
use Sandadmin\Tests\Support\Factory\UserFactory;

/**
 * 消息中心 HTTP 装配的入库前分支：鉴权与参数校验都在仓储取数之前
 * 抛出，仓储用免构造实例（不建任何库连接，触达即 Error）。
 * 真实取数/写库行为见 Integration NotificationWriteTest 与仓储测试。
 */
final class NotificationHttpTest extends TestCase
{
    private function inbox(): NotificationRepository
    {
        return Disconnected::make(NotificationRepository::class);
    }

    private function actor(int $userId = 5, ?int $deptId = 3): AnnouncementActor
    {
        $user = UserFactory::make(id: $userId, deptId: $deptId);
        return new AnnouncementActor($user->id, $user->nickname, $user->deptId, ['*'], $user->isSuper);
    }

    public function testUnreadCountRejectsGuest(): void
    {
        $this->expectException(NotificationException::class);
        NotificationHttp::unreadCount($this->inbox(), null, 'req-1');
    }

    public function testIndexRejectsGuest(): void
    {
        try {
            NotificationHttp::index($this->inbox(), null, [], 'req-2');
            self::fail('未登录应抛异常');
        } catch (NotificationException $e) {
            self::assertSame(401, $e->status);
            self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $e->errorCode);
        }
        $guest = $this->actor(0);
        try {
            NotificationHttp::index($this->inbox(), $guest, [], 'req-2');
            self::fail('user_id 不大于 0 视为未登录');
        } catch (NotificationException $e) {
            self::assertSame(401, $e->status);
        }
    }

    public function testMarkReadValidatesId(): void
    {
        foreach (['abc', '0', '1234567890123456789'] as $bad) {
            try {
                NotificationHttp::markRead($this->inbox(), $this->actor(), $bad, null, 'req-3');
                self::fail("编号 {$bad} 应抛校验异常");
            } catch (NotificationException $e) {
                self::assertSame(400, $e->status);
                self::assertArrayHasKey('notification_id', $e->fields);
            }
        }
    }

    public function testMarkReadChecksIdempotencyKey(): void
    {
        foreach (['短', 'bad key with space', str_repeat('k', 129)] as $bad) {
            try {
                NotificationHttp::markRead($this->inbox(), $this->actor(), '1', $bad, 'req-4');
                self::fail("幂等键 {$bad} 应抛校验异常");
            } catch (NotificationException $e) {
                self::assertSame(400, $e->status);
                self::assertArrayHasKey('idempotency_key', $e->fields);
            }
        }
    }

    public function testMarkAllReadChecksIdempotencyKey(): void
    {
        try {
            NotificationHttp::markAllRead($this->inbox(), $this->actor(), '空格 key', 'req-5');
            self::fail('非法幂等键应抛异常');
        } catch (NotificationException $e) {
            self::assertArrayHasKey('idempotency_key', $e->fields);
        }
    }

    public function testFailureEnvelope(): void
    {
        $result = NotificationHttp::failure(403, ErrorCodeRegistry::PERMISSION_DENIED, '没有权限', [], 'req-6');

        self::assertSame(403, $result['status']);
        self::assertSame(ErrorCodeRegistry::PERMISSION_DENIED, $result['body']['code']);
        self::assertSame('没有权限', $result['body']['message']);
        self::assertSame('req-6', $result['body']['request_id']);
    }
}
