<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:37ab83381b7353514761d4e11ad746e34a5f53ba5a4f3b278e844bf7bd45bdd7
// 生成器: contract-build v1

namespace Sandadmin\Tests\Contract\Generated;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\McpToolRegistry;
use Sandadmin\Tests\Contract\ContractAssertions;

/** 整表断言：契约可解析、零冲突、路由表完整、哨兵段完好、工具已登记。 */
final class ContractSurfaceTest extends TestCase
{
    public function testContractParsesClean(): void
    {
        $doc = ContractAssertions::parseHostDoc();
        self::assertSame([], $doc['problems'], implode("\n", $doc['problems']));
    }

    public function testNoConflicts(): void
    {
        $result = ContractAssertions::collectAllDocs();
        self::assertSame([], $result['problems'], implode("\n", $result['problems']));
        self::assertSame([], $result['conflicts'], implode("\n", $result['conflicts']));
    }

    public function testRouteTableComplete(): void
    {
        $expected = [
            'POST /api/admin/v1/files',
            'GET /api/admin/v1/files',
            'GET /api/admin/v1/files/{file_id}',
            'DELETE /api/admin/v1/files/{file_id}',
            'GET /api/admin/v1/files/{file_id}/download',
            'POST /api/admin/v1/files/cleanup',
            'GET /api/admin/v1/tasks',
            'GET /api/admin/v1/tasks/{task_id}',
            'GET /api/admin/v1/tasks/{task_id}/download',
            'GET /api/admin/v1/me/announcements/popup',
            'GET /api/admin/v1/announcements',
            'POST /api/admin/v1/announcements',
            'GET /api/admin/v1/announcements/{announcement_id}',
            'PUT /api/admin/v1/announcements/{announcement_id}',
            'DELETE /api/admin/v1/announcements/{announcement_id}',
            'POST /api/admin/v1/announcements/{announcement_id}/publish',
            'POST /api/admin/v1/announcements/{announcement_id}/revoke',
            'POST /api/admin/v1/announcements/{announcement_id}/read',
            'GET /api/admin/v1/announcements/{announcement_id}/read-stats',
            'GET /api/admin/v1/notifications/unread-count',
            'POST /api/admin/v1/notifications/read-all',
            'GET /api/admin/v1/notifications',
            'POST /api/admin/v1/notifications/{notification_id}/read',
            'GET /api/admin/v1/lifecycle-requests/{request_id}',
            'GET /api/admin/v1/auth/password-policy',
            'POST /api/admin/v1/auth/password/change',
            'GET /api/admin/v1/me',
            'GET /api/admin/v1/me/profile',
            'PUT /api/admin/v1/me/profile',
            'GET /api/admin/v1/me/menus',
            'GET /api/admin/v1/menus/tree',
            'POST /api/admin/v1/menus/groups',
            'POST /api/admin/v1/menus/links',
            'POST /api/admin/v1/menus/reset',
            'PATCH /api/admin/v1/menus/{menu_key}',
            'DELETE /api/admin/v1/menus/{menu_key}',
            'POST /api/admin/v1/menus/{menu_key}/move',
            'PUT /api/admin/v1/menus/{menu_key}/visible',
            'GET /api/admin/v1/dashboard/onboarding',
            'GET /api/admin/v1/dashboard/summary',
            'GET /api/admin/v1/dashboard/todos/all',
            'GET /api/admin/v1/dashboard/todos',
            'GET /api/admin/v1/dashboard/charts',
            'GET /api/admin/v1/dashboard/activities',
            'GET /api/admin/v1/dashboard/notices',
            'GET /api/admin/v1/dashboard/shortcuts',
            'GET /api/admin/v1/users',
            'POST /api/admin/v1/users',
            'POST /api/admin/v1/users/export',
            'GET /api/admin/v1/users/{user_id}',
            'PUT /api/admin/v1/users/{user_id}',
            'DELETE /api/admin/v1/users/{user_id}',
            'PUT /api/admin/v1/users/{user_id}/status',
            'GET /api/admin/v1/users/{user_id}/disable-check',
            'POST /api/admin/v1/users/{user_id}/reset-password',
            'PUT /api/admin/v1/users/{user_id}/roles',
            'GET /api/admin/v1/org/user-options',
            'GET /api/admin/v1/org/dept-options',
            'GET /api/admin/v1/depts/tree',
            'POST /api/admin/v1/depts',
            'PUT /api/admin/v1/depts/{dept_id}',
            'DELETE /api/admin/v1/depts/{dept_id}',
            'GET /api/admin/v1/depts/{dept_id}/delete-check',
            'GET /api/admin/v1/roles',
            'POST /api/admin/v1/roles',
            'GET /api/admin/v1/roles/{role_id}',
            'PUT /api/admin/v1/roles/{role_id}',
            'DELETE /api/admin/v1/roles/{role_id}',
            'POST /api/admin/v1/roles/{role_id}/copy',
            'PUT /api/admin/v1/roles/{role_id}/status',
            'GET /api/admin/v1/roles/{role_id}/permissions',
            'PUT /api/admin/v1/roles/{role_id}/permissions',
            'GET /api/admin/v1/permission-tree',
            'GET /api/admin/v1/posts',
            'POST /api/admin/v1/posts',
            'PUT /api/admin/v1/posts/{post_id}',
            'DELETE /api/admin/v1/posts/{post_id}',
            'GET /api/admin/v1/dicts',
            'POST /api/admin/v1/dicts',
            'GET /api/admin/v1/dicts/{type_id}',
            'PUT /api/admin/v1/dicts/{type_id}',
            'DELETE /api/admin/v1/dicts/{type_id}',
            'POST /api/admin/v1/dicts/{type_id}/items',
            'PUT /api/admin/v1/dicts/items/sort',
            'PUT /api/admin/v1/dicts/items/{item_id}',
            'DELETE /api/admin/v1/dicts/items/{item_id}',
            'PUT /api/admin/v1/dicts/items/{item_id}/status',
            'GET /api/admin/v1/plugins/overview',
            'GET /api/admin/v1/plugins/market',
            'GET /api/admin/v1/plugins/installed',
            'POST /api/admin/v1/plugins/upgrade-all',
            'POST /api/admin/v1/plugins/upload',
            'GET /api/admin/v1/plugins/{plugin_code}',
            'POST /api/admin/v1/plugins/{plugin_code}/install',
            'POST /api/admin/v1/plugins/{plugin_code}/upgrade',
            'POST /api/admin/v1/plugins/{plugin_code}/uninstall',
            'GET /api/admin/v1/system/update/status',
            'POST /api/admin/v1/system/update/check',
            'GET /api/admin/v1/system/update/probe',
            'POST /api/admin/v1/system/update/start',
            'PUT /api/admin/v1/scheduled-tasks/{code}',
        ];
        $actual = ContractAssertions::listGeneratedRoutes();
        foreach ($expected as $route) {
            self::assertArrayHasKey($route, $actual, "路由 {$route} 不在 route.generated.php");
        }
        self::assertCount(count($expected), $actual, 'route.generated.php 路由数与契约不一致');
    }

    public function testRouteShellIntact(): void
    {
        // 哨兵结构断言归 tools/check-dev-refs.php（构建器不感知哨兵）
        require_once ContractAssertions::repoRoot() . '/tools/check-dev-refs.php';
        self::assertSame([], \checkRouteShell(ContractAssertions::repoRoot()));
    }

    public function testMcpToolsDeclared(): void
    {
        $names = [];
        foreach (McpToolRegistry::specs() as $spec) {
            $names[] = $spec['name'];
        }
        sort($names);
        $expected = [
            'dev_inspect_routes',
            'dev_recent_errors',
            'dev_run_phpunit',
            'dev_run_pre_merge',
        ];
        sort($expected);
        self::assertSame($expected, $names);
    }
}
