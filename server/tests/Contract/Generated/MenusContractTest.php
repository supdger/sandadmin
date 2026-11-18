<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:cf82ee2f6dbd21796934cc4f544ba247c1bbfc8594af44eb80669fad02a37fc2
// 生成器: contract-build v1

namespace Sandadmin\Tests\Contract\Generated;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Contract\Generated\PermissionRegistry;
use Sandadmin\Tests\Contract\ContractAssertions;

/** 机械断言：路由注册、权限声明、错误码登记必跑；成功路径不假绿。 */
final class MenusContractTest extends TestCase
{
    /** @return list<array{0: string, 1: string, 2: string}> */
    public static function operationProvider(): array
    {
        return [
            ['menusTree', 'GET', '/api/admin/v1/menus/tree'],
            ['menusStoreGroup', 'POST', '/api/admin/v1/menus/groups'],
            ['menusStoreLink', 'POST', '/api/admin/v1/menus/links'],
            ['menusReset', 'POST', '/api/admin/v1/menus/reset'],
            ['menusUpdate', 'PATCH', '/api/admin/v1/menus/{menu_key}'],
            ['menusDestroy', 'DELETE', '/api/admin/v1/menus/{menu_key}'],
            ['menusMove', 'POST', '/api/admin/v1/menus/{menu_key}/move'],
            ['menusVisible', 'PUT', '/api/admin/v1/menus/{menu_key}/visible'],
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
            'system:menu:index',
            'system:menu:create',
            'system:menu:reset',
            'system:menu:update',
            'system:menu:delete',
        ];
        // array_diff 断言在空清单时也产生断言（防 risky）
        self::assertSame([], array_diff($codes, PermissionRegistry::CODES),
            '存在未进注册表的权限码');
    }

    public function testErrorCodesRegistered(): void
    {
        $codes = [
            'MENU_NOT_FOUND',
            'MENU_DEPTH_EXCEEDED',
            'MENU_LOCKED',
            'MENU_URL_INVALID',
            'STALE_VERSION',
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
