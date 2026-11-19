<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Notification;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Notification\NotificationRepository;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Tests\Support\NotificationFixture;

/**
 * 消息中心仓储的内存库用例：未读计数、单条与全部已读。
 * 这里跑的查询都是方言中立 SQL；listInbox 的 ::text 转换是 PG 方言，
 * sqlite 解析不了，由 Integration 覆盖，本类不断言。
 */
final class NotificationRepositoryTest extends TestCase
{
    private const NOW = '2026-06-01T12:00:00+00:00';

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

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    public function testCountUnreadSumsNotificationsAndAnnouncements(): void
    {
        NotificationFixture::insertNotification($this->conn, 5);
        NotificationFixture::insertNotification($this->conn, 5);
        NotificationFixture::insertNotification($this->conn, 5, ['read_time' => '2026-01-02T04:00:00+00:00']);
        NotificationFixture::insertNotification($this->conn, 6);
        NotificationFixture::insertAnnouncement($this->conn);

        self::assertSame(3, $this->inbox->countUnread(5, null, $this->now()));
        self::assertSame(2, $this->inbox->countUnread(6, null, $this->now()));
    }

    public function testCountUnreadRespectsAnnouncementScope(): void
    {
        $deptOnly = NotificationFixture::insertAnnouncement($this->conn, ['scope' => 'dept']);
        $this->conn->insert('sand_system_announcement_dept', ['announcement_id' => $deptOnly, 'dept_id' => 3]);
        NotificationFixture::insertAnnouncement($this->conn, ['title' => '全员公告']);

        self::assertSame(2, $this->inbox->countUnread(5, 3, $this->now()), '部门成员看得到 dept 公告');
        self::assertSame(1, $this->inbox->countUnread(5, 4, $this->now()), '其他部门只看全员公告');
        self::assertSame(1, $this->inbox->countUnread(5, null, $this->now()), '无部门按全员口径');
    }

    public function testCountUnreadSkipsDraftExpiredAndRead(): void
    {
        NotificationFixture::insertAnnouncement($this->conn, ['state' => 'draft']);
        NotificationFixture::insertAnnouncement($this->conn, ['expire_time' => '2026-01-01T00:00:00+00:00']);
        $read = NotificationFixture::insertAnnouncement($this->conn);
        $this->conn->insert('sand_system_announcement_read', [
            'announcement_id' => $read,
            'user_id' => 5,
            'read_time' => '2026-01-02T01:00:00+00:00',
        ]);

        self::assertSame(0, $this->inbox->countUnread(5, null, $this->now()));
    }

    public function testMarkOneReadStampsReadTime(): void
    {
        $id = NotificationFixture::insertNotification($this->conn, 5);

        self::assertTrue($this->inbox->markOneRead(5, $id, $this->now()));
        self::assertSame(
            self::NOW,
            $this->conn->fetchOne('SELECT read_time FROM sand_system_notification WHERE id = ?', [$id]),
        );
    }

    public function testMarkOneReadIsTrueForAlreadyRead(): void
    {
        $id = NotificationFixture::insertNotification($this->conn, 5, ['read_time' => '2026-01-02T04:00:00+00:00']);

        self::assertTrue($this->inbox->markOneRead(5, $id, $this->now()), '已读行存在仍算找到');
        self::assertSame(
            '2026-01-02T04:00:00+00:00',
            $this->conn->fetchOne('SELECT read_time FROM sand_system_notification WHERE id = ?', [$id]),
            '已读时间不被覆盖',
        );
    }

    public function testMarkOneReadFalseForMissingOrForeign(): void
    {
        $id = NotificationFixture::insertNotification($this->conn, 6);

        self::assertFalse($this->inbox->markOneRead(5, $id, $this->now()), '别人的消息标不了');
        self::assertFalse($this->inbox->markOneRead(5, 999, $this->now()));
    }

    public function testMarkAllReadCoversNotificationsAndAnnouncements(): void
    {
        NotificationFixture::insertNotification($this->conn, 5);
        NotificationFixture::insertNotification($this->conn, 5);
        NotificationFixture::insertNotification($this->conn, 6);
        NotificationFixture::insertAnnouncement($this->conn);
        $deptOnly = NotificationFixture::insertAnnouncement($this->conn, ['scope' => 'dept']);
        $this->conn->insert('sand_system_announcement_dept', ['announcement_id' => $deptOnly, 'dept_id' => 3]);
        NotificationFixture::insertAnnouncement($this->conn, ['expire_time' => '2026-01-01T00:00:00+00:00']);

        $this->inbox->markAllRead(5, 3, $this->now());

        self::assertSame(0, $this->inbox->countUnread(5, 3, $this->now()));
        self::assertSame(
            0,
            OrgSql::int($this->conn->fetchOne(
                'SELECT COUNT(*) FROM sand_system_notification WHERE user_id = 5 AND read_time IS NULL',
            )),
        );
        self::assertSame(
            1,
            OrgSql::int($this->conn->fetchOne(
                'SELECT COUNT(*) FROM sand_system_notification WHERE user_id = 6 AND read_time IS NULL',
            )),
            '别人的未读不受影响',
        );
        self::assertSame(
            2,
            OrgSql::int($this->conn->fetchOne('SELECT COUNT(*) FROM sand_system_announcement_read WHERE user_id = 5')),
            '全员 + 本部门公告各记一条已读，过期公告不记',
        );
    }

    public function testMarkAllReadIsIdempotent(): void
    {
        NotificationFixture::insertAnnouncement($this->conn);

        $this->inbox->markAllRead(5, null, $this->now());
        $this->inbox->markAllRead(5, null, $this->now());

        self::assertSame(
            1,
            OrgSql::int($this->conn->fetchOne('SELECT COUNT(*) FROM sand_system_announcement_read WHERE user_id = 5')),
            '重复调用靠 ON CONFLICT DO NOTHING 不重复记行',
        );
    }
}
