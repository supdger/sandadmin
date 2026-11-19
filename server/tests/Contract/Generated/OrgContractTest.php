<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:0e05c7423b5b7fd78d046ae957a741380629f10e683c91907c04c36be6a890ca
// 生成器: contract-build v1

namespace Sandadmin\Tests\Contract\Generated;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Contract\Generated\PermissionRegistry;
use Sandadmin\Tests\Contract\ContractAssertions;

/** 机械断言：路由注册、权限声明、错误码登记必跑；成功路径不假绿。 */
final class OrgContractTest extends TestCase
{
    /** @return list<array{0: string, 1: string, 2: string}> */
    public static function operationProvider(): array
    {
        return [
            ['usersIndex', 'GET', '/api/admin/v1/users'],
            ['usersStore', 'POST', '/api/admin/v1/users'],
            ['usersExport', 'POST', '/api/admin/v1/users/export'],
            ['usersShow', 'GET', '/api/admin/v1/users/{user_id}'],
            ['usersUpdate', 'PUT', '/api/admin/v1/users/{user_id}'],
            ['usersDestroy', 'DELETE', '/api/admin/v1/users/{user_id}'],
            ['usersStatus', 'PUT', '/api/admin/v1/users/{user_id}/status'],
            ['usersDisableCheck', 'GET', '/api/admin/v1/users/{user_id}/disable-check'],
            ['usersResetPassword', 'POST', '/api/admin/v1/users/{user_id}/reset-password'],
            ['usersRoles', 'PUT', '/api/admin/v1/users/{user_id}/roles'],
            ['orgUserOptions', 'GET', '/api/admin/v1/org/user-options'],
            ['orgDeptOptions', 'GET', '/api/admin/v1/org/dept-options'],
            ['deptsTree', 'GET', '/api/admin/v1/depts/tree'],
            ['deptsStore', 'POST', '/api/admin/v1/depts'],
            ['deptsUpdate', 'PUT', '/api/admin/v1/depts/{dept_id}'],
            ['deptsDestroy', 'DELETE', '/api/admin/v1/depts/{dept_id}'],
            ['deptsDeleteCheck', 'GET', '/api/admin/v1/depts/{dept_id}/delete-check'],
            ['rolesIndex', 'GET', '/api/admin/v1/roles'],
            ['rolesStore', 'POST', '/api/admin/v1/roles'],
            ['rolesShow', 'GET', '/api/admin/v1/roles/{role_id}'],
            ['rolesUpdate', 'PUT', '/api/admin/v1/roles/{role_id}'],
            ['rolesDestroy', 'DELETE', '/api/admin/v1/roles/{role_id}'],
            ['rolesCopy', 'POST', '/api/admin/v1/roles/{role_id}/copy'],
            ['rolesStatus', 'PUT', '/api/admin/v1/roles/{role_id}/status'],
            ['rolesPermissions', 'GET', '/api/admin/v1/roles/{role_id}/permissions'],
            ['rolesSavePermissions', 'PUT', '/api/admin/v1/roles/{role_id}/permissions'],
            ['rolesPermissionTree', 'GET', '/api/admin/v1/permission-tree'],
            ['postsIndex', 'GET', '/api/admin/v1/posts'],
            ['postsStore', 'POST', '/api/admin/v1/posts'],
            ['postsUpdate', 'PUT', '/api/admin/v1/posts/{post_id}'],
            ['postsDestroy', 'DELETE', '/api/admin/v1/posts/{post_id}'],
        ];
    }

    #[DataProvider('operationProvider')]
    public function testRoutesRegistered(string $operationId, string $method, string $path): void
    {
        ContractAssertions::assertRouteRegistered($method, $path, $operationId);
    }

    public function testPermissionsDeclared(): void
    {
        $codes = [
            'system:user:index',
            'system:user:create',
            'system:user:export',
            'system:user:update',
            'system:user:delete',
            'system:user:reset-password',
            'system:user:assign-role',
            'system:dept:index',
            'system:dept:create',
            'system:dept:update',
            'system:dept:delete',
            'system:role:index',
            'system:role:create',
            'system:role:update',
            'system:role:delete',
            'system:role:permission:update',
            'system:post:index',
            'system:post:create',
            'system:post:update',
            'system:post:delete',
        ];
        // array_diff 断言在空清单时也产生断言（防 risky）
        self::assertSame([], array_diff($codes, PermissionRegistry::CODES),
            '存在未进注册表的权限码');
    }

    public function testErrorCodesRegistered(): void
    {
        $codes = [
            'USERNAME_TAKEN',
            'IDEMPOTENCY_KEY_REUSED',
            'USER_NOT_FOUND',
            'NOT_FOUND',
            'STALE_VERSION',
            'LAST_ADMIN',
            'USER_DISABLE_BLOCKED',
            'DEPT_NAME_TAKEN',
            'DEPT_NOT_FOUND',
            'DEPT_NOT_EMPTY',
            'ROLE_NAME_TAKEN',
            'DEPT_REQUIRED',
            'DATA_SCOPE_INVALID',
            'SYSTEM_ROLE_READONLY',
            'SELF_LOCKOUT',
            'POST_NAME_TAKEN',
            'POST_NOT_FOUND',
        ];
        self::assertSame([], array_diff($codes, ErrorCodeRegistry::ALL),
            '存在未进注册表的错误码');
    }

    #[DataProvider('operationProvider')]
    public function testSuccessPath(string $operationId, string $method, string $path): void
    {
        self::markTestIncomplete("TODO(契约测试): {$operationId} {$method} {$path} 成功路径待补夹具");
    }
}
