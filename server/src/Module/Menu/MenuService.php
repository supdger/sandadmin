<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Menu;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgSql;

/**
 * 菜单树 = 随包清单 ⊕ 覆盖层 ⊕ 自建分组/外链。
 * 清单不落库；自建写入 sand_system_menu_custom，改清单项写入 sand_system_menu_override。
 * 写入失败抛 MenuException，调用方按状态码返回，不能记成成功。
 */
final class MenuService
{
    private const TITLE_MAX = 6;
    private const URL_MAX = 2048;

    public function __construct(
        private readonly Connection $connection,
        private readonly OrgAudit $audit,
        private readonly OrgIdempotency $keys,
    ) {
    }

    /**
     * @return array{menu_version: int, items: list<array<string, mixed>>}
     */
    public function loadTree(?string $keyword, ?string $source): array
    {
        $items = $this->assemble();

        return [
            'menu_version' => $this->version(),
            'items' => $this->filterNodes($items, $keyword ?? '', $source ?? ''),
        ];
    }

    /**
     * 侧栏 = 覆盖层生效后的清单 ⊕ 可见的自建节点。覆盖层把清单项改名、
     * 换图标、隐藏都在这里生效；自建节点的显隐在 attachCustom 里处理。
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    public function mergeSidebar(array $items): array
    {
        return $this->attachCustom($this->pruneHidden($this->applyOverrides($items)), true);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{key: string}
     */
    public function createGroup(array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device, ?string $idempotencyKey): array
    {
        $title = $this->readTitle($body);
        $icon = $this->readIcon($body);
        $this->assertSiblingFree(null, $title, null);

        $stored = $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'menu.group.create', $body, function () use ($title, $icon, $actor, $requestId, $ip, $device): array {
            $key = 'custom:' . bin2hex(random_bytes(8));
            $this->connection->executeStatement(
                'INSERT INTO sand_system_menu_custom (menu_key, kind, title, icon, parent_key, url, sort, visible, created_by, updated_by)'
                . ' VALUES (:menu_key, \'group\', :title, :icon, NULL, NULL, :sort, true, :by, :by)',
                [
                    'menu_key' => $key,
                    'title' => $title,
                    'icon' => $icon,
                    'sort' => $this->nextSort(null),
                    'by' => $actor->writerId(),
                ],
            );
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'create', '添加了菜单分组' . $title, 'menu', $key, $title, $requestId, $ip, $device);

            return ['key' => $key];
        });

        return ['key' => $this->createdKey($stored)];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{key: string}
     */
    public function createLink(array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device, ?string $idempotencyKey): array
    {
        $title = $this->readTitle($body);
        $icon = $this->readIcon($body);
        $url = $this->readUrl($body);
        $parentKey = $this->textOrNull($body['parent_key'] ?? null);
        $this->assertLinkParent($parentKey);
        $this->assertSiblingFree($parentKey, $title, null);

        $stored = $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'menu.link.create', $body, function () use ($title, $icon, $url, $parentKey, $actor, $requestId, $ip, $device): array {
            $key = 'custom:' . bin2hex(random_bytes(8));
            $this->connection->executeStatement(
                'INSERT INTO sand_system_menu_custom (menu_key, kind, title, icon, parent_key, url, sort, visible, created_by, updated_by)'
                . ' VALUES (:menu_key, \'link\', :title, :icon, :parent_key, :url, :sort, true, :by, :by)',
                [
                    'menu_key' => $key,
                    'title' => $title,
                    'icon' => $icon,
                    'parent_key' => $parentKey,
                    'url' => $url,
                    'sort' => $this->nextSort($parentKey),
                    'by' => $actor->writerId(),
                ],
            );
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'create', '添加了外部链接' . $title, 'menu', $key, $title, $requestId, $ip, $device);

            return ['key' => $key];
        });

        return ['key' => $this->createdKey($stored)];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function updateNode(string $key, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $this->assertVersion($body);
        $custom = $this->customByKey($key);
        if ($custom !== null) {
            $this->updateCustom($key, $custom, $body, $actor, $requestId, $ip, $device);

            return;
        }
        if (!$this->isCatalogKey($key)) {
            throw MenuException::notFound();
        }
        $this->updateOverride($key, $body, $actor, $requestId, $ip, $device);
    }

    public function removeNode(string $key, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $custom = $this->customByKey($key);
        if ($custom === null) {
            if ($this->isCatalogKey($key)) {
                throw MenuException::locked('系统菜单不能删除，只能隐藏');
            }
            throw MenuException::notFound();
        }
        $childCount = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_menu_custom WHERE parent_key = ?',
            [$key],
        ));
        if ($childCount > 0) {
            throw MenuException::invalid([], '先删掉或移走里面的菜单，再删这个分组');
        }
        $title = OrgSql::str($custom['title'] ?? null);
        $this->connection->transactional(function () use ($key, $actor, $title, $requestId, $ip, $device): void {
            $this->connection->executeStatement('DELETE FROM sand_system_menu_custom WHERE menu_key = ?', [$key]);
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'delete', '删除了菜单' . $title, 'menu', $key, $title, $requestId, $ip, $device);
        });
    }

    /**
     * @param array<string, mixed> $body
     */
    public function moveNode(string $key, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $this->assertVersion($body);
        $custom = $this->customByKey($key);
        if ($custom === null) {
            if ($this->isCatalogKey($key)) {
                throw MenuException::locked('系统菜单不能挪位置，只能改名字、图标和显示');
            }
            throw MenuException::notFound();
        }
        $parentKey = array_key_exists('parent_key', $body) ? $this->textOrNull($body['parent_key']) : $this->textOrNull($custom['parent_key'] ?? null);
        $kind = OrgSql::str($custom['kind'] ?? null);
        if ($kind === 'group' && $parentKey !== null) {
            throw MenuException::tooDeep();
        }
        if ($kind === 'link') {
            $this->assertLinkParent($parentKey);
        }
        $index = is_numeric($body['index'] ?? null) ? (int) $body['index'] : 0;
        if ($index < 0) {
            throw MenuException::invalid(['index' => ['排序位置不正确']]);
        }
        $title = OrgSql::str($custom['title'] ?? null);
        $this->connection->transactional(function () use ($key, $parentKey, $index, $actor, $title, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_menu_custom SET parent_key = :parent_key, sort = :sort, updated_by = :by, update_time = CURRENT_TIMESTAMP WHERE menu_key = :menu_key',
                ['parent_key' => $parentKey, 'sort' => $index, 'by' => $actor->writerId(), 'menu_key' => $key],
            );
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'update', '调整了菜单' . $title . '的位置', 'menu', $key, $title, $requestId, $ip, $device);
        });
    }

    /**
     * @param array<string, mixed> $body
     */
    public function setVisible(string $key, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        if (!array_key_exists('visible', $body)) {
            throw MenuException::invalid(['visible' => ['请选择显示还是隐藏']]);
        }
        $this->updateNode($key, ['visible' => $body['visible'], 'base_version' => $body['base_version'] ?? null], $actor, $requestId, $ip, $device);
    }

    /**
     * @return array{menu_version: int}
     */
    public function resetTree(OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $this->connection->transactional(function () use ($actor, $requestId, $ip, $device): void {
            $this->connection->executeStatement('DELETE FROM sand_system_menu_custom');
            $this->connection->executeStatement('DELETE FROM sand_system_menu_override');
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'update', '恢复了菜单默认', 'menu', 'menu', '菜单', $requestId, $ip, $device);
        });

        return ['menu_version' => $this->version()];
    }

    /**
     * @param array<string, mixed> $custom
     * @param array<string, mixed> $body
     */
    private function updateCustom(string $key, array $custom, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $title = array_key_exists('title', $body) ? $this->readTitle($body) : OrgSql::str($custom['title'] ?? null);
        $icon = array_key_exists('icon', $body) ? $this->readIcon($body) : $this->textOrNull($custom['icon'] ?? null);
        $visible = array_key_exists('visible', $body) ? $this->readVisible($body) : $this->isOn($custom['visible'] ?? true);
        if ($title !== OrgSql::str($custom['title'] ?? null)) {
            $this->assertSiblingFree($this->textOrNull($custom['parent_key'] ?? null), $title, $key);
        }
        $this->connection->transactional(function () use ($key, $title, $icon, $visible, $actor, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_menu_custom SET title = :title, icon = :icon, visible = :visible, updated_by = :by, update_time = CURRENT_TIMESTAMP WHERE menu_key = :menu_key',
                [
                    'title' => $title,
                    'icon' => $icon,
                    'visible' => $visible ? 'true' : 'false',
                    'by' => $actor->writerId(),
                    'menu_key' => $key,
                ],
            );
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'update', '保存了菜单' . $title, 'menu', $key, $title, $requestId, $ip, $device);
        });
    }

    /** @param array<string, mixed> $body */
    private function updateOverride(string $key, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $current = $this->overrideByKey($key);
        $catalog = $this->catalogTitle($key);
        $title = array_key_exists('title', $body) ? $this->readTitle($body) : ($this->textOrNull($current['title'] ?? null) ?? $catalog);
        $icon = array_key_exists('icon', $body) ? $this->readIcon($body) : $this->textOrNull($current['icon'] ?? null);
        $visible = array_key_exists('visible', $body) ? $this->readVisible($body) : ($current === [] ? true : $this->isOn($current['visible'] ?? true));
        if ($title === '') {
            throw MenuException::invalid(['title' => ['请填写名称']]);
        }
        $this->connection->transactional(function () use ($key, $title, $icon, $visible, $actor, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'INSERT INTO sand_system_menu_override (menu_key, title, icon, visible) VALUES (:menu_key, :title, :icon, :visible)'
                . ' ON CONFLICT (menu_key) DO UPDATE SET title = EXCLUDED.title, icon = EXCLUDED.icon, visible = EXCLUDED.visible, update_time = CURRENT_TIMESTAMP',
                [
                    'menu_key' => $key,
                    'title' => $title,
                    'icon' => $icon,
                    'visible' => $visible ? 'true' : 'false',
                ],
            );
            $this->bumpVersion();
            $this->audit->record($actor, 'menu', 'update', '保存了菜单' . $title, 'menu', $key, $title, $requestId, $ip, $device);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assemble(): array
    {
        $home = $this->catalogNode('home', 'page', '工作台', 'home', '/');
        $children = [
            $this->catalogNode('system:user', 'page', '用户管理', 'user', '/system/user'),
            $this->catalogNode('system:dept', 'page', '部门管理', 'dept', '/system/dept'),
            $this->catalogNode('system:post', 'page', '岗位管理', 'post', '/system/post'),
            $this->catalogNode('system:role', 'page', '角色管理', 'role', '/system/role'),
            $this->catalogNode('system:dict', 'page', '选项管理', 'dict', '/data/dict'),
            $this->catalogNode('system:announcement', 'page', '公告管理', 'notice', '/data/notice'),
            $this->catalogNode('system:menu', 'page', '菜单管理', 'menu', '/system/menu'),
            $this->catalogNode('system:plugin', 'page', '插件管理', 'plugin', '/system/plugins'),
            $this->catalogNode('system:update', 'page', '系统更新', 'refresh', '/system/update'),
        ];
        foreach ($this->pluginPages() as $page) {
            $children[] = $page;
        }
        $system = $this->catalogNode('system', 'group', '系统', 'setting', null);
        $system['children'] = $children;
        $items = [$home, $system];
        return $this->attachCustom($this->applyOverrides($items), false);
    }

    /**
     * 先挂没有上级的分组，再挂外链。否则外链的键排序靠前时，上级还没进树，链接会掉到根上。
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function attachCustom(array $items, bool $sidebar): array
    {
        $roots = [];
        $nested = [];
        foreach ($this->customRows() as $row) {
            if ($sidebar && !$this->isOn($row['visible'] ?? true)) {
                continue;
            }
            if ($this->textOrNull($row['parent_key'] ?? null) === null) {
                $roots[] = $row;
            } else {
                $nested[] = $row;
            }
        }
        $items = $this->placeRows($items, $roots, $sidebar);
        $items = $this->placeRows($items, $nested, $sidebar);

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function placeRows(array $items, array $rows, bool $sidebar): array
    {
        foreach ($rows as $row) {
            $node = $sidebar ? $this->sidebarNode($row) : $this->customNode($row);
            $parentKey = $this->textOrNull($row['parent_key'] ?? null);
            if ($parentKey === null) {
                $items[] = $node;
                continue;
            }
            $placed = $this->pushChild($items, $parentKey, $node);
            $items = $placed['items'];
            if (!$placed['placed']) {
                $items[] = $node;
            }
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function applyOverrides(array $items): array
    {
        $overrides = [];
        foreach ($this->overrideRows() as $row) {
            $key = OrgSql::str($row['menu_key'] ?? null);
            if ($key !== '') {
                $overrides[$key] = $row;
            }
        }
        if ($overrides === []) {
            return $items;
        }

        return $this->paintOverrides($items, $overrides);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $overrides
     *
     * @return list<array<string, mixed>>
     */
    private function paintOverrides(array $items, array $overrides): array
    {
        $painted = [];
        foreach ($items as $item) {
            $key = OrgSql::str($item['key'] ?? null);
            if (isset($overrides[$key])) {
                $row = $overrides[$key];
                $title = $this->textOrNull($row['title'] ?? null);
                $icon = array_key_exists('icon', $row) ? $this->textOrNull($row['icon']) : null;
                if ($title !== null) {
                    $item['title'] = $title;
                }
                if (array_key_exists('icon', $row) && $row['icon'] !== null) {
                    $item['icon'] = $icon;
                }
                if ($row['visible'] !== null) {
                    $item['visible'] = $this->isOn($row['visible']);
                }
            }
            $children = $item['children'] ?? null;
            if (is_array($children)) {
                $item['children'] = $this->paintOverrides($this->stringRows($children), $overrides);
            }
            $painted[] = $item;
        }

        return $painted;
    }

    /**
     * 被覆盖层隐藏的节点连同子级一起摘掉，visible 键不进侧栏响应。
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function pruneHidden(array $items): array
    {
        $kept = [];
        foreach ($items as $item) {
            if (array_key_exists('visible', $item) && !$this->isOn($item['visible'])) {
                continue;
            }
            unset($item['visible']);
            $children = $item['children'] ?? null;
            if (is_array($children)) {
                $item['children'] = $this->pruneHidden($this->stringRows($children));
            }
            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function filterNodes(array $items, string $keyword, string $source): array
    {
        if ($keyword === '' && $source === '') {
            return $items;
        }
        $kept = [];
        foreach ($items as $item) {
            $children = $item['children'] ?? null;
            $nested = is_array($children) ? $this->filterNodes($this->stringRows($children), $keyword, $source) : [];
            $title = OrgSql::str($item['title'] ?? null);
            $kind = '';
            $sourceNode = $item['source'] ?? null;
            if (is_array($sourceNode)) {
                $kind = OrgSql::str($sourceNode['kind'] ?? null);
            }
            $selfMatch = ($keyword === '' || mb_stripos($title, $keyword) !== false) && ($source === '' || $kind === $source);
            if (!$selfMatch && $nested === []) {
                continue;
            }
            if (is_array($children)) {
                $item['children'] = $nested;
            }
            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $node
     *
     * @return array{items: list<array<string, mixed>>, placed: bool}
     */
    private function pushChild(array $items, string $parentKey, array $node): array
    {
        $next = [];
        $placed = false;
        foreach ($items as $item) {
            if (OrgSql::str($item['key'] ?? null) === $parentKey) {
                $children = $item['children'] ?? [];
                $childRows = is_array($children) ? $this->stringRows($children) : [];
                $childRows[] = $node;
                $item['children'] = $childRows;
                $placed = true;
            } elseif (!$placed) {
                $children = $item['children'] ?? null;
                if (is_array($children)) {
                    $nested = $this->pushChild($this->stringRows($children), $parentKey, $node);
                    $item['children'] = $nested['items'];
                    if ($nested['placed']) {
                        $placed = true;
                    }
                }
            }
            $next[] = $item;
        }

        return ['items' => $next, 'placed' => $placed];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function customNode(array $row): array
    {
        $kind = OrgSql::str($row['kind'] ?? null);
        $node = [
            'key' => OrgSql::str($row['menu_key'] ?? null),
            'type' => $kind === 'group' ? 'group' : 'link',
            'title' => OrgSql::str($row['title'] ?? null),
            'original_title' => null,
            'icon' => $this->textOrNull($row['icon'] ?? null),
            'source' => ['kind' => 'custom', 'plugin_label' => null],
            'visible' => $this->isOn($row['visible'] ?? true),
            'locked' => ['move' => false, 'hide' => false],
            'new_until' => null,
            'rename_suggested' => false,
            'url' => $this->textOrNull($row['url'] ?? null),
        ];
        if ($kind === 'group') {
            $node['children'] = [];
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function sidebarNode(array $row): array
    {
        $kind = OrgSql::str($row['kind'] ?? null);
        $node = [
            'key' => OrgSql::str($row['menu_key'] ?? null),
            'type' => $kind === 'group' ? 'group' : 'link',
            'title' => OrgSql::str($row['title'] ?? null),
            'icon' => $this->textOrNull($row['icon'] ?? null),
            'path' => null,
            'url' => $this->textOrNull($row['url'] ?? null),
        ];
        if ($kind === 'group') {
            $node['children'] = [];
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function catalogNode(string $key, string $type, string $title, string $icon, ?string $path): array
    {
        $node = [
            'key' => $key,
            'type' => $type,
            'title' => $title,
            'original_title' => $title,
            'icon' => $icon,
            'path' => $path,
            'source' => ['kind' => 'system', 'plugin_label' => null],
            'visible' => true,
            'locked' => ['move' => false, 'hide' => false],
            'new_until' => null,
            'rename_suggested' => false,
            'url' => null,
        ];
        if ($type === 'group') {
            $node['children'] = [];
        }

        return $node;
    }

    /** @return list<array<string, mixed>> */
    private function pluginPages(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT code, name FROM sand_system_plugin WHERE state = \'installed\' ORDER BY name',
            );
        } catch (\Throwable) {
            return [];
        }
        $pages = [];
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            $name = $row['name'] ?? null;
            if (!is_string($code) || !is_string($name) || $code === '') {
                continue;
            }
            $page = $this->catalogNode('plugin:' . $code, 'page', $name, 'plugin', '/plugin/' . $code);
            $page['source'] = ['kind' => 'plugin', 'plugin_label' => $name];
            $pages[] = $page;
        }

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function customRows(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT menu_key, kind, title, icon, parent_key, url, sort, visible FROM sand_system_menu_custom ORDER BY sort, menu_key',
        );
    }

    /** @return list<array<string, mixed>> */
    private function overrideRows(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT menu_key, title, icon, parent_key, sort, visible FROM sand_system_menu_override',
        );
    }

    /** @return array<string, mixed>|null */
    private function customByKey(string $key): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT menu_key, kind, title, icon, parent_key, url, sort, visible FROM sand_system_menu_custom WHERE menu_key = ?',
            [$key],
        );

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed> */
    private function overrideByKey(string $key): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT menu_key, title, icon, visible FROM sand_system_menu_override WHERE menu_key = ?',
            [$key],
        );

        return $row === false ? [] : $row;
    }

    private function isCatalogKey(string $key): bool
    {
        if ($key === 'home' || $key === 'system' || str_starts_with($key, 'system:') || str_starts_with($key, 'plugin:')) {
            return true;
        }

        return false;
    }

    private function catalogTitle(string $key): string
    {
        foreach ($this->assemble() as $item) {
            $found = $this->titleOf($item, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return $key;
    }

    /** @param array<string, mixed> $item */
    private function titleOf(array $item, string $key): ?string
    {
        if (OrgSql::str($item['key'] ?? null) === $key) {
            $original = $this->textOrNull($item['original_title'] ?? null);

            return $original ?? OrgSql::str($item['title'] ?? null);
        }
        $children = $item['children'] ?? null;
        if (!is_array($children)) {
            return null;
        }
        foreach ($this->stringRows($children) as $child) {
            $found = $this->titleOf($child, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function assertLinkParent(?string $parentKey): void
    {
        if ($parentKey === null || $parentKey === '') {
            throw MenuException::invalid(['parent_key' => ['请选择分组']]);
        }
        if ($this->isGroupKey($parentKey)) {
            return;
        }
        throw MenuException::invalid(['parent_key' => ['上级必须是分组']]);
    }

    private function isGroupKey(string $key): bool
    {
        if ($key === 'system') {
            return true;
        }
        $row = $this->customByKey($key);

        return $row !== null && OrgSql::str($row['kind'] ?? null) === 'group';
    }

    private function assertSiblingFree(?string $parentKey, string $title, ?string $exceptKey): void
    {
        foreach ($this->assemble() as $item) {
            if ($this->nameTaken($item, $parentKey, $title, $exceptKey, null)) {
                throw MenuException::invalid(['title' => ['这个名称已经有了，请换一个']]);
            }
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function nameTaken(array $item, ?string $parentKey, string $title, ?string $exceptKey, ?string $seenParent): bool
    {
        $key = OrgSql::str($item['key'] ?? null);
        if ($seenParent === $parentKey && $key !== $exceptKey && OrgSql::str($item['title'] ?? null) === $title) {
            return true;
        }
        $children = $item['children'] ?? null;
        if (!is_array($children)) {
            return false;
        }
        foreach ($this->stringRows($children) as $child) {
            if ($this->nameTaken($child, $parentKey, $title, $exceptKey, $key)) {
                return true;
            }
        }

        return false;
    }

    private function nextSort(?string $parentKey): int
    {
        if ($parentKey === null) {
            $raw = $this->connection->fetchOne('SELECT COALESCE(MAX(sort), 0) FROM sand_system_menu_custom WHERE parent_key IS NULL');
        } else {
            $raw = $this->connection->fetchOne('SELECT COALESCE(MAX(sort), 0) FROM sand_system_menu_custom WHERE parent_key = ?', [$parentKey]);
        }

        return OrgSql::int($raw) + 1;
    }

    /** @param array<string, mixed> $stored */
    private function createdKey(array $stored): string
    {
        $key = $stored['key'] ?? null;
        if (!is_string($key) || $key === '') {
            throw MenuException::invalid([], '菜单没有写进去');
        }

        return $key;
    }

    /** @param array<string, mixed> $body */
    private function readTitle(array $body): string
    {
        $raw = $body['title'] ?? null;
        $title = is_string($raw) ? trim($raw) : '';
        if ($title === '') {
            throw MenuException::invalid(['title' => ['请填写名称']]);
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            throw MenuException::invalid(['title' => ['名称最多 6 个字']]);
        }

        return $title;
    }

    /** @param array<string, mixed> $body */
    private function readIcon(array $body): ?string
    {
        if (!array_key_exists('icon', $body) || $body['icon'] === null || $body['icon'] === '') {
            return null;
        }
        $icon = $body['icon'];
        if (!is_string($icon)) {
            throw MenuException::invalid(['icon' => ['图标不正确']]);
        }
        $icon = trim($icon);
        if (mb_strlen($icon) > 64) {
            throw MenuException::invalid(['icon' => ['图标名称太长了']]);
        }

        return $icon === '' ? null : $icon;
    }

    /** @param array<string, mixed> $body */
    private function readVisible(array $body): bool
    {
        $value = $body['visible'] ?? null;
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }
        throw MenuException::invalid(['visible' => ['请选择显示还是隐藏']]);
    }

    /** @param array<string, mixed> $body */
    private function readUrl(array $body): string
    {
        $raw = $body['url'] ?? null;
        $url = is_string($raw) ? trim($raw) : '';
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1 || str_contains($url, ' ') || mb_strlen($url) > self::URL_MAX) {
            throw MenuException::badUrl();
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw MenuException::badUrl();
        }

        return $url;
    }

    /** @param array<string, mixed> $body */
    private function assertVersion(array $body): void
    {
        if (!array_key_exists('base_version', $body) || $body['base_version'] === null || $body['base_version'] === '') {
            return;
        }
        $given = $body['base_version'];
        if (!is_numeric($given) || (int) $given !== $this->version()) {
            throw MenuException::stale();
        }
    }

    private function version(): int
    {
        $raw = $this->connection->fetchOne("SELECT value FROM sand_system_setting WHERE key = 'menu.version'");
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $trimmed = trim($raw, " \t\n\r\"");
            if (is_numeric($trimmed)) {
                return (int) $trimmed;
            }
        }

        return 1;
    }

    private function bumpVersion(): void
    {
        $next = $this->version() + 1;
        $this->connection->executeStatement(
            "INSERT INTO sand_system_setting (key, value, group_code) VALUES ('menu.version', CAST(:value AS jsonb), 'basic')"
            . ' ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, update_time = CURRENT_TIMESTAMP',
            ['value' => (string) $next],
        );
    }

    private function isOn(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    private function textOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);

        return $text === '' ? null : $text;
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function stringRows(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $item[$key] = $value;
                }
            }
            $clean[] = $item;
        }

        return $clean;
    }
}
