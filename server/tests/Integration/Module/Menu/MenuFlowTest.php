<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Menu;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Http\BizKernel;
use Sandadmin\Module\Menu\MenuException;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;

/** 菜单分组和外链要落库；失败必须是 4xx，不能是成功。 */
final class MenuFlowTest extends TestCase
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

    public function testGroupAndLinkAreStored(): void
    {
        $group = self::$kernel->menus->createGroup(
            ['title' => '测分组'],
            $this->actor,
            'req-m1',
            '127.0.0.1',
            null,
            null,
        );
        $groupKey = $group['key'];
        $this->assertNotSame('', $groupKey);
        $stored = OrgSql::int(self::$conn->fetchOne(
            'SELECT COUNT(*) FROM sand_system_menu_custom WHERE menu_key = ? AND kind = \'group\' AND title = \'测分组\'',
            [$groupKey],
        ));
        $this->assertSame(1, $stored);

        $link = self::$kernel->menus->createLink(
            ['title' => '测链接', 'url' => 'https://example.com/a', 'parent_key' => $groupKey],
            $this->actor,
            'req-m2',
            '127.0.0.1',
            null,
            null,
        );
        $linkKey = $link['key'];
        $this->assertSame(1, OrgSql::int(self::$conn->fetchOne(
            'SELECT COUNT(*) FROM sand_system_menu_custom WHERE menu_key = ? AND kind = \'link\' AND parent_key = ?',
            [$linkKey, $groupKey],
        )));

        $tree = self::$kernel->menus->loadTree(null, null);
        $this->assertGreaterThan(0, count($tree['items']));
        $foundGroup = false;
        foreach ($tree['items'] as $item) {
            if (($item['key'] ?? null) === $groupKey) {
                $foundGroup = true;
                $children = $item['children'] ?? [];
                $this->assertIsArray($children);
                $titles = [];
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $titles[] = $child['title'] ?? null;
                    }
                }
                $this->assertContains('测链接', $titles);
            }
        }
        $this->assertTrue($foundGroup);

        self::$kernel->menus->updateNode($groupKey, ['title' => '测分组改'], $this->actor, 'req-m3', '127.0.0.1', null);
        $this->assertSame('测分组改', OrgSql::str(self::$conn->fetchOne(
            'SELECT title FROM sand_system_menu_custom WHERE menu_key = ?',
            [$groupKey],
        )));

        self::$kernel->menus->removeNode($linkKey, $this->actor, 'req-m4', '127.0.0.1', null);
        self::$kernel->menus->removeNode($groupKey, $this->actor, 'req-m5', '127.0.0.1', null);
        $this->assertSame(0, OrgSql::int(self::$conn->fetchOne(
            'SELECT COUNT(*) FROM sand_system_menu_custom WHERE menu_key IN (?, ?)',
            [$groupKey, $linkKey],
        )));
    }

    public function testRenameChecksBaseVersion(): void
    {
        $group = self::$kernel->menus->createGroup(
            ['title' => '测并发组'],
            $this->actor,
            'req-v1',
            '127.0.0.1',
            null,
            null,
        );
        $version = OrgSql::int(self::$kernel->menus->loadTree(null, null)['menu_version']);
        $this->assertGreaterThan(0, $version);

        $saved = self::$kernel->menus->updateNode(
            $group['key'],
            ['title' => '测并发组改', 'base_version' => $version],
            $this->actor,
            'req-v2',
            '127.0.0.1',
            null,
        );
        $this->assertSame($version + 1, $saved['menu_version']);

        try {
            self::$kernel->menus->updateNode(
                $group['key'],
                ['title' => '测并发再改', 'base_version' => $version],
                $this->actor,
                'req-v3',
                '127.0.0.1',
                null,
            );
            $this->fail('旧菜单版本保存应被拒绝');
        } catch (MenuException $error) {
            $this->assertSame(409, $error->status);
            $this->assertSame('STALE_VERSION', $error->errorCode);
        }
        $this->assertSame('测并发组改', OrgSql::str(self::$conn->fetchOne(
            'SELECT title FROM sand_system_menu_custom WHERE menu_key = ?',
            [$group['key']],
        )));
    }

    public function testInvalidUrlAndSystemDeleteAreNotSuccess(): void
    {
        try {
            self::$kernel->menus->createLink(
                ['title' => '坏链接', 'url' => 'javascript:alert(1)', 'parent_key' => 'system'],
                $this->actor,
                'req-m6',
                '127.0.0.1',
                null,
                null,
            );
            $this->fail('非法链接应被拒绝');
        } catch (MenuException $error) {
            $this->assertSame(422, $error->status);
            $this->assertNotSame(200, $error->status);
            $this->assertSame('MENU_URL_INVALID', $error->errorCode);
        }
        $this->assertSame(0, OrgSql::int(self::$conn->fetchOne(
            "SELECT COUNT(*) FROM sand_system_menu_custom WHERE title = '坏链接'",
        )));

        try {
            self::$kernel->menus->removeNode('system:user', $this->actor, 'req-m7', '127.0.0.1', null);
            $this->fail('系统菜单不能删除');
        } catch (MenuException $error) {
            $this->assertSame(409, $error->status);
            $this->assertNotSame(200, $error->status);
            $this->assertSame('MENU_LOCKED', $error->errorCode);
        }

        try {
            self::$kernel->menus->removeNode('missing-menu', $this->actor, 'req-m8', '127.0.0.1', null);
            $this->fail('不存在的菜单应是 404');
        } catch (MenuException $error) {
            $this->assertSame(404, $error->status);
        }
    }

    public function testSidebarAppliesRenameAndHide(): void
    {
        self::$kernel->menus->updateNode('system:announcement', ['title' => '测公告'], $this->actor, 'req-s1', '127.0.0.1', null);
        self::$kernel->menus->updateNode('system:plugin', ['visible' => false], $this->actor, 'req-s2', '127.0.0.1', null);

        $items = self::$kernel->menus->mergeSidebar(self::sidebarItems());
        $titles = self::sidebarTitles($items);
        $this->assertContains('测公告', $titles);
        $this->assertNotContains('公告管理', $titles);
        $this->assertNotContains('插件管理', $titles);
        $this->assertContains('用户管理', $titles);
    }

    /**
     * 与 MeController::items 同构的最小侧栏清单。
     *
     * @return list<array<string, mixed>>
     */
    private static function sidebarItems(): array
    {
        return [
            self::sidebarPage('home', '工作台', '/'),
            [
                'key' => 'system',
                'type' => 'group',
                'title' => '系统',
                'icon' => 'setting',
                'path' => null,
                'url' => null,
                'children' => [
                    self::sidebarPage('system:user', '用户管理', '/system/user'),
                    self::sidebarPage('system:announcement', '公告管理', '/data/notice'),
                    self::sidebarPage('system:plugin', '插件管理', '/system/plugins'),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function sidebarPage(string $key, string $title, string $path): array
    {
        return ['key' => $key, 'type' => 'page', 'title' => $title, 'icon' => null, 'path' => $path, 'url' => null];
    }

    /**
     * @param array<mixed> $items
     *
     * @return list<string>
     */
    private static function sidebarTitles(array $items): array
    {
        $titles = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $titles[] = OrgSql::str($item['title'] ?? null);
            $children = $item['children'] ?? null;
            if (is_array($children)) {
                foreach (self::sidebarTitles($children) as $childTitle) {
                    $titles[] = $childTitle;
                }
            }
        }

        return $titles;
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_menu_custom WHERE menu_key LIKE 'custom:%' OR title LIKE '测%' OR title LIKE '坏%'");
        self::$conn->executeStatement("DELETE FROM sand_system_menu_override WHERE title LIKE '测%' OR menu_key = 'system:plugin'");
        self::$conn->executeStatement("DELETE FROM sand_system_audit_log WHERE module = 'menu' AND actor_name = '测试账号'");
    }
}
