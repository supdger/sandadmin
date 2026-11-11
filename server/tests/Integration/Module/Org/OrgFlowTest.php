<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Http\BizKernel;
use Sandadmin\Http\ScheduleHttp;
use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/**
 * 用户、部门、角色、岗位、选项的写后回读，以及定时任务时间错误码。
 * 需要 SAND_TEST_DB_URL 指向已应用 0001-0004 的 PostgreSQL。
 */
final class OrgFlowTest extends TestCase
{
    private static Connection $conn;
    private static BizKernel $kernel;
    private OrgActor $actor;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::$kernel = BizKernel::fromConnection(self::$conn);
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::wipe();
            self::$conn->close();
        }
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->actor = new OrgActor(0, '测试账号', null, true, ['*']);
    }

    public function testUserRenameRereadAndDuplicates(): void
    {
        $dept = self::$kernel->depts->create(['name' => '甲测部门'], $this->actor, 'req-dept', '127.0.0.1', null);
        $role = self::$kernel->roles->create(['name' => '甲测角色'], $this->actor, 'req-role', '127.0.0.1', null);
        $created = self::$kernel->users->create([
            'nickname' => '甲测用户',
            'phone' => '13700009001',
            'username' => 'bizuser',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-user', '127.0.0.1', null);
        $this->assertSame('甲测用户', $created['nickname']);
        $renamed = self::$kernel->users->update(OrgSql::int($created['id']), [
            'nickname' => '甲测改名',
            'phone' => '13700009001',
            'username' => 'bizuser',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-user-2', '127.0.0.1', null);
        $again = self::$kernel->users->show(OrgSql::int($created['id']));
        $this->assertSame('甲测改名', $renamed['nickname']);
        $this->assertSame('甲测改名', $again['nickname']);

        try {
            self::$kernel->users->create([
                'nickname' => '另一个',
                'phone' => '13700009002',
                'username' => 'bizuser',
                'password' => 'temp-pass-1',
                'dept_id' => $dept['id'],
                'role_ids' => [$role['id']],
            ], $this->actor, 'req-dup-user', '127.0.0.1', null);
            $this->fail('重复登录账号应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame('USERNAME_TAKEN', $error->errorCode);
        }

        try {
            self::$kernel->users->create([
                'nickname' => '另一个',
                'phone' => '13700009001',
                'username' => 'otheruser',
                'password' => 'temp-pass-1',
                'dept_id' => $dept['id'],
                'role_ids' => [$role['id']],
            ], $this->actor, 'req-dup-phone', '127.0.0.1', null);
            $this->fail('重复手机号应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame(422, $error->status);
            $this->assertSame('VALIDATION_FAILED', $error->errorCode);
            $this->assertSame('这个手机号已经有人用了', $error->fields['phone'][0] ?? '');
        }
    }

    public function testDeptRenameAndBlockedDelete(): void
    {
        $dept = self::$kernel->depts->create(['name' => '甲测部门'], $this->actor, 'req-d1', '127.0.0.1', null);
        $renamed = self::$kernel->depts->update(OrgSql::int($dept['id']), ['name' => '甲测部门改'], $this->actor, 'req-d2', '127.0.0.1', null);
        $this->assertSame('甲测部门改', $renamed['name']);
        try {
            self::$kernel->depts->create(['name' => '甲测部门改'], $this->actor, 'req-d3', '127.0.0.1', null);
            $this->fail('同级重名应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame('DEPT_NAME_TAKEN', $error->errorCode);
        }
        $role = self::$kernel->roles->create(['name' => '甲测角色'], $this->actor, 'req-r', '127.0.0.1', null);
        self::$kernel->users->create([
            'nickname' => '甲测用户',
            'phone' => '13700009011',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-u', '127.0.0.1', null);
        try {
            self::$kernel->depts->remove(OrgSql::int($dept['id']), $this->actor, 'req-d4', '127.0.0.1', null);
            $this->fail('有人的部门不能删');
        } catch (OrgException $error) {
            $this->assertSame('DEPT_NOT_EMPTY', $error->errorCode);
        }
    }

    public function testRoleRenameDuplicateAndStalePermission(): void
    {
        $role = self::$kernel->roles->create(['name' => '甲测角色'], $this->actor, 'req-r1', '127.0.0.1', null);
        $renamed = self::$kernel->roles->update(OrgSql::int($role['id']), ['name' => '甲测角色改'], $this->actor, 'req-r2', '127.0.0.1', null);
        $this->assertSame('甲测角色改', $renamed['name']);
        self::$kernel->roles->create(['name' => '甲测另一个'], $this->actor, 'req-r3', '127.0.0.1', null);
        try {
            self::$kernel->roles->update(OrgSql::int($role['id']), ['name' => '甲测另一个'], $this->actor, 'req-r4', '127.0.0.1', null);
            $this->fail('角色重名应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame('ROLE_NAME_TAKEN', $error->errorCode);
        }
        $perms = self::$kernel->roles->permissions(OrgSql::int($role['id']));
        try {
            self::$kernel->roles->savePermissions(OrgSql::int($role['id']), [
                'data_scope' => 'self',
                'dept_ids' => [],
                'permission_codes' => ['system:user:index'],
                'base_version' => OrgSql::int($perms['base_version'] ?? 0) + 9,
            ], $this->actor, 'req-r5', '127.0.0.1', null);
            $this->fail('版本不对应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame('STALE_VERSION', $error->errorCode);
        }
    }

    public function testPostRenameAndDuplicate(): void
    {
        $post = self::$kernel->posts->create(['name' => '甲测岗位'], $this->actor, 'req-p1', '127.0.0.1', null);
        $renamed = self::$kernel->posts->update(OrgSql::int($post['id']), ['name' => '甲测岗位改'], $this->actor, 'req-p2', '127.0.0.1', null);
        $this->assertSame('甲测岗位改', $renamed['name']);
        $shown = self::$kernel->posts->show(OrgSql::int($post['id']));
        $this->assertSame('甲测岗位改', $shown['name']);
        try {
            self::$kernel->posts->create(['name' => '甲测岗位改'], $this->actor, 'req-p3', '127.0.0.1', null);
            $this->fail('岗位重名应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame('POST_NAME_TAKEN', $error->errorCode);
        }
    }

    public function testDictTypeAndItemRename(): void
    {
        $type = self::$kernel->dicts->createType(['name' => '甲测选项组'], $this->actor, 'req-t1', '127.0.0.1', null);
        $renamed = self::$kernel->dicts->updateType(OrgSql::int($type['id']), ['name' => '甲测选项组改'], $this->actor, 'req-t2', '127.0.0.1', null);
        $this->assertSame('甲测选项组改', $renamed['name']);
        $item = self::$kernel->dicts->createItem(OrgSql::int($type['id']), ['label' => '甲测选项'], $this->actor, 'req-i1', '127.0.0.1', null);
        $itemRenamed = self::$kernel->dicts->updateItem(OrgSql::int($item['id']), ['label' => '甲测选项改'], $this->actor, 'req-i2', '127.0.0.1', null);
        $this->assertSame('甲测选项改', $itemRenamed['label']);
        $this->assertNull($itemRenamed['used']);
        try {
            self::$kernel->dicts->createType(['name' => '甲测选项组改'], $this->actor, 'req-t3', '127.0.0.1', null);
            $this->fail('选项组重名应被拒绝');
        } catch (DictException $error) {
            $this->assertSame('DICT_TYPE_TAKEN', $error->errorCode);
        }
    }

    public function testCreateSameKeyInsertsOnce(): void
    {
        $dept = self::$kernel->depts->create(['name' => '幂等底部门'], $this->actor, 'req-base-dept', '127.0.0.1', null);
        $role = self::$kernel->roles->create(['name' => '幂等底角色'], $this->actor, 'req-base-role', '127.0.0.1', null);
        $owner = self::$kernel->users->create([
            'nickname' => '幂等操作人',
            'phone' => '13700009100',
            'username' => 'idemowner',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-owner', '127.0.0.1', null);
        $actor = new OrgActor(OrgSql::int($owner['id']), '幂等操作人', null, true, ['*']);

        $body = ['name' => '幂等部门'];
        $first = self::$kernel->depts->create($body, $actor, 'req-idem-d1', '127.0.0.1', null, 'idem-dept-1');
        $second = self::$kernel->depts->create($body, $actor, 'req-idem-d2', '127.0.0.1', null, 'idem-dept-1');
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_dept WHERE name = '幂等部门'"));
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_idempotency_key WHERE key = 'idem-dept-1'"));

        try {
            self::$kernel->depts->create(['name' => '幂等部门另'], $actor, 'req-idem-d3', '127.0.0.1', null, 'idem-dept-1');
            $this->fail('同键不同内容应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame(409, $error->status);
            $this->assertSame('IDEMPOTENCY_KEY_REUSED', $error->errorCode);
        }
        $this->assertSame(0, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_dept WHERE name = '幂等部门另'"));

        try {
            self::$kernel->depts->create(['name' => '幂等坏键'], $actor, 'req-idem-bad', '127.0.0.1', null, 'bad key');
            $this->fail('不合规的幂等键应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame(422, $error->status);
        }

        $postBody = ['name' => '幂等岗位'];
        $post = self::$kernel->posts->create($postBody, $actor, 'req-idem-p1', '127.0.0.1', null, 'idem-post-1');
        $postAgain = self::$kernel->posts->create($postBody, $actor, 'req-idem-p2', '127.0.0.1', null, 'idem-post-1');
        $this->assertSame($post['id'], $postAgain['id']);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_post WHERE name = '幂等岗位'"));

        $roleBody = ['name' => '幂等角色'];
        $made = self::$kernel->roles->create($roleBody, $actor, 'req-idem-r1', '127.0.0.1', null, 'idem-role-1');
        $madeAgain = self::$kernel->roles->create($roleBody, $actor, 'req-idem-r2', '127.0.0.1', null, 'idem-role-1');
        $this->assertSame($made['id'], $madeAgain['id']);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_role WHERE name = '幂等角色'"));

        $userBody = [
            'nickname' => '幂等用户',
            'phone' => '13700009101',
            'username' => 'idemuser',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ];
        $user = self::$kernel->users->create($userBody, $actor, 'req-idem-u1', '127.0.0.1', null, 'idem-user-1');
        $userAgain = self::$kernel->users->create($userBody, $actor, 'req-idem-u2', '127.0.0.1', null, 'idem-user-1');
        $this->assertSame($user['id'], $userAgain['id']);
        $this->assertArrayNotHasKey('password', $userAgain);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_user WHERE phone = '13700009101'"));
        $stored = self::$conn->fetchOne("SELECT response::text FROM sand_system_idempotency_key WHERE key = 'idem-user-1'");
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('temp-pass-1', $stored);

        $typeBody = ['name' => '幂等选项组'];
        $type = self::$kernel->dicts->createType($typeBody, $actor, 'req-idem-t1', '127.0.0.1', null, 'idem-type-1');
        $typeAgain = self::$kernel->dicts->createType($typeBody, $actor, 'req-idem-t2', '127.0.0.1', null, 'idem-type-1');
        $this->assertSame($type['id'], $typeAgain['id']);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_dict_type WHERE name = '幂等选项组'"));

        $itemBody = ['label' => '幂等选项'];
        $typeId = OrgSql::int($type['id']);
        $item = self::$kernel->dicts->createItem($typeId, $itemBody, $actor, 'req-idem-i1', '127.0.0.1', null, 'idem-item-1');
        $itemAgain = self::$kernel->dicts->createItem($typeId, $itemBody, $actor, 'req-idem-i2', '127.0.0.1', null, 'idem-item-1');
        $this->assertSame($item['id'], $itemAgain['id']);
        $this->assertSame($item['value'], $itemAgain['value']);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_dict_item WHERE label = '幂等选项'"));
        $this->assertSame(6, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_idempotency_key WHERE key LIKE 'idem-%'"));
    }

    public function testScheduleInvalidReturns422BeforeWrite(): void
    {
        $http = new ScheduleHttp(self::$conn);
        $bad = $http->update('host.biz-a', ['schedule' => ['freq' => 'interval', 'interval' => 1]], 'req-sch');
        $this->assertSame(422, $bad['status']);
        $this->assertSame('TASK_SCHEDULE_INVALID', $bad['body']['code'] ?? null);
        $missing = $http->update('host.biz-a-missing', ['status' => 'disabled'], 'req-sch-2');
        $this->assertSame(404, $missing['status']);
        $this->assertSame('TASK_NOT_FOUND', $missing['body']['code'] ?? null);
    }

    private function rowsMatching(string $sql): int
    {
        return OrgSql::int(self::$conn->fetchOne($sql));
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_idempotency_key WHERE key LIKE 'idem-%'");
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '13700009%'");
        self::$conn->executeStatement("DELETE FROM sand_system_role WHERE name LIKE '甲测%' OR name LIKE '幂等%'");
        self::$conn->executeStatement("DELETE FROM sand_system_post WHERE name LIKE '甲测%' OR name LIKE '幂等%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name LIKE '甲测%' OR name LIKE '幂等%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dict_type WHERE name LIKE '甲测%' OR name LIKE '幂等%'");
        self::$conn->executeStatement("DELETE FROM sand_system_schedule WHERE code LIKE 'host.biz-a%'");
        self::$conn->executeStatement("DELETE FROM sand_system_audit_log WHERE actor_name = '测试账号' OR actor_name = '幂等操作人'");
    }
}
