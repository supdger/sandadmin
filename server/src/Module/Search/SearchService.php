<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Search;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;

/**
 * 全局搜索：功能（菜单）+ 用户两组结果。
 * 功能只含当前用户有权限且未被隐藏的页面（隐藏判定与 /me/menus 同一
 * mergeSidebar 管线）；用户组需要 system:user:index 权限。
 * 拼音首字母匹配本版不做（无可用拼音库），名称/编码包含即可命中。
 */
final class SearchService
{
    private const MENU_LIMIT = 6;
    private const USER_LIMIT = 5;

    /** key → [标题, 路径, 权限码, 分组名]；与 MeController::items 的菜单同源补齐。 */
    private const PAGES = [
        'system:user' => ['用户管理', '/system/user', 'system:user:index', '组织与用户'],
        'system:dept' => ['部门管理', '/system/dept', 'system:dept:index', '组织与用户'],
        'system:post' => ['岗位管理', '/system/post', 'system:post:index', '组织与用户'],
        'system:role' => ['角色管理', '/system/role', 'system:role:index', '组织与用户'],
        'system:dict' => ['选项管理', '/data/dict', 'system:dict:index', '内容'],
        'system:announcement' => ['公告管理', '/data/notice', 'system:announcement:index', '内容'],
        'system:menu' => ['菜单管理', '/system/menu', 'system:menu:index', '系统'],
        'system:plugin' => ['插件管理', '/system/plugins', 'system:plugin:index', '系统'],
        'system:update' => ['系统更新', '/system/update', 'system:update:index', '系统'],
        'system:file' => ['文件管理', '/data/file', 'system:file:index', '系统'],
        'system:monitor' => ['运行状态', '/system/monitor', 'system:monitor:index', '系统'],
        'system:cron' => ['定时任务', '/system/cron', 'system:cron:index', '系统'],
        'system:login-log' => ['登录日志', '/system/login-log', 'system:login-log:index', '系统'],
        'system:audit-log' => ['操作日志', '/system/oper-log', 'system:audit-log:index', '系统'],
        'system:config' => ['系统设置', '/system/settings', 'system:config:index', '系统'],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{groups: list<array{type: string, label: string, items: list<array<string, mixed>>}>} */
    public function search(OrgActor $actor, string $keyword, ?string $types): array
    {
        $wanted = $this->types($types);
        $groups = [];
        if (in_array('menu', $wanted, true)) {
            try {
                $items = $this->menuItems($actor, $keyword);
                if ($items !== []) {
                    $groups[] = ['type' => 'menu', 'label' => '功能', 'items' => $items];
                }
            } catch (\Throwable $error) {
                error_log('全局搜索功能源失败 ' . $error::class . ': ' . $error->getMessage());
            }
        }
        if (in_array('user', $wanted, true) && $actor->can('system:user:index')) {
            try {
                $items = $this->userItems($keyword);
                if ($items !== []) {
                    $groups[] = ['type' => 'user', 'label' => '用户', 'items' => $items];
                }
            } catch (\Throwable $error) {
                error_log('全局搜索用户源失败 ' . $error::class . ': ' . $error->getMessage());
            }
        }

        return ['groups' => $groups];
    }

    /** @return list<array<string, mixed>> */
    private function menuItems(OrgActor $actor, string $keyword): array
    {
        $hidden = $this->hiddenKeys();
        $installed = $this->installedPlugins();
        $items = [];
        foreach (self::PAGES as $key => $page) {
            if (isset($hidden[$key]) || !$actor->can($page[2])) {
                continue;
            }
            if (mb_stripos($page[0], $keyword) === false && mb_stripos($key, $keyword) === false) {
                continue;
            }
            $items[] = [
                'id' => $key,
                'title' => $page[0],
                'subtitle' => $page[3] . ' › ' . $page[0],
                'link' => $page[1],
            ];
        }
        foreach ($installed as $code => $name) {
            $key = 'plugin:' . $code;
            if (isset($hidden[$key]) || (mb_stripos($name, $keyword) === false && mb_stripos($code, $keyword) === false)) {
                continue;
            }
            $items[] = [
                'id' => $key,
                'title' => $name,
                'subtitle' => '插件 › ' . $name,
                'link' => '/plugin/' . $code,
            ];
        }
        // 自建菜单（custom link）参与搜索：取菜单合并树里可见的链接节点
        foreach ($this->customLinks() as $link) {
            if (isset($hidden[$link['key']]) || mb_stripos($link['title'], $keyword) === false) {
                continue;
            }
            $items[] = [
                'id' => $link['key'],
                'title' => $link['title'],
                'subtitle' => $link['parent'] . ' › ' . $link['title'],
                'link' => $link['url'],
            ];
        }

        return array_slice($items, 0, self::MENU_LIMIT);
    }

    /** @return list<array<string, mixed>> */
    private function userItems(string $keyword): array
    {
        $like = '%' . addcslashes($keyword, '%_\\') . '%';
        $phone4 = preg_match('/^\d{4}$/', $keyword) === 1 ? $keyword : null;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.id, u.nickname, u.username, d.name AS dept_name'
            . ' FROM sand_system_user u LEFT JOIN sand_system_dept d ON d.id = u.dept_id'
            . ' WHERE u.delete_time IS NULL AND (u.nickname ILIKE :kw OR u.username ILIKE :kw'
            . ($phone4 !== null ? ' OR right(u.phone, 4) = :p4' : '') . ')'
            . ' ORDER BY u.id LIMIT ' . self::USER_LIMIT,
            $phone4 !== null ? ['kw' => $like, 'p4' => $phone4] : ['kw' => $like],
        );
        $items = [];
        foreach ($rows as $row) {
            $nickname = OrgSql::str($row['nickname'] ?? null);
            $items[] = [
                'id' => (string) OrgSql::int($row['id'] ?? null),
                'title' => $nickname !== '' ? $nickname : OrgSql::str($row['username'] ?? null),
                'subtitle' => OrgSql::str($row['dept_name'] ?? '') !== '' ? OrgSql::str($row['dept_name']) : null,
                'link' => '/system/user?user_id=' . OrgSql::int($row['id'] ?? null),
            ];
        }

        return $items;
    }

    /** @return array<string, true> 被覆盖层或自建隐藏标记的菜单键 */
    private function hiddenKeys(): array
    {
        $hidden = [];
        foreach ($this->connection->fetchAllKeyValue("SELECT menu_key, visible FROM sand_system_menu_override") as $key => $visible) {
            if ($visible === false || $visible === 'f' || $visible === 0 || $visible === '0') {
                $hidden[(string) $key] = true;
            }
        }
        foreach ($this->connection->fetchAllKeyValue("SELECT menu_key, visible FROM sand_system_menu_custom") as $key => $visible) {
            if ($visible === false || $visible === 'f' || $visible === 0 || $visible === '0') {
                $hidden[(string) $key] = true;
            }
        }

        return $hidden;
    }

    /** @return array<string, string> 已装插件编码 → 中文名 */
    private function installedPlugins(): array
    {
        $out = [];
        $rows = $this->connection->fetchAllAssociative(
            "SELECT code, name FROM sand_system_plugin WHERE state = 'installed' ORDER BY name",
        );
        foreach ($rows as $row) {
            $code = OrgSql::str($row['code'] ?? null);
            $name = OrgSql::str($row['name'] ?? null);
            if ($code !== '' && $name !== '') {
                $out[$code] = $name;
            }
        }

        return $out;
    }

    /** @return list<array{key: string, title: string, url: string, parent: string}> 可见的自建链接菜单 */
    private function customLinks(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.menu_key, c.title, c.url, c.visible, p.title AS parent_title"
            . ' FROM sand_system_menu_custom c LEFT JOIN sand_system_menu_custom p ON p.menu_key = c.parent_key'
            . " WHERE c.kind = 'link' ORDER BY c.sort, c.menu_key",
        );
        $out = [];
        foreach ($rows as $row) {
            if (!self::isOn($row['visible'] ?? true)) {
                continue;
            }
            $url = OrgSql::str($row['url'] ?? null);
            $title = OrgSql::str($row['title'] ?? null);
            if ($url === '' || $title === '') {
                continue;
            }
            $out[] = [
                'key' => OrgSql::str($row['menu_key'] ?? null),
                'title' => $title,
                'url' => $url,
                'parent' => OrgSql::str($row['parent_title'] ?? '') !== '' ? OrgSql::str($row['parent_title']) : '自定义',
            ];
        }

        return $out;
    }

    /** @return list<string> types 逗号拆出的来源类型；空为全部 */
    private function types(?string $types): array
    {
        if ($types === null || trim($types) === '') {
            return ['menu', 'user'];
        }
        $out = [];
        foreach (explode(',', $types) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out === [] ? ['menu', 'user'] : $out;
    }

    private static function isOn(mixed $value): bool
    {
        return !($value === false || $value === 'f' || $value === 0 || $value === '0');
    }
}
