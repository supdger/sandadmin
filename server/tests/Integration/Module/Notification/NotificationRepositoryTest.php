<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Notification;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Notification\NotificationRepository;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Tests\Support\NotificationFixture;

/**
 * 消息中心仓储：未读计数（通知 + 公告范围）、单条与全部已读。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL；
 * 用户/部门按真实表种入，类首尾与每个用例前都清理。
 */
final class NotificationRepositoryTest extends TestCase
{
    private const NOW = '2026-06-01T12:00:00+00:00';

    private static Connection $conn;
    private static int $deptId;
    private static int $otherDeptId;
    private static int $userId;
    private static int $otherUserId;
    private NotificationRepository $inbox;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380000060%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name LIKE 'cknotif%'");
        self::$conn->executeStatement(
            "INSERT INTO sand_system_dept (name, status) VALUES ('cknotif本部', 1), ('cknotif外部', 1)",
        );
        self::$deptId = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name = 'cknotif本部'"));
        self::$otherDeptId = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name = 'cknotif外部'"));
        $insert = "INSERT INTO sand_system_user (phone, nickname, password_hash, dept_id, status) VALUES (:p,:n,'h',:d,1) RETURNING id";
        self::$userId = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000601', 'n' => 'cknotif本人', 'd' => self::$deptId]));
        self::$otherUserId = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000602', 'n' => 'cknotif他人', 'd' => self::$otherDeptId]));
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$conn)) {
            return;
        }
        self::wipe();
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380000060%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name LIKE 'cknotif%'");
        self::$conn->close();
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->inbox = new NotificationRepository(self::$conn);
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement(
            'DELETE FROM sand_system_announcement_read WHERE announcement_id IN (SELECT id FROM sand_system_announcement WHERE title LIKE ?)',
            ['cknotif%'],
        );
        self::$conn->executeStatement(
            'DELETE FROM sand_system_announcement_dept WHERE announcement_id IN (SELECT id FROM sand_system_announcement WHERE title LIKE ?)',
            ['cknotif%'],
        );
        self::$conn->executeStatement("DELETE FROM sand_system_announcement WHERE title LIKE 'cknotif%'");
        self::$conn->executeStatement(
            'DELETE FROM sand_system_notification WHERE user_id IN (?, ?)',
            [self::$userId, self::$otherUserId],
        );
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    private function addDeptLink(int $announcementId, int $deptId): void
    {
        self::$conn->insert('sand_system_announcement_dept', [
            'announcement_id' => $announcementId,
            'dept_id' => $deptId,
        ]);
    }

    private function addRead(int $announcementId, int $userId, string $readTime = '2026-01-02T01:00:00+00:00'): void
    {
        self::$conn->insert('sand_system_announcement_read', [
            'announcement_id' => $announcementId,
            'user_id' => $userId,
            'read_time' => $readTime,
        ]);
    }

    public function testCountUnreadSumsNotificationsAndAnnouncements(): void
    {
        NotificationFixture::insertNotification(self::$conn, self::$userId);
        NotificationFixture::insertNotification(self::$conn, self::$userId);
        NotificationFixture::insertNotification(self::$conn, self::$userId, ['read_time' => '2026-01-02T04:00:00+00:00']);
        NotificationFixture::insertNotification(self::$conn, self::$otherUserId);
        NotificationFixture::insertAnnouncement(self::$conn);

        self::assertSame(3, $this->inbox->countUnread(self::$userId, null, $this->now()));
        self::assertSame(2, $this->inbox->countUnread(self::$otherUserId, null, $this->now()));
    }

    public function testCountUnreadRespectsAnnouncementScope(): void
    {
        $deptOnly = NotificationFixture::insertAnnouncement(self::$conn, ['scope' => 'dept']);
        $this->addDeptLink($deptOnly, self::$deptId);
        NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'cknotif全员公告']);

        self::assertSame(2, $this->inbox->countUnread(self::$userId, self::$deptId, $this->now()), '部门成员看得到 dept 公告');
        self::assertSame(1, $this->inbox->countUnread(self::$userId, self::$otherDeptId, $this->now()), '其他部门只看全员公告');
        self::assertSame(1, $this->inbox->countUnread(self::$userId, null, $this->now()), '无部门按全员口径');
    }

    public function testCountUnreadSkipsDraftExpiredAndRead(): void
    {
        NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'cknotif草稿', 'state' => 'draft']);
        NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'cknotif过期', 'expire_time' => '2026-01-01T00:00:00+00:00']);
        $read = NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'cknotif已读']);
        $this->addRead($read, self::$userId);

        self::assertSame(0, $this->inbox->countUnread(self::$userId, null, $this->now()));
    }

    public function testMarkOneReadStampsReadTime(): void
    {
        $id = NotificationFixture::insertNotification(self::$conn, self::$userId);

        self::assertTrue($this->inbox->markOneRead(self::$userId, $id, $this->now()));
        $readTime = OrgSql::str(self::$conn->fetchOne(
            'SELECT read_time FROM sand_system_notification WHERE id = ?',
            [$id],
        ));
        self::assertStringStartsWith('2026-06-01', $readTime, 'read_time 盖上标记时刻');
    }

    public function testMarkOneReadIsTrueForAlreadyRead(): void
    {
        $id = NotificationFixture::insertNotification(self::$conn, self::$userId, ['read_time' => '2026-01-02T04:00:00+00:00']);

        self::assertTrue($this->inbox->markOneRead(self::$userId, $id, $this->now()), '已读行存在仍算找到');
        self::assertStringStartsWith(
            '2026-01-02 04:00:00',
            OrgSql::str(self::$conn->fetchOne('SELECT read_time FROM sand_system_notification WHERE id = ?', [$id])),
            '已读时间不被覆盖',
        );
    }

    public function testMarkOneReadFalseForMissingOrForeign(): void
    {
        $id = NotificationFixture::insertNotification(self::$conn, self::$otherUserId);

        self::assertFalse($this->inbox->markOneRead(self::$userId, $id, $this->now()), '别人的消息标不了');
        self::assertFalse($this->inbox->markOneRead(self::$userId, 999999999, $this->now()));
    }

    public function testMarkAllReadCoversNotificationsAndAnnouncements(): void
    {
        NotificationFixture::insertNotification(self::$conn, self::$userId);
        NotificationFixture::insertNotification(self::$conn, self::$userId);
        NotificationFixture::insertNotification(self::$conn, self::$otherUserId);
        NotificationFixture::insertAnnouncement(self::$conn);
        $deptOnly = NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'cknotif部门', 'scope' => 'dept']);
        $this->addDeptLink($deptOnly, self::$deptId);
        NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'cknotif过期', 'expire_time' => '2026-01-01T00:00:00+00:00']);

        $this->inbox->markAllRead(self::$userId, self::$deptId, $this->now());

        self::assertSame(0, $this->inbox->countUnread(self::$userId, self::$deptId, $this->now()));
        self::assertSame(
            0,
            OrgSql::int(self::$conn->fetchOne(
                'SELECT COUNT(*) FROM sand_system_notification WHERE user_id = ? AND read_time IS NULL',
                [self::$userId],
            )),
        );
        self::assertSame(
            1,
            OrgSql::int(self::$conn->fetchOne(
                'SELECT COUNT(*) FROM sand_system_notification WHERE user_id = ? AND read_time IS NULL',
                [self::$otherUserId],
            )),
            '别人的未读不受影响',
        );
        self::assertSame(
            2,
            OrgSql::int(self::$conn->fetchOne('SELECT COUNT(*) FROM sand_system_announcement_read WHERE user_id = ?', [self::$userId])),
            '全员 + 本部门公告各记一条已读，过期公告不记',
        );
    }

    public function testMarkAllReadIsIdempotent(): void
    {
        NotificationFixture::insertAnnouncement(self::$conn);

        $this->inbox->markAllRead(self::$userId, null, $this->now());
        $this->inbox->markAllRead(self::$userId, null, $this->now());

        self::assertSame(
            1,
            OrgSql::int(self::$conn->fetchOne('SELECT COUNT(*) FROM sand_system_announcement_read WHERE user_id = ?', [self::$userId])),
            '重复调用靠 ON CONFLICT DO NOTHING 不重复记行',
        );
    }
}
