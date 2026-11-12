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
use Sandadmin\Module\Package\PackageOps;
use Sandadmin\Task\StoredExportResolver;
use Sandadmin\Task\TaskRun;
use Sandadmin\Task\TaskState;

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
        self::$conn->executeStatement(
            'CREATE TABLE IF NOT EXISTS sand_system_dict_item_ref ('
            . ' item_id bigint NOT NULL,'
            . ' subject_type varchar(64) NOT NULL,'
            . ' subject_id varchar(64) NOT NULL,'
            . ' create_time timestamptz(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' CONSTRAINT pk_sand_system_dict_item_ref PRIMARY KEY (item_id, subject_type, subject_id),'
            . ' CONSTRAINT fk_sand_system_dict_item_ref_item FOREIGN KEY (item_id)'
            . ' REFERENCES sand_system_dict_item (id) ON DELETE CASCADE)'
        );
        self::$conn->executeStatement(
            'CREATE INDEX IF NOT EXISTS ix_sand_system_dict_item_ref_subject'
            . ' ON sand_system_dict_item_ref (subject_type, subject_id)'
        );
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
        $this->assertFalse($itemRenamed['used']);
        try {
            self::$kernel->dicts->createType(['name' => '甲测选项组改'], $this->actor, 'req-t3', '127.0.0.1', null);
            $this->fail('选项组重名应被拒绝');
        } catch (DictException $error) {
            $this->assertSame('DICT_TYPE_TAKEN', $error->errorCode);
        }
    }

    public function testRenameKeepsDisabledStatus(): void
    {
        $dept = self::$kernel->depts->create(
            ['name' => '甲测停用部门', 'status' => 'disabled'],
            $this->actor,
            'req-st-d1',
            '127.0.0.1',
            null,
        );
        $deptKept = self::$kernel->depts->update(
            OrgSql::int($dept['id']),
            ['name' => '甲测停用部门改'],
            $this->actor,
            'req-st-d2',
            '127.0.0.1',
            null,
        );
        $this->assertSame('disabled', $deptKept['status']);

        $role = self::$kernel->roles->create(
            ['name' => '甲测停用角色', 'status' => 'disabled'],
            $this->actor,
            'req-st-r1',
            '127.0.0.1',
            null,
        );
        $roleKept = self::$kernel->roles->update(
            OrgSql::int($role['id']),
            ['name' => '甲测停用角色改'],
            $this->actor,
            'req-st-r2',
            '127.0.0.1',
            null,
        );
        $this->assertSame('disabled', $roleKept['status']);

        $post = self::$kernel->posts->create(
            ['name' => '甲测停用岗位', 'status' => 'disabled'],
            $this->actor,
            'req-st-p1',
            '127.0.0.1',
            null,
        );
        $postKept = self::$kernel->posts->update(
            OrgSql::int($post['id']),
            ['name' => '甲测停用岗位改'],
            $this->actor,
            'req-st-p2',
            '127.0.0.1',
            null,
        );
        $this->assertSame('disabled', $postKept['status']);

        $user = self::$kernel->users->create([
            'nickname' => '甲测停用用户',
            'phone' => '13700009021',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
            'status' => 'disabled',
        ], $this->actor, 'req-st-u1', '127.0.0.1', null);
        $userKept = self::$kernel->users->update(OrgSql::int($user['id']), [
            'nickname' => '甲测停用用户改',
            'phone' => '13700009021',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-st-u2', '127.0.0.1', null);
        $this->assertSame('disabled', $userKept['status']);

        $type = self::$kernel->dicts->createType(['name' => '甲测停用组'], $this->actor, 'req-st-t1', '127.0.0.1', null);
        $item = self::$kernel->dicts->createItem(
            OrgSql::int($type['id']),
            ['label' => '甲测停用项', 'status' => 'disabled'],
            $this->actor,
            'req-st-i1',
            '127.0.0.1',
            null,
        );
        $this->assertSame('disabled', $item['status']);
        $itemKept = self::$kernel->dicts->updateItem(
            OrgSql::int($item['id']),
            ['label' => '甲测停用项改'],
            $this->actor,
            'req-st-i2',
            '127.0.0.1',
            null,
        );
        $this->assertSame('甲测停用项改', $itemKept['label']);
        $this->assertSame('disabled', $itemKept['status']);

        $enabled = self::$kernel->dicts->updateItem(
            OrgSql::int($item['id']),
            ['label' => '甲测停用项改', 'status' => 'enabled'],
            $this->actor,
            'req-st-i3',
            '127.0.0.1',
            null,
        );
        $this->assertSame('enabled', $enabled['status']);
    }

    public function testProfileReadsNamePhoneAndDept(): void
    {
        $dept = self::$kernel->depts->create(['name' => '甲测资料部门'], $this->actor, 'req-pf-d', '127.0.0.1', null);
        $role = self::$kernel->roles->create(['name' => '甲测资料角色'], $this->actor, 'req-pf-r', '127.0.0.1', null);
        $user = self::$kernel->users->create([
            'nickname' => '甲测资料人',
            'phone' => '13700009022',
            'username' => 'pfuser',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-pf-u', '127.0.0.1', null);
        $profile = self::$kernel->users->profile(OrgSql::int($user['id']));
        $this->assertSame('甲测资料人', $profile['nickname']);
        $this->assertSame('13700009022', $profile['phone']);
        $this->assertSame('甲测资料部门', $profile['dept_name']);
        $this->assertSame('pfuser', $profile['username']);
        $roleNames = [];
        $rawNames = $profile['role_names'];
        if (is_array($rawNames)) {
            foreach ($rawNames as $name) {
                if (is_string($name)) {
                    $roleNames[] = $name;
                }
            }
        }
        $this->assertContains('甲测资料角色', $roleNames);

        $saved = self::$kernel->users->updateProfile(OrgSql::int($user['id']), [
            'nickname' => '甲测资料改',
            'phone' => '13700009022',
        ], $this->actor, 'req-pf-up', '127.0.0.1', null);
        $this->assertSame('甲测资料改', $saved['nickname']);
        $this->assertSame('甲测资料部门', $saved['dept_name']);
        $again = self::$kernel->users->show(OrgSql::int($user['id']));
        $this->assertSame('enabled', $again['status']);
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

    public function testDictItemUsedFollowsReferences(): void
    {
        $type = self::$kernel->dicts->createType(['name' => '甲测引用组'], $this->actor, 'req-used-t', '127.0.0.1', null);
        $item = self::$kernel->dicts->createItem(OrgSql::int($type['id']), ['label' => '甲测引用项'], $this->actor, 'req-used-i', '127.0.0.1', null);
        $this->assertFalse($item['used']);
        self::$conn->executeStatement(
            'INSERT INTO sand_system_dict_item_ref (item_id, subject_type, subject_id) VALUES (?, ?, ?)',
            [OrgSql::int($item['id']), 'demo', 'row-1'],
        );
        $used = self::$kernel->dicts->updateItem(OrgSql::int($item['id']), ['label' => '甲测引用项'], $this->actor, 'req-used-i2', '127.0.0.1', null);
        $this->assertTrue($used['used']);
        self::$conn->executeStatement('DELETE FROM sand_system_dict_item_ref WHERE item_id = ?', [OrgSql::int($item['id'])]);
        self::$conn->executeStatement(
            'UPDATE sand_system_dict_type SET extension_key = ? WHERE id = ?',
            ['flow.group', OrgSql::int($type['id'])],
        );
        $unknown = self::$kernel->dicts->updateItem(OrgSql::int($item['id']), ['label' => '甲测引用项'], $this->actor, 'req-used-i3', '127.0.0.1', null);
        $this->assertNull($unknown['used']);
    }

    public function testRoleDeleteRequiresReplacementWhenUsed(): void
    {
        $dept = self::$kernel->depts->create(['name' => '甲测换角部门'], $this->actor, 'req-mv-d', '127.0.0.1', null);
        $role = self::$kernel->roles->create(['name' => '甲测在用角色'], $this->actor, 'req-mv-r', '127.0.0.1', null);
        $next = self::$kernel->roles->create(['name' => '甲测替换角色'], $this->actor, 'req-mv-n', '127.0.0.1', null);
        $user = self::$kernel->users->create([
            'nickname' => '甲测换角人',
            'phone' => '13700009021',
            'username' => 'moverole',
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-mv-u', '127.0.0.1', null);
        $roleId = OrgSql::int($role['id']);
        $nextId = OrgSql::int($next['id']);
        try {
            self::$kernel->roles->remove($roleId, null, $this->actor, 'req-mv-x', '127.0.0.1', null);
            $this->fail('还有人在用时不传新角色应被拒绝');
        } catch (OrgException $error) {
            $this->assertSame(422, $error->status);
            $this->assertStringContainsString('还有 1 个人在用这个角色', $error->getMessage());
        }
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_role WHERE name = '甲测在用角色'"));
        $kept = self::$conn->fetchOne(
            'SELECT role_id FROM sand_system_user_role WHERE user_id = ?',
            [OrgSql::int($user['id'])],
        );
        $this->assertSame($roleId, OrgSql::int($kept));
        $removed = self::$kernel->roles->remove($roleId, $nextId, $this->actor, 'req-mv-ok', '127.0.0.1', null);
        $this->assertSame(1, $removed['moved_count']);
        $this->assertSame('甲测替换角色', $removed['replacement_role_name']);
        $this->assertSame(0, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_role WHERE name = '甲测在用角色'"));
        $now = self::$conn->fetchOne(
            'SELECT role_id FROM sand_system_user_role WHERE user_id = ?',
            [OrgSql::int($user['id'])],
        );
        $this->assertSame($nextId, OrgSql::int($now));
        $empty = self::$kernel->roles->create(['name' => '甲测空角色'], $this->actor, 'req-mv-e', '127.0.0.1', null);
        $gone = self::$kernel->roles->remove(OrgSql::int($empty['id']), null, $this->actor, 'req-mv-e2', '127.0.0.1', null);
        $this->assertSame(0, $gone['moved_count']);
        $this->assertNull($gone['replacement_role_name']);
    }

    public function testCopyRoleReusesIdempotencyKey(): void
    {
        $owner = $this->ownerActor('13700009041', 'copyowner', '甲测复制人');
        $source = self::$kernel->roles->create(['name' => '甲测复制源'], $owner, 'req-cp-s', '127.0.0.1', null);
        $body = ['name' => '甲测副本'];
        $first = self::$kernel->roles->copy(OrgSql::int($source['id']), $body, $owner, 'req-cp-1', '127.0.0.1', null, 'idem-copy-1');
        $second = self::$kernel->roles->copy(OrgSql::int($source['id']), $body, $owner, 'req-cp-2', '127.0.0.1', null, 'idem-copy-1');
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_role WHERE name = '甲测副本'"));
    }

    public function testScheduleSaveReturnsChineseName(): void
    {
        self::$conn->executeStatement(
            'INSERT INTO sand_system_schedule (code, enabled) VALUES (?, true)',
            ['host.log-cleanup'],
        );
        $http = new ScheduleHttp(self::$conn);
        $saved = $http->update('host.log-cleanup', ['status' => 'disabled'], 'req-sch-name');
        $this->assertSame(200, $saved['status']);
        $data = $saved['body']['data'] ?? null;
        $this->assertIsArray($data);
        $this->assertSame('日志清理', $data['name'] ?? null);
    }

    public function testUserExportWritesDownloadableFile(): void
    {
        $root = sys_get_temp_dir() . '/sandadmin-export-' . getmypid();
        $previous = getenv('SAND_FILE_ROOT');
        putenv('SAND_FILE_ROOT=' . $root);
        try {
            $owner = $this->ownerActor('13700009031', 'exportowner', '甲测导出人');
            $exported = self::$kernel->users->export([], $owner, 'req-exp', '127.0.0.1', null);
            $this->assertSame('succeeded', $exported['state']);
            $raw = self::$conn->fetchOne(
                'SELECT result::text FROM sand_system_task_run WHERE public_id = ?',
                [$exported['task_id']],
            );
            $this->assertIsString($raw);
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded);
            $now = new \DateTimeImmutable();
            $task = new TaskRun(
                $exported['task_id'],
                'user.export',
                TaskState::Succeeded,
                '已完成',
                100,
                ['file_id' => OrgSql::int($decoded['file_id'] ?? 0)],
                null,
                null,
                [],
                $owner->userId,
                null,
                0,
                null,
                null,
                $now,
                $now,
                $now,
                $now,
            );
            $artifact = (new StoredExportResolver(self::$conn))->resolve($task);
            $this->assertNotNull($artifact);
            $bytes = file_get_contents($artifact->path);
            $this->assertIsString($bytes);
            $this->assertStringContainsString('甲测导出人', $bytes);
            $this->assertStringNotContainsString('temp-pass-1', $bytes);
        } finally {
            if (is_string($previous) && $previous !== '') {
                putenv('SAND_FILE_ROOT=' . $previous);
            } else {
                putenv('SAND_FILE_ROOT');
            }
        }
    }

    public function testProductionRefusesCompiledPluginInstall(): void
    {
        $this->withPackageEnv('production', '0', '1', function (): void {
            $owner = $this->ownerActor('13700009051', 'pkgowner', '甲测安装人');
            $ops = new PackageOps(self::$conn);
            $refused = $ops->install('flow', $owner, 'req-pkg-prod');
            $this->assertSame(403, $refused['status']);
            $this->assertSame('PLUGIN_OPERATION_FORBIDDEN', $refused['body']['code'] ?? null);
            $this->assertSame(0, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_plugin WHERE code = 'flow'"));
            $phar = $ops->upload('evil.phar', '{"code":"flow"}', $owner, 'req-pkg-phar');
            $this->assertSame(403, $phar['status']);
            $this->assertSame(0, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_lifecycle_request WHERE executor_id = 'package-ops'"));
        });
    }

    public function testPackageInstallAdvancesWithoutExecutingCode(): void
    {
        $root = sys_get_temp_dir() . '/sandadmin-export-' . getmypid();
        $previous = getenv('SAND_FILE_ROOT');
        putenv('SAND_FILE_ROOT=' . $root);
        try {
            $this->withPackageEnv('development', '0', '1', function (): void {
                $owner = $this->ownerActor('13700009061', 'pkgdev', '甲测开发装');
                $ops = new PackageOps(self::$conn);
                $compiled = $ops->upload('pack.json', '{"compiled":true,"code":"flow"}', $owner, 'req-pkg-c');
                $this->assertSame(403, $compiled['status']);
                $bad = $ops->upload('pack.json', 'not-json', $owner, 'req-pkg-b');
                $this->assertSame(422, $bad['status']);
                $accepted = $ops->install('flow', $owner, 'req-pkg-ok');
                $this->assertSame(200, $accepted['status']);
                $data = $accepted['body']['data'] ?? null;
                $this->assertIsArray($data);
                $requestId = $data['request_id'] ?? '';
                $this->assertIsString($requestId);
                for ($step = 0; $step < 4; $step++) {
                    $ops->advance($requestId);
                }
                $this->assertSame(1, $this->rowsMatching("SELECT COUNT(*) FROM sand_system_plugin WHERE code = 'flow' AND state = 'installed'"));
                $this->assertSame('1.0.0', self::$conn->fetchOne("SELECT version FROM sand_system_plugin WHERE code = 'flow'"));
                $started = $ops->startUpdate($owner, 'req-up');
                $this->assertSame(200, $started['status']);
                $update = $started['body']['data'] ?? null;
                $this->assertIsArray($update);
                $updateId = $update['request_id'] ?? '';
                $this->assertIsString($updateId);
                $seen = [];
                for ($step = 0; $step < 6; $step++) {
                    $ops->advance($updateId);
                    $key = self::$conn->fetchOne(
                        'SELECT stage_key FROM sand_system_lifecycle_request WHERE public_id = ?',
                        [$updateId],
                    );
                    if (is_string($key)) {
                        $seen[$key] = true;
                    }
                }
                foreach (['check', 'backup', 'download', 'switch', 'verify'] as $stage) {
                    $this->assertArrayHasKey($stage, $seen);
                }
                $state = self::$conn->fetchOne(
                    'SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?',
                    [$updateId],
                );
                $this->assertSame('succeeded', $state);
            });
        } finally {
            if (is_string($previous) && $previous !== '') {
                putenv('SAND_FILE_ROOT=' . $previous);
            } else {
                putenv('SAND_FILE_ROOT');
            }
        }
    }

    public function testReinstallKeepsPluginExtension(): void
    {
        $this->withPackageEnv('development', '0', '1', function (): void {
            self::$conn->executeStatement("DELETE FROM sand_system_lifecycle_request WHERE target_code = 'flow'");
            self::$conn->executeStatement("DELETE FROM sand_system_plugin_extension WHERE plugin_code = 'flow'");
            self::$conn->executeStatement("DELETE FROM sand_system_plugin WHERE code = 'flow'");
            $owner = $this->ownerActor('13700009071', 'pkgkeep', '甲测重装人');
            $ops = new PackageOps(self::$conn);
            $accepted = $ops->install('flow', $owner, 'req-pkg-keep');
            $this->assertSame(200, $accepted['status']);
            $data = $accepted['body']['data'] ?? null;
            $this->assertIsArray($data);
            $requestId = $data['request_id'] ?? '';
            $this->assertIsString($requestId);
            $this->finishPackageRequest($ops, $requestId);
            $pluginId = self::$conn->fetchOne("SELECT id FROM sand_system_plugin WHERE code = 'flow' AND state = 'installed'");
            $this->assertNotFalse($pluginId);
            self::$conn->executeStatement(
                "INSERT INTO sand_system_plugin_extension (plugin_code, kind, extension_key, enabled, sort)"
                . " VALUES ('flow', 'workbench_card', 'flow.pending', false, 3)",
            );
            $removed = $ops->uninstall('flow', false, $owner, 'req-pkg-off');
            $this->assertSame(200, $removed['status']);
            $off = $removed['body']['data'] ?? null;
            $this->assertIsArray($off);
            $offId = $off['request_id'] ?? '';
            $this->assertIsString($offId);
            $this->finishPackageRequest($ops, $offId);
            $this->assertSame('uninstalled', self::$conn->fetchOne("SELECT state FROM sand_system_plugin WHERE id = ?", [$pluginId]));
            $again = $ops->install('flow', $owner, 'req-pkg-back');
            $this->assertSame(200, $again['status']);
            $back = $again['body']['data'] ?? null;
            $this->assertIsArray($back);
            $backId = $back['request_id'] ?? '';
            $this->assertIsString($backId);
            $this->finishPackageRequest($ops, $backId);
            $this->assertSame($pluginId, self::$conn->fetchOne("SELECT id FROM sand_system_plugin WHERE code = 'flow' AND state = 'installed'"));
            $this->assertSame(1, $this->rowsMatching(
                "SELECT COUNT(*) FROM sand_system_plugin_extension WHERE plugin_code = 'flow' AND extension_key = 'flow.pending' AND enabled = false AND sort = 3",
            ));
        });
    }

    public function testUninstallWithoutPurgeSuspendsResidueAndReinstallResumes(): void
    {
        $this->withPackageEnv('development', '0', '1', function (): void {
            $owner = $this->ownerActor('13700009072', 'pkgsusp', '甲测停用人');
            $ops = new PackageOps(self::$conn);
            $accepted = $ops->install('flow', $owner, 'req-pkg-sus');
            $this->assertSame(200, $accepted['status']);
            $data = $accepted['body']['data'] ?? null;
            $this->assertIsArray($data);
            $requestId = $data['request_id'] ?? '';
            $this->assertIsString($requestId);
            $this->finishPackageRequest($ops, $requestId);
            self::$conn->executeStatement(
                "INSERT INTO sand_system_plugin_extension (plugin_code, kind, extension_key, enabled, sort)"
                . " VALUES ('flow', 'workbench_card', 'flow.pending', false, 3)",
            );
            self::$conn->executeStatement(
                "INSERT INTO sand_system_schedule (code, plugin_code, enabled, next_run_time)"
                . " VALUES ('flow.tick', 'flow', true, CURRENT_TIMESTAMP)",
            );
            $removed = $ops->uninstall('flow', false, $owner, 'req-pkg-sus-off');
            $this->assertSame(200, $removed['status']);
            $off = $removed['body']['data'] ?? null;
            $this->assertIsArray($off);
            $offId = $off['request_id'] ?? '';
            $this->assertIsString($offId);
            $this->finishPackageRequest($ops, $offId);
            $this->assertSame(1, $this->rowsMatching(
                "SELECT COUNT(*) FROM sand_system_plugin_extension WHERE plugin_code = 'flow' AND suspend_time IS NOT NULL",
            ));
            $this->assertSame(1, $this->rowsMatching(
                "SELECT COUNT(*) FROM sand_system_schedule WHERE code = 'flow.tick' AND suspend_time IS NOT NULL",
            ));
            $again = $ops->install('flow', $owner, 'req-pkg-sus-back');
            $this->assertSame(200, $again['status']);
            $back = $again['body']['data'] ?? null;
            $this->assertIsArray($back);
            $backId = $back['request_id'] ?? '';
            $this->assertIsString($backId);
            $this->finishPackageRequest($ops, $backId);
            $this->assertSame(1, $this->rowsMatching(
                "SELECT COUNT(*) FROM sand_system_plugin_extension WHERE plugin_code = 'flow' AND suspend_time IS NULL",
            ));
            $this->assertSame(1, $this->rowsMatching(
                "SELECT COUNT(*) FROM sand_system_schedule WHERE code = 'flow.tick' AND suspend_time IS NULL",
            ));
        });
    }

    private function finishPackageRequest(PackageOps $ops, string $requestId): void
    {
        for ($step = 0; $step < 6; $step++) {
            $ops->advance($requestId);
            $state = self::$conn->fetchOne(
                'SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?',
                [$requestId],
            );
            if ($state === 'succeeded') {
                return;
            }
        }
        $this->fail('生命周期没有完成');
    }

    private function ownerActor(string $phone, string $username, string $nickname): OrgActor
    {
        $dept = self::$kernel->depts->create(['name' => '甲测' . $username], $this->actor, 'req-' . $username . '-d', '127.0.0.1', null);
        $role = self::$kernel->roles->create(['name' => '甲测' . $username], $this->actor, 'req-' . $username . '-r', '127.0.0.1', null);
        $user = self::$kernel->users->create([
            'nickname' => $nickname,
            'phone' => $phone,
            'username' => $username,
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-' . $username . '-u', '127.0.0.1', null);

        return new OrgActor(OrgSql::int($user['id']), $nickname, null, true, ['*']);
    }

    private function withPackageEnv(string $appEnv, string $compiled, string $ops, callable $work): void
    {
        $saved = [
            'APP_ENV' => getenv('APP_ENV'),
            'SAND_COMPILED' => getenv('SAND_COMPILED'),
            'SAND_PACKAGE_OPS' => getenv('SAND_PACKAGE_OPS'),
        ];
        putenv('APP_ENV=' . $appEnv);
        putenv('SAND_COMPILED=' . $compiled);
        putenv('SAND_PACKAGE_OPS=' . $ops);
        try {
            $work();
        } finally {
            foreach ($saved as $name => $value) {
                if (is_string($value) && $value !== '') {
                    putenv($name . '=' . $value);
                } else {
                    putenv($name);
                }
            }
        }
    }

    private function rowsMatching(string $sql): int
    {
        return OrgSql::int(self::$conn->fetchOne($sql));
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_lifecycle_request WHERE executor_id = 'package-ops' OR target_code IN ('flow', 'host')");
        self::$conn->executeStatement("DELETE FROM sand_system_task_run WHERE created_by IN (SELECT id FROM sand_system_user WHERE phone LIKE '13700009%')");
        self::$conn->executeStatement("DELETE FROM sand_system_file WHERE uploader_id IN (SELECT id FROM sand_system_user WHERE phone LIKE '13700009%')");
        self::$conn->executeStatement("DELETE FROM sand_system_plugin WHERE code = 'flow'");
        self::$conn->executeStatement("DELETE FROM sand_system_setting WHERE key = 'host.version'");
        self::$conn->executeStatement("DELETE FROM sand_system_schedule WHERE code = 'host.log-cleanup'");
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
