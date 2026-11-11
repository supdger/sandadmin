<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\Exception\DeptNotEmptyException;
use Sandadmin\Module\Org\Exception\DeptNotFoundException;

/** 部门树的查询、改名、挪动和删除。删除非空仍走 DeptDeletion。 */
final class DeptService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DeptDeletion $deletion,
        private readonly OrgAudit $audit,
        private readonly OrgIdempotency $keys,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function tree(?string $keyword, ?int $status): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.id, d.parent_id, d.name, d.leader_id, d.phone, d.sort, d.status,'
            . ' CASE WHEN u.id IS NULL OR u.delete_time IS NOT NULL THEN NULL ELSE u.nickname END AS leader_name,'
            . ' CASE WHEN u.id IS NULL OR u.delete_time IS NOT NULL THEN NULL'
            . '      WHEN u.status = 2 THEN \'disabled\' ELSE \'enabled\' END AS leader_status,'
            . ' (SELECT COUNT(*) FROM sand_system_user x WHERE x.dept_id = d.id AND x.delete_time IS NULL) AS user_count'
            . ' FROM sand_system_dept d'
            . ' LEFT JOIN sand_system_user u ON u.id = d.leader_id'
            . ' ORDER BY d.sort, d.id',
        );
        $nodes = [];
        foreach ($rows as $row) {
            $id = OrgSql::int($row['id'] ?? null);
            $nodes[$id] = $this->node($row);
        }
        if ($keyword !== null && $keyword !== '') {
            $nodes = $this->filterKeyword($nodes, $keyword);
        }
        if ($status !== null) {
            $kept = [];
            foreach ($nodes as $id => $node) {
                if (($node['status'] ?? '') === OrgSql::statusName($status)) {
                    $kept[$id] = $node;
                }
            }
            $nodes = $kept;
        }

        return ['items' => $this->nest($nodes)];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function create(array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device, ?string $idempotencyKey = null): array
    {
        $draft = $this->draft($body);
        $this->assertParent($draft['parent_id']);
        $this->assertLeader($draft['leader_id']);
        try {
            return $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'dept.create', $body, function () use ($draft, $actor, $requestId, $ip, $device): array {
                $createdId = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_dept (parent_id, name, leader_id, phone, sort, status, created_by, updated_by)'
                    . ' VALUES (:parent_id, :name, :leader_id, :phone, :sort, :status, :by, :by) RETURNING id',
                    [
                        'parent_id' => $draft['parent_id'],
                        'name' => $draft['name'],
                        'leader_id' => $draft['leader_id'],
                        'phone' => $draft['phone'],
                        'sort' => $draft['sort'],
                        'status' => $draft['status'],
                        'by' => $actor->writerId(),
                    ],
                ));
                $this->audit->record($actor, 'dept', 'create', '添加了部门' . $draft['name'], 'dept', (string) $createdId, $draft['name'], $requestId, $ip, $device);

                return $this->one($createdId);
            });
        } catch (\Throwable $error) {
            OrgDbMap::map($error);
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $before = $this->requireRow($id);
        $draft = $this->draft($body);
        if ($draft['parent_id'] === $id) {
            throw OrgException::invalid(['parent_id' => ['不能把部门挪到它自己下面']]);
        }
        $this->assertParent($draft['parent_id']);
        $this->assertNotDescendant($id, $draft['parent_id']);
        $this->assertLeader($draft['leader_id']);
        try {
            $this->connection->transactional(function () use ($id, $draft, $actor, $before, $requestId, $ip, $device): void {
                $this->connection->executeStatement(
                    'UPDATE sand_system_dept SET parent_id = :parent_id, name = :name, leader_id = :leader_id,'
                    . ' phone = :phone, sort = :sort, status = :status, updated_by = :by, update_time = CURRENT_TIMESTAMP'
                    . ' WHERE id = :id',
                    [
                        'parent_id' => $draft['parent_id'],
                        'name' => $draft['name'],
                        'leader_id' => $draft['leader_id'],
                        'phone' => $draft['phone'],
                        'sort' => $draft['sort'],
                        'status' => $draft['status'],
                        'by' => $actor->writerId(),
                        'id' => $id,
                    ],
                );
                $this->audit->record(
                    $actor,
                    'dept',
                    'update',
                    '保存了部门' . $draft['name'],
                    'dept',
                    (string) $id,
                    $draft['name'],
                    $requestId,
                    $ip,
                    $device,
                    ['name' => ['before' => OrgSql::str($before['name'] ?? null), 'after' => $draft['name']]],
                );
            });
        } catch (\Throwable $error) {
            OrgDbMap::map($error);
        }

        return $this->one($id);
    }

    /** @return array{user_count: int, child_count: int, affected_roles: list<array{id: string, name: string}>} */
    public function deleteCheck(int $id): array
    {
        try {
            $check = $this->deletion->check($id);
        } catch (DeptNotFoundException) {
            throw OrgException::deptNotFound();
        }
        $roles = [];
        foreach ($check->affectedRoles as $role) {
            $roles[] = ['id' => (string) $role['id'], 'name' => $role['name']];
        }

        return [
            'user_count' => $check->userCount,
            'child_count' => $check->childCount,
            'affected_roles' => $roles,
        ];
    }

    public function remove(int $id, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $row = $this->requireRow($id);
        $name = OrgSql::str($row['name'] ?? null);
        try {
            $this->deletion->delete($id);
        } catch (DeptNotFoundException) {
            throw OrgException::deptNotFound();
        } catch (DeptNotEmptyException) {
            throw OrgException::deptNotEmpty($name . '现在还不能删除');
        }
        $this->audit->record($actor, 'dept', 'delete', '删除了部门' . $name, 'dept', (string) $id, $name, $requestId, $ip, $device);
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function options(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, parent_id, name, sort FROM sand_system_dept WHERE status = 1 ORDER BY sort, id',
        );
        $items = [];
        foreach ($rows as $row) {
            $parent = $row['parent_id'] ?? null;
            $items[] = [
                'id' => (string) OrgSql::int($row['id'] ?? null),
                'parent_id' => $parent === null ? null : (string) OrgSql::int($parent),
                'name' => OrgSql::str($row['name'] ?? null),
                'sort' => OrgSql::int($row['sort'] ?? 0),
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{parent_id: ?int, name: string, leader_id: ?int, phone: ?string, sort: int, status: int}
     */
    private function draft(array $body): array
    {
        $name = OrgInput::requiredText($body, 'name');
        if (mb_strlen($name) > 30) {
            throw OrgException::invalid(['name' => ['部门名称最多 30 个字']]);
        }
        $phone = OrgInput::optionalText($body, 'phone');
        if ($phone !== null && mb_strlen($phone) > 20) {
            throw OrgException::invalid(['phone' => ['联系电话太长了']]);
        }

        return [
            'parent_id' => OrgInput::optionalId($body, 'parent_id'),
            'name' => $name,
            'leader_id' => OrgInput::optionalId($body, 'leader_id'),
            'phone' => $phone,
            'sort' => OrgInput::sort($body),
            'status' => OrgInput::status($body, 1),
        ];
    }

    private function assertParent(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        $count = OrgSql::int($this->connection->fetchOne('SELECT COUNT(*) FROM sand_system_dept WHERE id = ?', [$parentId]));
        if ($count !== 1) {
            throw OrgException::deptNotFound();
        }
    }

    private function assertLeader(?int $leaderId): void
    {
        if ($leaderId === null) {
            return;
        }
        $count = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user WHERE id = ? AND delete_time IS NULL',
            [$leaderId],
        ));
        if ($count !== 1) {
            throw OrgException::userNotFound();
        }
    }

    private function assertNotDescendant(int $id, ?int $parentId): void
    {
        $current = $parentId;
        $guard = 0;
        while ($current !== null && $guard < 32) {
            if ($current === $id) {
                throw OrgException::invalid(['parent_id' => ['不能把部门挪到它自己的下级部门里']]);
            }
            $parent = $this->connection->fetchOne('SELECT parent_id FROM sand_system_dept WHERE id = ?', [$current]);
            $current = $parent === null || $parent === false ? null : OrgSql::int($parent);
            if ($current === 0) {
                $current = null;
            }
            $guard++;
        }
    }

    /** @return array<string, mixed> */
    private function requireRow(int $id): array
    {
        $row = $this->connection->fetchAssociative('SELECT id, name FROM sand_system_dept WHERE id = ?', [$id]);
        if ($row === false) {
            throw OrgException::deptNotFound();
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function one(int $id): array
    {
        $tree = $this->tree(null, null);
        $found = $this->findNode($tree['items'], $id);
        if ($found === null) {
            throw OrgException::deptNotFound();
        }

        return $found;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    private function findNode(array $items, int $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? '') === (string) $id) {
                return $item;
            }
            $children = $item['children'] ?? [];
            if (is_array($children)) {
                $childRows = [];
                foreach ($children as $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    $row = [];
                    foreach ($child as $key => $value) {
                        if (is_string($key)) {
                            $row[$key] = $value;
                        }
                    }
                    $childRows[] = $row;
                }
                $hit = $this->findNode($childRows, $id);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function node(array $row): array
    {
        $parent = $row['parent_id'] ?? null;
        $leader = $row['leader_id'] ?? null;
        $leaderStatus = $row['leader_status'] ?? null;

        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'parent_id' => $parent === null ? null : (string) OrgSql::int($parent),
            'name' => OrgSql::str($row['name'] ?? null),
            'leader_id' => $leader === null ? null : (string) OrgSql::int($leader),
            'leader_name' => ($row['leader_name'] ?? null) === null ? null : OrgSql::str($row['leader_name']),
            'leader_avatar' => null,
            'leader_status' => is_string($leaderStatus) ? $leaderStatus : null,
            'phone' => ($row['phone'] ?? null) === null ? null : OrgSql::str($row['phone']),
            'sort' => OrgSql::int($row['sort'] ?? 0),
            'status' => OrgSql::statusName(OrgSql::int($row['status'] ?? 1)),
            'user_count' => OrgSql::int($row['user_count'] ?? 0),
            'children' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterKeyword(array $nodes, string $keyword): array
    {
        $keep = [];
        foreach ($nodes as $id => $node) {
            if (is_string($node['name'] ?? null) && mb_stripos($node['name'], $keyword) !== false) {
                $keep[$id] = true;
                $parentText = $node['parent_id'] ?? null;
                $parentId = is_string($parentText) ? (int) $parentText : 0;
                $guard = 0;
                while ($parentId > 0 && isset($nodes[$parentId]) && $guard < 32) {
                    $keep[$parentId] = true;
                    $up = $nodes[$parentId]['parent_id'] ?? null;
                    $parentId = is_string($up) ? (int) $up : 0;
                    $guard++;
                }
            }
        }
        $filtered = [];
        foreach ($keep as $id => $_) {
            if (isset($nodes[$id])) {
                $filtered[$id] = $nodes[$id];
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function nest(array $nodes): array
    {
        $childrenOf = [];
        foreach ($nodes as $id => $node) {
            $parentText = $node['parent_id'] ?? null;
            $parentId = is_string($parentText) && isset($nodes[(int) $parentText]) ? (int) $parentText : 0;
            $childrenOf[$parentId][] = $id;
        }
        $build = function (int $parentId) use (&$build, $nodes, $childrenOf): array {
            $list = [];
            foreach ($childrenOf[$parentId] ?? [] as $childId) {
                $node = $nodes[$childId];
                $node['children'] = $build($childId);
                $list[] = $node;
            }

            return $list;
        };

        return $build(0);
    }
}
