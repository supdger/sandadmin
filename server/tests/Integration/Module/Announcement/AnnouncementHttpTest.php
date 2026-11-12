<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Announcement;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Announcement\AnnouncementActor;
use Sandadmin\Module\Announcement\AnnouncementAuditWriter;
use Sandadmin\Module\Announcement\AnnouncementException;
use Sandadmin\Module\Announcement\AnnouncementHttp;
use Sandadmin\Module\Announcement\AnnouncementRepository;
use Sandadmin\Module\Announcement\AnnouncementService;

/**
 * 公告模块端到端（HTTP 装配层直接调用，注入操作人代替鉴权中间件）。
 * 需要 SAND_TEST_DB_URL 指向已应用 0001-0003 基线迁移的 PostgreSQL。
 */
final class AnnouncementHttpTest extends TestCase
{
    private static Connection $conn;
    private static int $deptA;
    private static int $deptB;
    private static int $userA;
    private static int $userB;
    private static int $userOutside;
    private AnnouncementService $service;
    private AnnouncementActor $admin;
    private AnnouncementActor $member;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        // 上次中断可能残留夹具，先清再建
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '138000000%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name LIKE '公告%部'");
        // 0004 把 level 加宽到 varchar(16)，important（9 字符）才能写入。不改已发布的 0001。
        $migration = file_get_contents(dirname(__DIR__, 4) . '/database/migrations/0004_widen_announcement_level.sql');
        if (!is_string($migration) || $migration === '') {
            self::fail('缺少 0004 迁移文件');
        }
        self::$conn->executeStatement($migration);
        $width = self::$conn->fetchOne(
            "SELECT character_maximum_length FROM information_schema.columns"
            . " WHERE table_schema = 'public' AND table_name = 'sand_system_announcement' AND column_name = 'level'",
        );
        if (!is_numeric($width) || (int) $width !== 16) {
            self::fail('announcement.level 应为 varchar(16)');
        }
        self::$conn->executeStatement("INSERT INTO sand_system_dept (name, status) VALUES ('公告甲部', 1), ('公告乙部', 1)");
        self::$deptA = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name='公告甲部'"));
        self::$deptB = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name='公告乙部'"));
        $insert = "INSERT INTO sand_system_user (phone, nickname, password_hash, dept_id, status) VALUES (:p,:n,'h',:d,1) RETURNING id";
        self::$userA = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000011', 'n' => '公告管理员', 'd' => self::$deptA]));
        self::$userB = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000012', 'n' => '甲部成员', 'd' => self::$deptA]));
        self::$userOutside = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000013', 'n' => '乙部成员', 'd' => self::$deptB]));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '138000000%'");
            self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name LIKE '公告%部'");
            self::$conn->close();
        }
    }

    protected function setUp(): void
    {
        self::$conn->executeStatement('DELETE FROM sand_system_announcement');
        self::$conn->executeStatement("DELETE FROM sand_system_audit_log WHERE module='announcement'");
        $repository = new AnnouncementRepository(self::$conn);
        $this->service = new AnnouncementService($repository, new AnnouncementAuditWriter(self::$conn));
        $this->admin = new AnnouncementActor(self::$userA, '公告管理员', self::$deptA, [
            'system:announcement:index', 'system:announcement:create', 'system:announcement:update',
            'system:announcement:delete', 'system:announcement:publish', 'system:announcement:revoke',
        ]);
        $this->member = new AnnouncementActor(self::$userB, '甲部成员', self::$deptA, []);
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return array<array-key, mixed> */
    private function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function toStr(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array{status: int, body: array<string, mixed>} $outcome
     *
     * @return array<string, mixed>
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

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    private function body(array $over = []): array
    {
        return array_merge(['title' => '版本更新', 'content' => '<p>正文</p>', 'scope' => 'all'], $over);
    }

    private function createDraft(): int
    {
        $outcome = AnnouncementHttp::create($this->service, $this->admin, $this->body(), null, 'req_t', '127.0.0.1', 'cli');
        self::assertSame(200, $outcome['status']);

        return self::toInt($this->data($outcome)['id']);
    }

    public function testCreateListShowAndAudit(): void
    {
        $id = $this->createDraft();
        $show = AnnouncementHttp::show($this->service, $this->admin, (string) $id, 'req_t');
        self::assertSame(200, $show['status']);
        self::assertSame('版本更新', $this->data($show)['title']);
        self::assertSame('draft', $this->data($show)['state']);
                $expectedRecipients = self::toInt(self::$conn->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user WHERE status = 1 AND delete_time IS NULL',
        ));
        self::assertSame($expectedRecipients, $this->data($show)['recipient_count']);
        self::assertSame('公告管理员', $this->data($show)['created_by_name']);

        $list = AnnouncementHttp::index($this->service, $this->admin, ['keyword' => '版本'], 'req_t');
        self::assertSame(1, $this->data($list)['total']);
        $list = AnnouncementHttp::index($this->service, $this->admin, ['keyword' => '不存在'], 'req_t');
        self::assertSame(0, $this->data($list)['total']);

        $audit = self::$conn->fetchOne("SELECT action_text FROM sand_system_audit_log WHERE module='announcement' AND action='create'");
        self::assertSame('新建了公告「版本更新」', $audit);
    }

    public function testCreateValidationErrors(): void
    {
        try {
            AnnouncementHttp::create($this->service, $this->admin, ['title' => '', 'content' => '', 'scope' => 'dept'], null, 'r', null, null);
            self::fail('应抛校验异常');
        } catch (AnnouncementException $e) {
            self::assertSame('VALIDATION_FAILED', $e->errorCode);
            self::assertArrayHasKey('title', $e->fields);
            self::assertArrayHasKey('dept_ids', $e->fields);
        }
        try {
            AnnouncementHttp::create($this->service, $this->admin, $this->body(['scope' => 'dept', 'dept_ids' => [999998]]), null, 'r', null, null);
            self::fail('应抛部门不存在');
        } catch (AnnouncementException $e) {
            self::assertArrayHasKey('dept_ids', $e->fields);
        }
        try {
            AnnouncementHttp::create($this->service, $this->admin, $this->body(['title' => str_repeat('长', 51)]), null, 'r', null, null);
            self::fail('应抛标题超长');
        } catch (AnnouncementException $e) {
            self::assertArrayHasKey('title', $e->fields);
        }
    }

    public function testUnauthenticatedAndForbidden(): void
    {
        $id = $this->createDraft();
        try {
            AnnouncementHttp::index($this->service, null, [], 'r');
            self::fail();
        } catch (AnnouncementException $e) {
            self::assertSame(401, $e->status);
        }
        try {
            AnnouncementHttp::update($this->service, $this->member, (string) $id, $this->body(), null, 'r', null, null);
            self::fail();
        } catch (AnnouncementException $e) {
            self::assertSame(403, $e->status);
            self::assertSame('PERMISSION_DENIED', $e->errorCode);
        }
    }

    public function testPublishRevokeDeleteLifecycle(): void
    {
        $id = $this->createDraft();
        AnnouncementHttp::publish($this->service, $this->admin, (string) $id, null, 'r', null, null);
        $show = AnnouncementHttp::show($this->service, $this->admin, (string) $id, 'r');
        self::assertSame('published', $this->data($show)['state']);
        self::assertNotNull($this->data($show)['published_time']);

        // 收件人标记已读（幂等）
        AnnouncementHttp::markRead($this->service, $this->member, (string) $id, null, 'r');
        AnnouncementHttp::markRead($this->service, $this->member, (string) $id, null, 'r');
        $stats = AnnouncementHttp::readStats($this->service, $this->admin, (string) $id, 'r');
        self::assertSame(1, $this->data($stats)['read_count']);
        $totalRecipients = self::toInt(self::$conn->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user WHERE status = 1 AND delete_time IS NULL',
        ));
        self::assertSame($totalRecipients - 1, $this->data($stats)['unread_count']);

        // 收回后不可见：收件人再标记返回 404
        AnnouncementHttp::revoke($this->service, $this->admin, (string) $id, null, 'r', null, null);
        try {
            AnnouncementHttp::markRead($this->service, $this->member, (string) $id, null, 'r');
            self::fail('收回后应不可见');
        } catch (AnnouncementException $e) {
            self::assertSame('ANNOUNCEMENT_NOT_FOUND', $e->errorCode);
            self::assertSame(404, $e->status);
        }
        // 收回保留已读记录，可重新发布
        AnnouncementHttp::publish($this->service, $this->admin, (string) $id, null, 'r', null, null);
        self::assertTrue($this->service->readStats($id)['read_count'] === 1);

        // 删除：已读记录级联、行不存在
        AnnouncementHttp::destroy($this->service, $this->admin, (string) $id, null, 'r', null, null);
        try {
            AnnouncementHttp::show($this->service, $this->admin, (string) $id, 'r');
            self::fail();
        } catch (AnnouncementException $e) {
            self::assertSame('ANNOUNCEMENT_NOT_FOUND', $e->errorCode);
        }
        $audit = self::$conn->fetchOne("SELECT COUNT(*) FROM sand_system_audit_log WHERE module='announcement' AND action='delete'");
        self::assertSame(1, self::toInt($audit));
    }

    public function testDeptScopeReadAndStats(): void
    {
        $outcome = AnnouncementHttp::create($this->service, $this->admin, $this->body([
            'scope' => 'dept', 'dept_ids' => [self::$deptA],
        ]), null, 'r', null, null);
        $id = self::toStr($this->data($outcome)['id']);
        AnnouncementHttp::publish($this->service, $this->admin, $id, null, 'r', null, null);

        // 收件人分母只算甲部（2 人），不含乙部
        $stats = AnnouncementHttp::readStats($this->service, $this->admin, $id, 'r');
        self::assertSame(0, $this->data($stats)['read_count']);
        self::assertSame(2, $this->data($stats)['unread_count']);
        $statItems = $this->data($stats)['items'];
        self::assertIsArray($statItems);
        self::assertCount(2, $statItems);
        $names = [];
        foreach ($statItems as $row) {
            if (is_array($row)) {
                $names[] = self::toStr($row['nickname'] ?? '');
            }
        }
        sort($names);
        self::assertSame(['公告管理员', '甲部成员'], $names);

        // 乙部成员不在范围内 → 标记 404
        $outside = new AnnouncementActor(self::$userOutside, '乙部成员', self::$deptB, []);
        try {
            AnnouncementHttp::markRead($this->service, $outside, $id, null, 'r');
            self::fail();
        } catch (AnnouncementException $e) {
            self::assertSame('ANNOUNCEMENT_NOT_FOUND', $e->errorCode);
        }
        AnnouncementHttp::markRead($this->service, $this->member, $id, null, 'r');
    }

    public function testPopupImportantUnreadOnly(): void
    {
        $outcome = AnnouncementHttp::create($this->service, $this->admin, $this->body(['level' => 'important']), null, 'r', null, null);
        $id = self::toStr($this->data($outcome)['id']);

        // 草稿不出现；发布后未读才出现
        self::assertCount(0, $this->arr($this->data(AnnouncementHttp::popup($this->service, $this->member, 'r'))['items']));
        AnnouncementHttp::publish($this->service, $this->admin, $id, null, 'r', null, null);
        $items = $this->arr($this->data(AnnouncementHttp::popup($this->service, $this->member, 'r'))['items']);
        self::assertCount(1, $items);
        $first = $this->arr(reset($items));
        self::assertSame(self::toInt($id), self::toInt($first['id'] ?? null));

        // 已读后不再弹出；普通级公告不弹
        AnnouncementHttp::markRead($this->service, $this->member, $id, null, 'r');
        self::assertCount(0, $this->arr($this->data(AnnouncementHttp::popup($this->service, $this->member, 'r'))['items']));
        $normal = AnnouncementHttp::create($this->service, $this->admin, $this->body(['level' => 'normal']), null, 'r', null, null);
        AnnouncementHttp::publish($this->service, $this->admin, self::toStr($this->data($normal)['id']), null, 'r', null, null);
        self::assertCount(0, $this->arr($this->data(AnnouncementHttp::popup($this->service, $this->member, 'r'))['items']));
    }

    public function testExpiredDerivationAndFilter(): void
    {
        $outcome = AnnouncementHttp::create($this->service, $this->admin, $this->body([
            'expire_time' => '2020-01-01T00:00:00+00:00',
        ]), null, 'r', null, null);
        $id = self::toStr($this->data($outcome)['id']);
        AnnouncementHttp::publish($this->service, $this->admin, $id, null, 'r', null, null);

        $show = AnnouncementHttp::show($this->service, $this->admin, $id, 'r');
        self::assertTrue($this->data($show)['expired']);
        self::assertSame('published', $this->data($show)['state']);

        $expired = AnnouncementHttp::index($this->service, $this->admin, ['state' => 'expired'], 'r');
        self::assertSame(1, $this->data($expired)['total']);

        // 已过期对收件人不可见
        try {
            AnnouncementHttp::markRead($this->service, $this->member, $id, null, 'r');
            self::fail();
        } catch (AnnouncementException $e) {
            self::assertSame('ANNOUNCEMENT_NOT_FOUND', $e->errorCode);
        }
        try {
            AnnouncementHttp::index($this->service, $this->admin, ['state' => 'bogus'], 'r');
            self::fail();
        } catch (AnnouncementException $e) {
            self::assertSame('VALIDATION_FAILED', $e->errorCode);
        }
    }

    public function testUpdateAndSanitizedContent(): void
    {
        $id = $this->createDraft();
        $outcome = AnnouncementHttp::update($this->service, $this->admin, (string) $id, $this->body([
            'title' => '改名', 'content' => '<p>新<script>alert(1)</script>文</p><img src="javascript:x">',
        ]), null, 'r', null, null);
        self::assertSame(200, $outcome['status']);
        $data = $this->data($outcome);
        self::assertSame('改名', self::toStr($data['title'] ?? ''));
        $content = self::toStr($data['content'] ?? '');
        self::assertStringNotContainsString('script', strtolower($content));
        self::assertStringContainsString('新', $content);
        $audit = self::$conn->fetchOne("SELECT action_text FROM sand_system_audit_log WHERE module='announcement' AND action='update'");
        self::assertSame('更新了公告「改名」', $audit);
    }
}
