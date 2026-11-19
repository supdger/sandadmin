<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Menu;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Idempotency\ConnectionIdempotencyStore;
use Sandadmin\Application\Plugin\PluginManifestLoader;
use Sandadmin\Module\Menu\MenuService;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;

/**
 * 插件菜单收集点：installed 插件按 manifest 声明展开，无声明保持单自动页。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL；
 * 插件行按 code 清理，菜单只读不写。
 */
final class PluginMenuTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/plugin';
    private const TMP = __DIR__ . '/../../../tmp/menu-plugin';
    private const PLUGIN_CODES = ['sand-hello', 'sand-ghost', 'sand-bad', 'sand-closed'];

    private static Connection $conn;
    private OrgActor $super;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::wipe();
            self::$conn->close();
        }
        self::clearDir(self::TMP);
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement(
            'DELETE FROM sand_system_plugin WHERE code IN (?, ?, ?, ?)',
            self::PLUGIN_CODES,
        );
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->super = new OrgActor(1, '超管', null, true, ['*']);
        self::clearDir(self::TMP);
        mkdir(self::TMP, 0777, true);
    }

    protected function tearDown(): void
    {
        self::clearDir(self::TMP);
    }

    private function install(string $code, string $name, string $state = 'installed'): void
    {
        self::$conn->insert('sand_system_plugin', [
            'code' => $code,
            'name' => $name,
            'version' => '1.0.0',
            'host_constraint' => '^1.0',
            'manifest_sha256' => hash('sha256', $code),
            'state' => $state,
        ]);
    }

    private function service(?string $dir = null): MenuService
    {
        return new MenuService(
            self::$conn,
            new OrgAudit(self::$conn),
            new OrgIdempotency(new ConnectionIdempotencyStore(self::$conn)),
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
