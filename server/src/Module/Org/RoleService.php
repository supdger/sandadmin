<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;

/** 角色、数据范围和权限码。系统角色拒绝改、停、删。 */
final class RoleService
{
    /** @var list<array{group_key: string, group_title: string, menu_key: string, title: string, actions: list<array{action: string, label: string}>}> */
    private const MENUS = [
        ['group_key' => 'org', 'group_title' => '组织与用户', 'menu_key' => 'system:user', 'title' => '用户管理', 'actions' => [
            ['action' => 'index', 'label' => '查看'], ['action' => 'create', 'label' => '添加'],
            ['action' => 'update', 'label' => '编辑'], ['action' => 'delete', 'label' => '删除'],
            ['action' => 'export', 'label' => '导出'], ['action' => 'reset-password', 'label' => '重置密码'],
            ['action' => 'assign-role', 'label' => '分配角色'],
        ]],
        ['group_key' => 'org', 'group_title' => '组织与用户', 'menu_key' => 'system:dept', 'title' => '部门管理', 'actions' => [
            ['action' => 'index', 'label' => '查看'], ['action' => 'create', 'label' => '添加'],
            ['action' => 'update', 'label' => '编辑'], ['action' => 'delete', 'label' => '删除'],
        ]],
        ['group_key' => 'org', 'group_title' => '组织与用户', 'menu_key' => 'system:post', 'title' => '岗位管理', 'actions' => [
            ['action' => 'index', 'label' => '查看'], ['action' => 'create', 'label' => '添加'],
            ['action' => 'update', 'label' => '编辑'], ['action' => 'delete', 'label' => '删除'],
        ]],
        ['group_key' => 'org', 'group_title' => '组织与用户', 'menu_key' => 'system:role', 'title' => '角色与权限', 'actions' => [
            ['action' => 'index', 'label' => '查看'], ['action' => 'create', 'label' => '添加'],
            ['action' => 'update', 'label' => '编辑'], ['action' => 'delete', 'label' => '删除'],
            ['action' => 'permission:update', 'label' => '设置权限'],
        ]],
        ['group_key' => 'data', 'group_title' => '数据管理', 'menu_key' => 'system:dict', 'title' => '选项管理', 'actions' => [
            ['action' => 'index', 'label' => '查看'], ['action' => 'create', 'label' => '添加'],
            ['action' => 'update', 'label' => '编辑'], ['action' => 'delete', 'label' => '删除'],
        ]],
        ['group_key' => 'data', 'group_title' => '数据管理', 'menu_key' => 'system:announcement', 'title' => '公告管理', 'actions' => [
            ['action' => 'index', 'label' => '查看'], ['action' => 'create', 'label' => '添加'],
            ['action' => 'update', 'label' => '编辑'], ['action' => 'delete', 'label' => '删除'],
            ['action' => 'publish', 'label' => '发布'], ['action' => 'revoke', 'label' => '收回'],
        ]],
    ];

    /** @var list<string> */
    private const SCOPES = ['all', 'dept_and_child', 'dept', 'self', 'custom'];

    public function __construct(
        private readonly Connection $connection,
        private readonly OrgAudit $audit,
        private readonly OrgIdempotency $keys,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, page_size: int}
     */
    public function search(array $query): array
    {
        $page = is_numeric($query['page'] ?? null) ? max(1, (int) $query['page']) : 1;
        $pageSize = is_numeric($query['page_size'] ?? null) ? (int) $query['page_size'] : 20;
        if ($pageSize < 1 || $pageSize > 100) {
            throw OrgException::invalid(['page_size' => ['每页条数要在 1 到 100 之间']]);
        }
        $where = ['1=1'];
        $params = [];
        $keyword = is_string($query['keyword'] ?? null) ? trim($query['keyword']) : '';
        if ($keyword !== '') {
            $where[] = 'name ILIKE :kw';
            $params['kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        if (($query['status'] ?? null) === 'enabled') {
            $where[] = 'status = 1';
        } elseif (($query['status'] ?? null) === 'disabled') {
            $where[] = 'status = 2';
        } elseif (($query['status'] ?? null) !== null && $query['status'] !== '') {
            throw OrgException::invalid(['status' => ['状态不正确']]);
        }
        $sqlWhere = implode(' AND ', $where);
        $total = OrgSql::int($this->connection->fetchOne('SELECT COUNT(*) FROM sand_system_role WHERE ' . $sqlWhere, $params));
        $params['lim'] = $pageSize;
        $params['off'] = ($page - 1) * $pageSize;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, remark, is_system, is_super, status, data_scope, update_time,'
            . ' (SELECT COUNT(*) FROM sand_system_user_role ur JOIN sand_system_user u ON u.id = ur.user_id AND u.delete_time IS NULL WHERE ur.role_id = sand_system_role.id) AS user_count'
            . ' FROM sand_system_role WHERE ' . $sqlWhere . ' ORDER BY id LIMIT :lim OFFSET :off',
            $params,
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->item($row);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function create(array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device, ?string $idempotencyKey = null): array
    {
        $draft = $this->draft($body);
        try {
            return $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'role.create', $body, function () use ($draft, $actor, $requestId, $ip, $device): array {
                $createdId = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_role (name, code, remark, status, created_by, updated_by)'
                    . ' VALUES (:name, :code, :remark, :status, :by, :by) RETURNING id',
                    [
                        'name' => $draft['name'],
                        'code' => 'role_' . bin2hex(random_bytes(8)),
                        'remark' => $draft['remark'],
                        'status' => $draft['status'],
                        'by' => $actor->writerId(),
                    ],
                ));
                $this->audit->record($actor, 'role', 'create', '添加了角色' . $draft['name'], 'role', (string) $createdId, $draft['name'], $requestId, $ip, $device);

                return $this->show($createdId);
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
        $this->assertWritable($before);
        $draft = $this->draft($body);
        try {
            $this->connection->transactional(function () use ($id, $draft, $actor, $before, $requestId, $ip, $device): void {
                $this->connection->executeStatement(
                    'UPDATE sand_system_role SET name = :name, remark = :remark, status = :status, updated_by = :by, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                    ['name' => $draft['name'], 'remark' => $draft['remark'], 'status' => $draft['status'], 'by' => $actor->writerId(), 'id' => $id],
                );
                $this->audit->record(
                    $actor,
                    'role',
                    'update',
                    '保存了角色' . $draft['name'],
                    'role',
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

        return $this->show($id);
    }

    /** @return array<string, mixed> */
    public function show(int $id): array
    {
        return $this->item($this->requireRow($id));
    }

    /** @return array{moved_to_default: int} */
    public function remove(int $id, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $row = $this->requireRow($id);
        $this->assertWritable($row);
        $name = OrgSql::str($row['name'] ?? null);
        $moved = 0;
        $this->connection->transactional(function () use ($id, $actor, $name, $requestId, $ip, $device, &$moved): void {
            $sole = $this->connection->fetchFirstColumn(
                'SELECT ur.user_id FROM sand_system_user_role ur'
                . ' JOIN sand_system_user u ON u.id = ur.user_id AND u.delete_time IS NULL'
                . ' WHERE ur.role_id = :id'
                . ' AND NOT EXISTS (SELECT 1 FROM sand_system_user_role other WHERE other.user_id = ur.user_id AND other.role_id <> :id)',
                ['id' => $id],
            );
            $userIds = [];
            foreach ($sole as $userId) {
                $userIds[] = OrgSql::int($userId);
            }
            if ($userIds !== []) {
                $defaultId = OrgSql::int($this->connection->fetchOne(
                    'SELECT id FROM sand_system_role WHERE is_system AND NOT is_super AND id <> ? ORDER BY id LIMIT 1',
                    [$id],
                ));
                if ($defaultId <= 0) {
                    throw OrgException::invalid([], '系统还没有默认角色，暂时不能删除正在使用的角色');
                }
                foreach ($userIds as $userId) {
                    $this->connection->insert('sand_system_user_role', ['user_id' => $userId, 'role_id' => $defaultId]);
                }
                $moved = count($userIds);
            }
            $this->connection->executeStatement('DELETE FROM sand_system_role WHERE id = ?', [$id]);
            $this->audit->record($actor, 'role', 'delete', '删除了角色' . $name, 'role', (string) $id, $name, $requestId, $ip, $device);
        });

        return ['moved_to_default' => $moved];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function copy(int $id, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $source = $this->requireRow($id);
        $name = OrgInput::requiredText($body, 'name');
        if (mb_strlen($name) > 20) {
            throw OrgException::invalid(['name' => ['名称最多 20 个字']]);
        }
        try {
            $createdId = OrgSql::int($this->connection->transactional(function () use ($id, $source, $name, $actor, $requestId, $ip, $device): int {
                $created = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_role (name, code, remark, data_scope, status, created_by, updated_by)'
                    . ' VALUES (:name, :code, :remark, :scope, 1, :by, :by) RETURNING id',
                    [
                        'name' => $name,
                        'code' => 'role_' . bin2hex(random_bytes(8)),
                        'remark' => $source['remark'] ?? null,
                        'scope' => OrgSql::str($source['data_scope'] ?? 'self'),
                        'by' => $actor->writerId(),
                    ],
                ));
                $this->connection->executeStatement(
                    'INSERT INTO sand_system_role_permission (role_id, permission_code)'
                    . ' SELECT :new_id, permission_code FROM sand_system_role_permission WHERE role_id = :old_id',
                    ['new_id' => $created, 'old_id' => $id],
                );
                $this->connection->executeStatement(
                    'INSERT INTO sand_system_role_dept (role_id, dept_id)'
                    . ' SELECT :new_id, dept_id FROM sand_system_role_dept WHERE role_id = :old_id',
                    ['new_id' => $created, 'old_id' => $id],
                );
                $this->audit->record($actor, 'role', 'create', '复制了角色' . $name, 'role', (string) $created, $name, $requestId, $ip, $device);

                return $created;
            }));
        } catch (\Throwable $error) {
            OrgDbMap::map($error);
        }

        return $this->show($createdId);
    }

    public function setStatus(int $id, int $status, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $row = $this->requireRow($id);
        $this->assertWritable($row);
        $name = OrgSql::str($row['name'] ?? null);
        $this->connection->transactional(function () use ($id, $status, $actor, $name, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_role SET status = :status, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => $status, 'id' => $id],
            );
            $verb = $status === 2 ? '停用了角色' : '启用了角色';
            $this->audit->record($actor, 'role', 'update', $verb . $name, 'role', (string) $id, $name, $requestId, $ip, $device);
        });
    }

    /** @return array<string, mixed> */
    public function permissions(int $id): array
    {
        $row = $this->requireRow($id);
        $codes = $this->connection->fetchFirstColumn(
            'SELECT permission_code FROM sand_system_role_permission WHERE role_id = ? ORDER BY permission_code',
            [$id],
        );
        $deptIds = $this->connection->fetchFirstColumn(
            'SELECT dept_id FROM sand_system_role_dept WHERE role_id = ? ORDER BY dept_id',
            [$id],
        );
        $permissionCodes = [];
        foreach ($codes as $code) {
            if (is_string($code)) {
                $permissionCodes[] = $code;
            }
        }
        $selectedDeptIds = [];
        foreach ($deptIds as $deptId) {
            $selectedDeptIds[] = (string) OrgSql::int($deptId);
        }
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1;
        $isSuper = ($row['is_super'] ?? null) === true || ($row['is_super'] ?? null) === 't' || ($row['is_super'] ?? null) === 1;

        return [
            'data_scope' => OrgSql::str($row['data_scope'] ?? 'self'),
            'dept_ids' => $selectedDeptIds,
            'permission_codes' => $permissionCodes,
            'user_count' => OrgSql::int($row['user_count'] ?? 0),
            'readonly' => $isSystem || $isSuper,
            'base_version' => OrgSql::int($row['authz_version'] ?? 1),
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{affected_users: int}
     */
    public function savePermissions(int $id, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $row = $this->requireRow($id);
        $this->assertWritable($row);
        $scope = OrgInput::requiredText($body, 'data_scope');
        if (!in_array($scope, self::SCOPES, true)) {
            throw OrgException::scopeInvalid();
        }
        $deptIds = OrgInput::idList($body, 'dept_ids', false);
        if (($scope === 'custom' || $scope === 'dept') && $deptIds === [] && $scope === 'custom') {
            throw OrgException::deptRequired();
        }
        $codes = $this->codeList($body);
        $version = OrgInput::optionalId($body, 'base_version') ?? 0;
        if (!array_key_exists('base_version', $body)) {
            throw OrgException::invalid(['base_version' => ['缺少版本号']]);
        }
        $rawVersion = $body['base_version'];
        $baseVersion = is_numeric($rawVersion) ? (int) $rawVersion : -1;
        if ($baseVersion !== OrgSql::int($row['authz_version'] ?? 0)) {
            throw OrgException::stale();
        }
        unset($version);
        $this->guardSelfLockout($id, $codes, $actor);
        $name = OrgSql::str($row['name'] ?? null);
        $affected = 0;
        $this->connection->transactional(function () use ($id, $scope, $deptIds, $codes, $actor, $name, $requestId, $ip, $device, &$affected): void {
            $this->connection->executeStatement('DELETE FROM sand_system_role_permission WHERE role_id = ?', [$id]);
            foreach ($codes as $code) {
                $this->connection->insert('sand_system_role_permission', ['role_id' => $id, 'permission_code' => $code]);
            }
            $this->connection->executeStatement('DELETE FROM sand_system_role_dept WHERE role_id = ?', [$id]);
            foreach ($deptIds as $deptId) {
                $this->connection->insert('sand_system_role_dept', ['role_id' => $id, 'dept_id' => $deptId]);
            }
            $this->connection->executeStatement(
                'UPDATE sand_system_role SET data_scope = :scope, authz_version = authz_version + 1, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                ['scope' => $scope, 'id' => $id],
            );
            $affected = OrgSql::int($this->connection->fetchOne(
                'SELECT COUNT(*) FROM sand_system_user_role ur JOIN sand_system_user u ON u.id = ur.user_id AND u.delete_time IS NULL WHERE ur.role_id = ?',
                [$id],
            ));
            $this->audit->record($actor, 'role', 'update', '保存了角色' . $name . '的权限', 'role', (string) $id, $name, $requestId, $ip, $device);
        });

        return ['affected_users' => $affected];
    }

    /** @return array{items: list<array<string, mixed>>} */
    public function permissionTree(): array
    {
        $groups = [];
        foreach (self::MENUS as $menu) {
            $groupKey = $menu['group_key'];
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_key' => $groupKey,
                    'group_title' => $menu['group_title'],
                    'menus' => [],
                ];
            }
            $actions = [];
            foreach ($menu['actions'] as $action) {
                $code = $menu['menu_key'] . ':' . $action['action'];
                if ($menu['menu_key'] === 'system:role' && $action['action'] === 'permission:update') {
                    $code = 'system:role:permission:update';
                }
                $actions[] = [
                    'code' => $code,
                    'action' => $action['action'],
                    'label' => $action['label'],
                    'description' => null,
                ];
            }
            $groups[$groupKey]['menus'][] = [
                'menu_key' => $menu['menu_key'],
                'title' => $menu['title'],
                'description' => null,
                'actions' => $actions,
            ];
        }

        return ['items' => array_values($groups)];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{name: string, remark: ?string, status: int}
     */
    private function draft(array $body): array
    {
        $name = OrgInput::requiredText($body, 'name');
        if (mb_strlen($name) > 20) {
            throw OrgException::invalid(['name' => ['名称最多 20 个字']]);
        }
        $remark = OrgInput::optionalText($body, 'remark');
        if ($remark !== null && mb_strlen($remark) > 100) {
            throw OrgException::invalid(['remark' => ['说明最多 100 个字']]);
        }

        return ['name' => $name, 'remark' => $remark, 'status' => OrgInput::status($body, 1)];
    }

    /** @return array<string, mixed> */
    private function requireRow(int $id): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, remark, is_system, is_super, status, data_scope, authz_version, update_time,'
            . ' (SELECT COUNT(*) FROM sand_system_user_role ur JOIN sand_system_user u ON u.id = ur.user_id AND u.delete_time IS NULL WHERE ur.role_id = sand_system_role.id) AS user_count'
            . ' FROM sand_system_role WHERE id = ?',
            [$id],
        );
        if ($row === false) {
            throw OrgException::roleNotFound();
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function assertWritable(array $row): void
    {
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1 || ($row['is_system'] ?? null) === '1';
        $isSuper = ($row['is_super'] ?? null) === true || ($row['is_super'] ?? null) === 't' || ($row['is_super'] ?? null) === 1 || ($row['is_super'] ?? null) === '1';
        if ($isSystem || $isSuper) {
            throw OrgException::systemRole();
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1 || ($row['is_system'] ?? null) === '1';
        $isSuper = ($row['is_super'] ?? null) === true || ($row['is_super'] ?? null) === 't' || ($row['is_super'] ?? null) === 1 || ($row['is_super'] ?? null) === '1';

        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'name' => OrgSql::str($row['name'] ?? null),
            'remark' => ($row['remark'] ?? null) === null ? null : OrgSql::str($row['remark']),
            'is_system' => $isSystem,
            'is_super' => $isSuper,
            'status' => OrgSql::statusName(OrgSql::int($row['status'] ?? 1)),
            'data_scope' => OrgSql::str($row['data_scope'] ?? 'dept_and_child'),
            'user_count' => OrgSql::int($row['user_count'] ?? 0),
            'update_time' => OrgSql::time($row['update_time'] ?? null) ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private function codeList(array $body): array
    {
        if (!isset($body['permission_codes']) || !is_array($body['permission_codes'])) {
            throw OrgException::invalid(['permission_codes' => ['权限清单不正确']]);
        }
        $known = [];
        foreach ($this->permissionTree()['items'] as $group) {
            $menus = $group['menus'] ?? [];
            if (!is_array($menus)) {
                continue;
            }
            foreach ($menus as $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $actions = $menu['actions'] ?? [];
                if (!is_array($actions)) {
                    continue;
                }
                foreach ($actions as $action) {
                    if (is_array($action) && is_string($action['code'] ?? null)) {
                        $known[$action['code']] = true;
                    }
                }
            }
        }
        $codes = [];
        foreach ($body['permission_codes'] as $code) {
            if (!is_string($code) || !isset($known[$code])) {
                throw OrgException::invalid(['permission_codes' => ['有不认识的权限']]);
            }
            $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }

    /** @param list<string> $codes */
    private function guardSelfLockout(int $roleId, array $codes, OrgActor $actor): void
    {
        if ($actor->isSuper || $actor->userId <= 0) {
            return;
        }
        $holds = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user_role WHERE user_id = ? AND role_id = ?',
            [$actor->userId, $roleId],
        ));
        if ($holds !== 1) {
            return;
        }
        if (in_array('system:role:permission:update', $codes, true)) {
            return;
        }
        $elsewhere = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_role_permission rp'
            . ' JOIN sand_system_user_role ur ON ur.role_id = rp.role_id'
            . ' WHERE ur.user_id = ? AND ur.role_id <> ? AND rp.permission_code = \'system:role:permission:update\'',
            [$actor->userId, $roleId],
        ));
        if ($elsewhere === 0 && $actor->can('system:role:permission:update')) {
            throw OrgException::selfLockout();
        }
    }
}
