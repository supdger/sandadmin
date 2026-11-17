<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Menu;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Idempotency\ConnectionIdempotencyStore;
use Sandadmin\Application\Plugin\PluginManifestLoader;
use Sandadmin\Module\Menu\MenuService;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;

/** 插件菜单收集点：installed 插件按 manifest 声明展开，无声明保持单自动页。 */
final class PluginMenuTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/plugin';
    private const TMP = __DIR__ . '/../../../tmp/menu-plugin';

    private Connection $conn;
    private OrgActor $super;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->schema();
        $this->super = new OrgActor(1, '超管', null, true, ['*']);
        self::clearDir(self::TMP);
        mkdir(self::TMP, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->conn->close();
        self::clearDir(self::TMP);
    }

    private function schema(): void
    {
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_plugin (code varchar(64) PRIMARY KEY, name varchar(128) NOT NULL, state varchar(32) NOT NULL)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_menu_custom (menu_key varchar(128) PRIMARY KEY, kind varchar(16) NOT NULL,
                title varchar(64) NOT NULL, icon varchar(64) NULL, parent_key varchar(128) NULL,
                url varchar(2048) NULL, sort int NOT NULL DEFAULT 0, visible boolean NOT NULL DEFAULT true)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_menu_override (menu_key varchar(128) PRIMARY KEY, title varchar(64) NULL,
                icon varchar(64) NULL, parent_key varchar(128) NULL, sort int NULL, visible boolean NULL)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_setting (key varchar(64) PRIMARY KEY, value text NULL, group_code varchar(32) NULL)',
        );
    }

    private function install(string $code, string $name, string $state = 'installed'): void
    {
        $this->conn->insert('sand_system_plugin', ['code' => $code, 'name' => $name, 'state' => $state]);
    }

    private function service(?string $dir = null): MenuService
    {
        return new MenuService(
            $this->conn,
            new OrgAudit($this->conn),
            new OrgIdempotency(new ConnectionIdempotencyStore($this->conn)),
            new PluginManifestLoader($dir ?? self::FIXTURES, static function (string $message): void {
            }),
        );
    }

    /**
     * @param array<mixed> $items
     *
     * @return array<string, mixed>|null
     */
    private function find(array $items, string $key): ?array
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($item as $name => $value) {
                if (is_string($name)) {
                    $row[$name] = $value;
                }
            }
            if (($row['key'] ?? null) === $key) {
                return $row;
            }
            $children = $row['children'] ?? null;
            if (is_array($children)) {
                $found = $this->find($children, $key);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $items
     *
     * @return array<string, mixed>
     */
    private function node(array $items, string $key): array
    {
        $found = $this->find($items, $key);
        if ($found === null) {
            self::fail('未找到菜单节点 ' . $key);
        }

        return $found;
    }

    private static function clearDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }

    public function testDeclaredMenusProduceGroupAndPages(): void
    {
        $this->install('sand-hello', '你好插件');
        $items = $this->service()->sidebarItems($this->super);

        $group = $this->node($items, 'plugin:sand-hello');
        self::assertSame('group', $group['type']);
        self::assertSame('你好插件', $group['title']);
        self::assertSame('sand-hello', $group['plugin']);

        $home = $this->node($items, 'plugin:sand-hello:home');
        self::assertSame('page', $home['type']);
        self::assertSame('你好示例', $home['title']);
        self::assertSame('/plugin/sand-hello', $home['path']);
        self::assertSame('apps', $home['icon']);
        self::assertSame('menu.home', $home['title_key']);
        self::assertSame('sand_hello:page:read', $home['permission']);
        self::assertSame('sand-hello', $home['plugin']);

        $more = $this->node($items, 'plugin:sand-hello:more');
        self::assertSame('group', $more['type']);
        $about = $this->node($items, 'plugin:sand-hello:more:about');
        self::assertSame('page', $about['type']);
        self::assertSame('/plugin/sand-hello/more/about', $about['path']);
    }

    public function testUndeclaredPluginKeepsSinglePage(): void
    {
        $this->install('sand-ghost', '幽灵插件');
        $items = $this->service()->sidebarItems($this->super);
        $page = $this->node($items, 'plugin:sand-ghost');
        self::assertSame('page', $page['type']);
        self::assertSame('幽灵插件', $page['title']);
        self::assertSame('/plugin/sand-ghost', $page['path']);
    }

    public function testBadManifestFallsBackToSinglePage(): void
    {
        mkdir(self::TMP . '/sand-bad', 0777, true);
        copy(self::FIXTURES . '/sand-hello/manifest-bad.json', self::TMP . '/sand-bad/manifest.json');
        $this->install('sand-bad', '坏清单插件');
        $items = $this->service(self::TMP)->sidebarItems($this->super);
        $page = $this->node($items, 'plugin:sand-bad');
        self::assertSame('page', $page['type']);
    }

    public function testUninstalledPluginHidden(): void
    {
        $this->install('sand-hello', '你好插件', 'uninstalled');
        $items = $this->service()->sidebarItems($this->super);
        self::assertNull($this->find($items, 'plugin:sand-hello'));
    }

    public function testPermissionFiltersDeclaredItems(): void
    {
        $this->install('sand-hello', '你好插件');
        $viewer = new OrgActor(2, '无权限', null, false, ['system:user:read']);
        $items = $this->service()->sidebarItems($viewer);

        self::assertNull($this->find($items, 'plugin:sand-hello:home'));
        // 无权限声明的嵌套页仍可见，分组保留
        self::assertNotNull($this->find($items, 'plugin:sand-hello:more:about'));

        $granted = new OrgActor(3, '有权限', null, false, ['sand_hello:page:read']);
        $items = $this->service()->sidebarItems($granted);
        $home = $this->node($items, 'plugin:sand-hello:home');
        self::assertSame('sand_hello:page:read', $home['permission']);
    }

    public function testEmptyGroupDroppedWhenAllChildrenFiltered(): void
    {
        mkdir(self::TMP . '/sand-closed', 0777, true);
        file_put_contents(self::TMP . '/sand-closed/manifest.json', json_encode([
            'menus' => [['key' => 'home', 'path' => '/plugin/sand-closed', 'title' => '闭门', 'permission' => 'sand_closed:page:read']],
        ]));
        $this->install('sand-closed', '闭门插件');
        $viewer = new OrgActor(2, '无权限', null, false, []);
        $items = $this->service(self::TMP)->sidebarItems($viewer);
        self::assertNull($this->find($items, 'plugin:sand-closed'));
    }

    public function testCatalogTreeShowsGroupAndSource(): void
    {
        $this->install('sand-hello', '你好插件');
        $tree = $this->service()->loadTree(null, null);
        $group = $this->node($tree['items'], 'plugin:sand-hello');
        self::assertSame('group', $group['type']);
        $source = $group['source'] ?? null;
        if (!is_array($source)) {
            self::fail('插件分组缺 source');
        }
        self::assertSame('plugin', $source['kind']);
        self::assertSame('你好插件', $source['plugin_label']);
        // 目录视图不过滤：permission 项也在
        $home = $this->node($tree['items'], 'plugin:sand-hello:home');
        self::assertSame('sand_hello:page:read', $home['permission']);
    }

    public function testSystemGroupHoldsPluginNodes(): void
    {
        $this->install('sand-hello', '你好插件');
        $items = $this->service()->sidebarItems($this->super);
        $system = $this->node($items, 'system');
        self::assertNotNull($this->find([$system], 'plugin:sand-hello'));
        self::assertNotNull($this->find([$system], 'system:plugin'));
    }
}
