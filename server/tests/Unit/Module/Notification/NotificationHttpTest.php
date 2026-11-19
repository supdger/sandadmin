<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Notification;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Announcement\AnnouncementActor;
use Sandadmin\Module\Notification\NotificationException;
use Sandadmin\Module\Notification\NotificationHttp;
use Sandadmin\Module\Notification\NotificationRepository;
use Sandadmin\Tests\Support\Factory\UserFactory;
use Sandadmin\Tests\Support\NotificationFixture;

/**
 * 消息中心 HTTP 装配：鉴权、参数校验与成功信封。
 * index 的分页/未读过滤走 listInbox，其 SQL 含 PG 的 ::text 转换，
 * sqlite 解析不了，本类只覆盖 index 的鉴权前置，行为面由 Integration 覆盖。
 */
final class NotificationHttpTest extends TestCase
{
    private Connection $conn;
    private NotificationRepository $inbox;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        NotificationFixture::createTables($this->conn);
        $this->inbox = new NotificationRepository($this->conn);
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    private function actor(int $userId = 5, ?int $deptId = 3): AnnouncementActor
    {
        $user = UserFactory::make(id: $userId, deptId: $deptId);
        return new AnnouncementActor($user->id, $user->nickname, $user->deptId, ['*'], $user->isSuper);
    }

    public function testUnreadCountWrapsEnvelope(): void
    {
        NotificationFixture::insertNotification($this->conn, 5);
        NotificationFixture::insertAnnouncement($this->conn);

        $result = NotificationHttp::unreadCount($this->inbox, $this->actor(), 'req-1');

        self::assertSame(200, $result['status']);
        self::assertSame(ErrorCodeRegistry::OK, $result['body']['code']);
        $data = $result['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame(2, $data['count']);
        self::assertSame('req-1', $result['body']['request_id']);
    }

    public function testUnreadCountRejectsGuest(): void
    {
        $this->expectException(NotificationException::class);
        NotificationHttp::unreadCount($this->inbox, null, 'req-2');
    }

    public function testIndexRejectsGuest(): void
    {
        try {
            NotificationHttp::index($this->inbox, null, [], 'req-3');
            self::fail('未登录应抛异常');
        } catch (NotificationException $e) {
            self::assertSame(401, $e->status);
            self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $e->errorCode);
        }
        $guest = $this->actor(0);
        try {
            NotificationHttp::index($this->inbox, $guest, [], 'req-3');
            self::fail('user_id 不大于 0 视为未登录');
        } catch (NotificationException $e) {
            self::assertSame(401, $e->status);
        }
    }

    public function testMarkReadStampsRow(): void
    {
        $id = NotificationFixture::insertNotification($this->conn, 5);
        $result = NotificationHttp::markRead($this->inbox, $this->actor(), (string) $id, 'key_12345', 'req-4');

        self::assertSame(200, $result['status']);
        self::assertSame(ErrorCodeRegistry::OK, $result['body']['code']);
        self::assertNotNull(
            $this->conn->fetchOne('SELECT read_time FROM sand_system_notification WHERE id = ?', [$id]),
        );
    }

    public function testMarkReadValidatesId(): void
    {
        foreach (['abc', '0', '1234567890123456789'] as $bad) {
            try {
                NotificationHttp::markRead($this->inbox, $this->actor(), $bad, null, 'req-5');
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
                NotificationHttp::markRead($this->inbox, $this->actor(), '1', $bad, 'req-6');
                self::fail("幂等键 {$bad} 应抛校验异常");
            } catch (NotificationException $e) {
                self::assertSame(400, $e->status);
                self::assertArrayHasKey('idempotency_key', $e->fields);
            }
        }
    }

    public function testMarkReadNotFound(): void
    {
        try {
            NotificationHttp::markRead($this->inbox, $this->actor(), '999', null, 'req-7');
            self::fail('不存在的消息应 404');
        } catch (NotificationException $e) {
            self::assertSame(404, $e->status);
            self::assertSame(ErrorCodeRegistry::NOT_FOUND, $e->errorCode);
        }
    }

    public function testMarkAllReadWrapsEnvelope(): void
    {
        NotificationFixture::insertNotification($this->conn, 5);
        NotificationFixture::insertAnnouncement($this->conn);
        $result = NotificationHttp::markAllRead($this->inbox, $this->actor(), 'key.all_1', 'req-8');

        self::assertSame(200, $result['status']);
        self::assertSame(ErrorCodeRegistry::OK, $result['body']['code']);
        self::assertSame(0, $this->inbox->countUnread(5, 3, new \DateTimeImmutable()));
    }

    public function testMarkAllReadChecksIdempotencyKey(): void
    {
        try {
            NotificationHttp::markAllRead($this->inbox, $this->actor(), '空格 key', 'req-9');
            self::fail('非法幂等键应抛异常');
        } catch (NotificationException $e) {
            self::assertArrayHasKey('idempotency_key', $e->fields);
        }
    }

    public function testFailureEnvelope(): void
    {
        $result = NotificationHttp::failure(403, ErrorCodeRegistry::PERMISSION_DENIED, '没有权限', [], 'req-10');

        self::assertSame(403, $result['status']);
        self::assertSame(ErrorCodeRegistry::PERMISSION_DENIED, $result['body']['code']);
        self::assertSame('没有权限', $result['body']['message']);
        self::assertSame('req-10', $result['body']['request_id']);
    }
}
