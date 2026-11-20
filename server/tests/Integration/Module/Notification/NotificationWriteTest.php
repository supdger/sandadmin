<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Notification;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Announcement\AnnouncementActor;
use Sandadmin\Module\Notification\NotificationHttp;
use Sandadmin\Module\Notification\NotificationRepository;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Tests\Support\NotificationFixture;

/**
 * 消息中心写路径：单条已读盖时间戳、全部已读清空未读数。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL。
 */
final class NotificationWriteTest extends TestCase
{
    private static Connection $conn;
    private static int $deptId;
    private static int $userId;
    private NotificationRepository $inbox;
    private AnnouncementActor $actor;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380000070%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name = 'ckwrite部'");
        self::$conn->executeStatement("INSERT INTO sand_system_dept (name, status) VALUES ('ckwrite部', 1)");
        self::$deptId = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name = 'ckwrite部'"));
        self::$userId = self::toInt(self::$conn->fetchOne(
            "INSERT INTO sand_system_user (phone, nickname, password_hash, dept_id, status) VALUES (:p,:n,'h',:d,1) RETURNING id",
            ['p' => '13800000701', 'n' => 'ckwrite本人', 'd' => self::$deptId],
        ));
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$conn)) {
            return;
        }
        self::wipe();
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380000070%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name = 'ckwrite部'");
        self::$conn->close();
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->inbox = new NotificationRepository(self::$conn);
        $this->actor = new AnnouncementActor(self::$userId, 'ckwrite本人', self::$deptId, ['*'], true);
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement(
            'DELETE FROM sand_system_announcement_read WHERE announcement_id IN (SELECT id FROM sand_system_announcement WHERE title LIKE ?)',
            ['ckwrite%'],
        );
        self::$conn->executeStatement(
            'DELETE FROM sand_system_announcement_dept WHERE announcement_id IN (SELECT id FROM sand_system_announcement WHERE title LIKE ?)',
            ['ckwrite%'],
        );
        self::$conn->executeStatement("DELETE FROM sand_system_announcement WHERE title LIKE 'ckwrite%'");
        self::$conn->executeStatement('DELETE FROM sand_system_notification WHERE user_id = ?', [self::$userId]);
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public function testMarkReadStampsRowAndWrapsEnvelope(): void
    {
        $id = NotificationFixture::insertNotification(self::$conn, self::$userId);
        $result = NotificationHttp::markRead($this->inbox, $this->actor, (string) $id, 'key_12345', 'ckwrite_req1');

        self::assertSame(200, $result['status']);
        self::assertSame(ErrorCodeRegistry::OK, $result['body']['code']);
        self::assertSame('ckwrite_req1', $result['body']['request_id']);
        self::assertNotNull(
            self::$conn->fetchOne('SELECT read_time FROM sand_system_notification WHERE id = ?', [$id]),
        );
    }

    public function testMarkAllReadClearsUnread(): void
    {
        NotificationFixture::insertNotification(self::$conn, self::$userId);
        NotificationFixture::insertAnnouncement(self::$conn, ['title' => 'ckwrite公告']);
        self::assertSame(2, $this->inbox->countUnread(self::$userId, self::$deptId, new \DateTimeImmutable()));

        $result = NotificationHttp::markAllRead($this->inbox, $this->actor, 'key.all_1', 'ckwrite_req2');

        self::assertSame(200, $result['status']);
        self::assertSame(ErrorCodeRegistry::OK, $result['body']['code']);
        self::assertSame(0, $this->inbox->countUnread(self::$userId, self::$deptId, new \DateTimeImmutable()));
        self::assertSame(
            0,
            OrgSql::int(self::$conn->fetchOne(
                'SELECT COUNT(*) FROM sand_system_notification WHERE user_id = ? AND read_time IS NULL',
                [self::$userId],
            )),
        );
    }
}
