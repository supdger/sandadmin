<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Notification;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Announcement\AnnouncementActor;
use Sandadmin\Module\Announcement\AnnouncementAuditWriter;
use Sandadmin\Module\Announcement\AnnouncementHttp;
use Sandadmin\Module\Announcement\AnnouncementRepository;
use Sandadmin\Module\Announcement\AnnouncementService;
use Sandadmin\Module\Notification\NotificationException;
use Sandadmin\Module\Notification\NotificationHttp;
use Sandadmin\Module\Notification\NotificationRepository;

/**
 * 未读数要把当前可见、尚未阅读的公告算进去。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL。
 */
final class NotificationHttpTest extends TestCase
{
    private static Connection $conn;
    private static int $deptId;
    private static int $otherDeptId;
    private static int $userId;
    private static int $outsiderId;
    private NotificationRepository $inbox;
    private AnnouncementService $announcements;
    private AnnouncementActor $admin;
    private AnnouncementActor $reader;
    private AnnouncementActor $outsider;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380001705%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name IN ('铃铛测试部', '铃铛部外')");
        self::$conn->executeStatement("INSERT INTO sand_system_dept (name, status) VALUES ('铃铛测试部', 1), ('铃铛部外', 1)");
        self::$deptId = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name = '铃铛测试部'"));
        self::$otherDeptId = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name = '铃铛部外'"));
        $insert = "INSERT INTO sand_system_user (phone, nickname, password_hash, dept_id, status) VALUES (:p,:n,'h',:d,1) RETURNING id";
        self::$userId = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800017051', 'n' => '铃铛本人', 'd' => self::$deptId]));
        self::$outsiderId = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800017052', 'n' => '铃铛外人', 'd' => self::$otherDeptId]));
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$conn)) {
            return;
        }
        self::$conn->executeStatement("DELETE FROM sand_system_announcement WHERE title LIKE '铃铛%'");
        self::$conn->executeStatement('DELETE FROM sand_system_notification WHERE user_id IN (?, ?)', [self::$userId, self::$outsiderId]);
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380001705%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name IN ('铃铛测试部', '铃铛部外')");
        self::$conn->close();
    }

    protected function setUp(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_announcement WHERE title LIKE '铃铛%'");
        self::$conn->executeStatement('DELETE FROM sand_system_notification WHERE user_id IN (?, ?)', [self::$userId, self::$outsiderId]);
        $this->inbox = new NotificationRepository(self::$conn);
        $this->announcements = new AnnouncementService(
            new AnnouncementRepository(self::$conn),
            new AnnouncementAuditWriter(self::$conn),
        );
        $this->admin = new AnnouncementActor(self::$userId, '铃铛本人', self::$deptId, [
            'system:announcement:create', 'system:announcement:publish',
        ]);
        $this->reader = new AnnouncementActor(self::$userId, '铃铛本人', self::$deptId, []);
        $this->outsider = new AnnouncementActor(self::$outsiderId, '铃铛外人', self::$otherDeptId, []);
    }

    public function testPublishedAnnouncementCountsAsUnread(): void
    {
        $before = NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell');
        $base = self::toInt($this->data($before)['count']);

        $created = AnnouncementHttp::create($this->announcements, $this->admin, [
            'title' => '铃铛公告',
            'content' => '<p>给本人</p>',
            'scope' => 'all',
        ], null, 'req_bell', null, null);
        $id = self::toStr($this->data($created)['id']);
        AnnouncementHttp::publish($this->announcements, $this->admin, $id, null, 'req_bell', null, null);

        $after = NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell');
        self::assertSame($base + 1, self::toInt($this->data($after)['count']));

        $list = NotificationHttp::index($this->inbox, $this->reader, ['unread_only' => 'true'], 'req_bell');
        $items = $this->data($list)['items'] ?? null;
        self::assertIsArray($items);
        $titles = [];
        foreach ($items as $item) {
            if (is_array($item) && ($item['kind'] ?? null) === 'announcement') {
                $titles[] = $item['title'] ?? null;
            }
        }
        self::assertContains('铃铛公告', $titles);

        AnnouncementHttp::markRead($this->announcements, $this->reader, $id, null, 'req_bell');
        $read = NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell');
        self::assertSame($base, self::toInt($this->data($read)['count']));
    }

    public function testDeptAnnouncementStaysHiddenFromOutsider(): void
    {
        $insideBefore = self::toInt($this->data(NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell'))['count']);
        $outsideBefore = self::toInt($this->data(NotificationHttp::unreadCount($this->inbox, $this->outsider, 'req_bell'))['count']);
        $created = AnnouncementHttp::create($this->announcements, $this->admin, [
            'title' => '铃铛部门公告',
            'content' => '<p>仅本部</p>',
            'scope' => 'dept',
            'dept_ids' => [self::$deptId],
        ], null, 'req_bell', null, null);
        AnnouncementHttp::publish(
            $this->announcements,
            $this->admin,
            self::toStr($this->data($created)['id']),
            null,
            'req_bell',
            null,
            null,
        );

        $insideAfter = self::toInt($this->data(NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell'))['count']);
        $outsideAfter = self::toInt($this->data(NotificationHttp::unreadCount($this->inbox, $this->outsider, 'req_bell'))['count']);
        self::assertSame($insideBefore + 1, $insideAfter);
        self::assertSame($outsideBefore, $outsideAfter);
    }

    public function testDirectNotificationAndMarkAll(): void
    {
        self::$conn->executeStatement(
            'INSERT INTO sand_system_notification (user_id, source_code, text) VALUES (:uid, :source, :text)',
            ['uid' => self::$userId, 'source' => 'host.test', 'text' => '有一条新消息'],
        );
        $counted = NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell');
        self::assertGreaterThan(0, self::toInt($this->data($counted)['count']));

        NotificationHttp::markAllRead($this->inbox, $this->reader, null, 'req_bell');
        $cleared = NotificationHttp::unreadCount($this->inbox, $this->reader, 'req_bell');
        self::assertSame(0, self::toInt($this->data($cleared)['count']));
    }

    public function testMissingNotificationIsNotFound(): void
    {
        try {
            NotificationHttp::markRead($this->inbox, $this->reader, '999999999', null, 'req_bell');
            self::fail('不存在的消息应拒绝');
        } catch (NotificationException $error) {
            self::assertSame(404, $error->status);
            self::assertSame('NOT_FOUND', $error->errorCode);
        }
    }

    public function testGuestIsRejected(): void
    {
        try {
            NotificationHttp::unreadCount($this->inbox, null, 'req_bell');
            self::fail('未登录应拒绝');
        } catch (NotificationException $error) {
            self::assertSame(401, $error->status);
        }
    }

    /** @param array{status: int, body: array<string, mixed>} $outcome
     *  @return array<string, mixed>
     */
    private function data(array $outcome): array
    {
        $data = $outcome['body']['data'] ?? null;
        if (!is_array($data)) {
            return [];
        }
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function toStr(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
