<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:c87c36392839f8b51ff28182ccbf46337615fd10fed811e05e33e3994c1f309f
// 生成器: contract-build v1

namespace Sandadmin\Tests\Contract\Generated;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Contract\Generated\PermissionRegistry;
use Sandadmin\Tests\Contract\ContractAssertions;

/** 机械断言：路由注册、权限声明、错误码登记必跑；成功路径不假绿。 */
final class DashboardContractTest extends TestCase
{
    /** @return list<array{0: string, 1: string, 2: string}> */
    public static function operationProvider(): array
    {
        return [
            ['dashboardOnboarding', 'GET', '/api/admin/v1/dashboard/onboarding'],
            ['dashboardSummary', 'GET', '/api/admin/v1/dashboard/summary'],
            ['dashboardAllTodos', 'GET', '/api/admin/v1/dashboard/todos/all'],
            ['dashboardTodos', 'GET', '/api/admin/v1/dashboard/todos'],
            ['dashboardCharts', 'GET', '/api/admin/v1/dashboard/charts'],
            ['dashboardActivities', 'GET', '/api/admin/v1/dashboard/activities'],
            ['dashboardNotices', 'GET', '/api/admin/v1/dashboard/notices'],
            ['dashboardShortcuts', 'GET', '/api/admin/v1/dashboard/shortcuts'],
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
        ];
        // array_diff 断言在空清单时也产生断言（防 risky）
        self::assertSame([], array_diff($codes, PermissionRegistry::CODES),
            '存在未进注册表的权限码');
    }

    public function testErrorCodesRegistered(): void
    {
        $codes = [
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
