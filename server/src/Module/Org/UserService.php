<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;

/**
 * 用户管理写读。口令只以哈希落库，响应里不出现口令。
 * 停用前检查目前没有插件回调，清单为空。
 */
final class UserService
{
    /** @var list<string> */
    private const ADMIN_CODES = [
        'system:user:create',
        'system:user:update',
        'system:user:reset-password',
        'system:user:assign-role',
        'system:role:permission:update',
    ];

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
        $page = $this->page($query['page'] ?? null);
        $pageSize = $this->pageSize($query['page_size'] ?? null);
        $keyword = is_string($query['keyword'] ?? null) ? trim((string) $query['keyword']) : '';
        $status = $this->queryStatus($query['status'] ?? null);
        $deptId = $this->queryId($query['dept_id'] ?? null, 'dept_id');
        $roleId = $this->queryId($query['role_id'] ?? null, 'role_id');
        $postId = $this->queryId($query['post_id'] ?? null, 'post_id');
        $deptIds = $deptId === null ? [] : $this->deptAndChildren($deptId);

        $where = ['u.delete_time IS NULL'];
        $params = [];
        if ($keyword !== '') {
            $where[] = '(u.nickname ILIKE :kw OR u.phone ILIKE :kw OR COALESCE(u.username, \'\') ILIKE :kw)';
            $params['kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        if ($status !== null) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        if ($deptIds !== []) {
            $where[] = 'u.dept_id IN (' . implode(',', $deptIds) . ')';
        }
        if ($roleId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM sand_system_user_role ur WHERE ur.user_id = u.id AND ur.role_id = :role_id)';
            $params['role_id'] = $roleId;
        }
        if ($postId !== null) {
            $where[] = 'u.post_id = :post_id';
            $params['post_id'] = $postId;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user u WHERE ' . $sqlWhere,
            $params,
        ));
        $params['lim'] = $pageSize;
        $params['off'] = ($page - 1) * $pageSize;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.id, u.username, u.nickname, u.phone, u.email, u.avatar_file_id, u.dept_id,'
            . ' d.name AS dept_name, u.post_id, p.name AS post_name, u.status, u.is_admin, u.is_super,'
            . ' u.must_change_password, u.last_login_time, u.create_time'
            . ' FROM sand_system_user u'
            . ' JOIN sand_system_dept d ON d.id = u.dept_id'
            . ' LEFT JOIN sand_system_post p ON p.id = u.post_id'
            . ' WHERE ' . $sqlWhere
            . ' ORDER BY u.create_time DESC, u.id DESC LIMIT :lim OFFSET :off',
            $params,
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = OrgSql::int($row['id'] ?? null);
        }
        $roles = $this->rolesForUsers($ids);
        $items = [];
        foreach ($rows as $row) {
            $id = OrgSql::int($row['id'] ?? null);
            $pair = $roles[$id] ?? ['ids' => [], 'names' => []];
            $items[] = $this->listItem($row, $pair['ids'], $pair['names']);
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
        $draft = $this->draft($body, true);
        $this->assertRefs($draft['dept_id'], $draft['post_id'], $draft['role_ids']);
        $flags = $this->flagsForRoles($draft['role_ids']);
        try {
            return $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'user.create', $body, function () use ($draft, $flags, $actor, $requestId, $ip, $device): array {
                $createdId = $this->insertUser($draft, $flags['is_super'], $flags['is_admin'], $actor->userId);
                $this->replaceRoles($createdId, $draft['role_ids']);
                $this->audit->record(
                    $actor,
                    'user',
                    'create',
                    '添加了用户「' . $draft['nickname'] . '」',
                    'user',
                    (string) $createdId,
                    $draft['nickname'],
                    $requestId,
                    $ip,
                    $device,
                );

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
        $before = $this->requireAlive($id);
        $draft = $this->draft($body, false);
        $draft['status'] = OrgInput::keptStatus($body, OrgSql::int($before['status'] ?? 1));
        $this->assertRefs($draft['dept_id'], $draft['post_id'], $draft['role_ids']);
        $flags = $this->flagsForRoles($draft['role_ids']);
        $this->guardLastAdmin($id, $draft['status'] === 1 && $flags['is_super']);
        if ($actor->userId === $id && $draft['status'] === 2) {
            throw OrgException::invalid([], '不能停用你自己');
        }
        try {
            $this->connection->transactional(function () use ($id, $draft, $flags, $actor, $before, $requestId, $ip, $device): void {
                $this->connection->executeStatement(
                    'UPDATE sand_system_user SET username = :username, phone = :phone, nickname = :nickname,'
                    . ' email = :email, dept_id = :dept_id, post_id = :post_id, status = :status,'
                    . ' is_super = :is_super, is_admin = :is_admin, updated_by = :by, update_time = CURRENT_TIMESTAMP'
                    . ' WHERE id = :id AND delete_time IS NULL',
                    [
                        'username' => $draft['username'],
                        'phone' => $draft['phone'],
                        'nickname' => $draft['nickname'],
                        'email' => $draft['email'],
                        'dept_id' => $draft['dept_id'],
                        'post_id' => $draft['post_id'],
                        'status' => $draft['status'],
                        'is_super' => $flags['is_super'] ? 'true' : 'false',
                        'is_admin' => $flags['is_admin'] ? 'true' : 'false',
                        'by' => $actor->writerId(),
                        'id' => $id,
                    ],
                );
                $this->replaceRoles($id, $draft['role_ids']);
                if ($draft['status'] === 2) {
                    $this->revokeSessions($id, 'user_disabled');
                }
                $this->audit->record(
                    $actor,
                    'user',
                    'update',
                    '保存了用户「' . $draft['nickname'] . '」',
                    'user',
                    (string) $id,
                    $draft['nickname'],
                    $requestId,
                    $ip,
                    $device,
                    ['nickname' => ['before' => OrgSql::str($before['nickname'] ?? null), 'after' => $draft['nickname']]],
                );
            });
        } catch (\Throwable $error) {
            OrgDbMap::map($error);
        }

        return $this->show($id);
    }

    /** @return array<string, mixed> */
    public function profile(int $id): array
    {
        $row = $this->requireAlive($id);
        $roles = $this->rolesForUsers([$id]);
        $pair = $roles[$id] ?? ['ids' => [], 'names' => []];

        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'username' => ($row['username'] ?? null) === null ? null : OrgSql::str($row['username']),
            'nickname' => OrgSql::str($row['nickname'] ?? null),
            'avatar' => null,
            'avatar_file_id' => ($row['avatar_file_id'] ?? null) === null ? null : (string) OrgSql::int($row['avatar_file_id']),
            'phone' => OrgSql::str($row['phone'] ?? null),
            'email' => ($row['email'] ?? null) === null ? null : OrgSql::str($row['email']),
            'dept_name' => ($row['dept_name'] ?? null) === null ? null : OrgSql::str($row['dept_name']),
            'post_name' => ($row['post_name'] ?? null) === null ? null : OrgSql::str($row['post_name']),
            'role_names' => $pair['names'],
            'password_changed_time' => OrgSql::time($row['password_changed_time'] ?? null) ?? '',
        ];
    }

    /** @return array<string, mixed> */
    public function passwordPolicy(int $id): array
    {
        $this->requireAlive($id);
        $admin = $this->holdsAdminTier($id);

        return [
            'tier' => $admin ? 'admin' : 'normal',
            'min_length' => $admin ? 10 : 8,
            'require_letter' => true,
            'require_digit' => true,
            'forbid_weak' => true,
            'forbid_same_as_username' => true,
            'forbid_same_as_previous' => 0,
        ];
    }

    /**
     * 校验旧口令后换新哈希。明文只用于校验，不写日志。
     * 改密成功时同一事务内吊销本人全部会话（含当前），再补发一枚新令牌；
     * 新令牌只在返回值里出现一次，库里存 SHA-256 哈希。
     *
     * @return array{changed: true, token: string}
     */
    public function changePassword(int $id, string $oldPassword, string $newPassword, ?string $ip = null, ?string $device = null): array
    {
        $row = $this->requireAlive($id);
        $stored = $this->connection->fetchOne(
            'SELECT password_hash FROM sand_system_user WHERE id = :id AND delete_time IS NULL',
            ['id' => $id],
        );
        $hash = is_string($stored) ? $stored : '';
        if ($hash === '' || !password_verify($oldPassword, $hash)) {
            throw OrgException::invalid(
                ['old_password' => ['当前密码不对，请再输一遍']],
                '当前密码不对，请再输一遍',
            );
        }
        $policy = $this->passwordPolicy($id);
        $minLength = is_int($policy['min_length']) ? $policy['min_length'] : 8;
        if (strlen($newPassword) < $minLength) {
            throw OrgException::invalid(
                ['new_password' => ["密码太短了，至少要 {$minLength} 位"]],
                '密码太短了',
            );
        }
        if (preg_match('/[A-Za-z]/', $newPassword) !== 1 || preg_match('/\d/', $newPassword) !== 1) {
            throw OrgException::invalid(
                ['new_password' => ['密码里要有字母和数字']],
                '密码里要有字母和数字',
            );
        }
        $username = is_string($row['username'] ?? null) ? $row['username'] : '';
        if ($username !== '' && strcasecmp($newPassword, $username) === 0) {
            throw OrgException::invalid(
                ['new_password' => ['密码不能和账号一样，请换一个']],
                '密码不能和账号一样，请换一个',
            );
        }
        $token = bin2hex(random_bytes(32));
        $this->connection->transactional(function () use ($id, $newPassword, $token, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_user SET password_hash = :hash, password_changed_time = CURRENT_TIMESTAMP WHERE id = :id',
                ['hash' => $this->hashPassword($newPassword), 'id' => $id],
            );
            $this->revokeSessions($id, 'password_changed');
            $this->issueSession($id, $token, $ip, $device);
        });

        return ['changed' => true, 'token' => $token];
    }

    private function holdsAdminTier(int $id): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM sand_system_user_role ur'
            . ' JOIN sand_system_role r ON r.id = ur.role_id'
            . ' WHERE ur.user_id = :id AND r.is_super = true',
            ['id' => $id],
        );

        return $found !== false && $found !== null;
    }

    /**
     * 只改本人能改的资料。部门、岗位、角色和启停状态保持原样。
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function updateProfile(int $id, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $before = $this->requireAlive($id);
        $nickname = OrgInput::requiredText($body, 'nickname');
        if (mb_strlen($nickname) > 20) {
            throw OrgException::invalid(['nickname' => ['姓名最多 20 个字']]);
        }
        $phone = OrgInput::requiredText($body, 'phone');
        if (preg_match('/^1[0-9]{10}$/', $phone) !== 1) {
            throw OrgException::invalid(['phone' => ['手机号要 11 位数字，请检查后再填']]);
        }
        $email = OrgInput::optionalText($body, 'email');
        if ($email !== null && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) !== 1) {
            throw OrgException::invalid(['email' => ['邮箱格式不对，例如 name@company.com']]);
        }
        $avatarId = array_key_exists('avatar_file_id', $body)
            ? OrgInput::optionalId($body, 'avatar_file_id')
            : (($before['avatar_file_id'] ?? null) === null ? null : OrgSql::int($before['avatar_file_id']));
        if ($avatarId !== null) {
            $found = OrgSql::int($this->connection->fetchOne('SELECT COUNT(*) FROM sand_system_file WHERE id = ?', [$avatarId]));
            if ($found !== 1) {
                throw OrgException::invalid(['avatar_file_id' => ['头像文件不存在']]);
            }
        }
        try {
            $this->connection->transactional(function () use ($id, $nickname, $phone, $email, $avatarId, $actor, $requestId, $ip, $device): void {
                $this->connection->executeStatement(
                    'UPDATE sand_system_user SET nickname = :nickname, phone = :phone, email = :email,'
                    . ' avatar_file_id = :avatar, updated_by = :by, update_time = CURRENT_TIMESTAMP'
                    . ' WHERE id = :id AND delete_time IS NULL',
                    [
                        'nickname' => $nickname,
                        'phone' => $phone,
                        'email' => $email,
                        'avatar' => $avatarId,
                        'by' => $actor->writerId(),
                        'id' => $id,
                    ],
                );
                $this->audit->record($actor, 'user', 'update', '保存了个人资料「' . $nickname . '」', 'user', (string) $id, $nickname, $requestId, $ip, $device);
            });
        } catch (\Throwable $error) {
            OrgDbMap::map($error);
        }

        return $this->profile($id);
    }

    /** @return array<string, mixed> */
    public function show(int $id): array
    {
        $row = $this->requireAlive($id);
        $roles = $this->rolesForUsers([$id]);
        $pair = $roles[$id] ?? ['ids' => [], 'names' => []];
        $item = $this->listItem($row, $pair['ids'], $pair['names']);
        $roleRows = [];
        foreach ($pair['ids'] as $index => $roleId) {
            $roleRows[] = ['id' => (string) $roleId, 'name' => $pair['names'][$index] ?? ''];
        }
        $post = null;
        if ($row['post_id'] !== null) {
            $post = ['id' => (string) OrgSql::int($row['post_id']), 'name' => OrgSql::str($row['post_name'] ?? null)];
        }
        $item['dept_path'] = $this->deptPath(OrgSql::int($row['dept_id'] ?? null));
        $item['post'] = $post;
        $item['roles'] = $roleRows;
        $item['permissions'] = OrgSql::int($row['is_super'] ?? 0) === 1 || ($row['is_super'] ?? null) === true || ($row['is_super'] ?? null) === 't'
            ? ['*']
            : $this->permissionCodes($id);

        return $item;
    }

    public function remove(int $id, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        if ($actor->userId === $id) {
            throw OrgException::invalid([], '不能删除你自己');
        }
        $row = $this->requireAlive($id);
        $this->guardLastAdmin($id, false);
        $nickname = OrgSql::str($row['nickname'] ?? null);
        $this->connection->transactional(function () use ($id, $actor, $nickname, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_user SET delete_time = CURRENT_TIMESTAMP, dept_id = NULL, update_time = CURRENT_TIMESTAMP'
                . ' WHERE id = ? AND delete_time IS NULL',
                [$id],
            );
            $this->connection->executeStatement(
                'UPDATE sand_system_dept SET leader_id = NULL WHERE leader_id = ?',
                [$id],
            );
            $this->revokeSessions($id, 'user_deleted');
            $this->audit->record($actor, 'user', 'delete', '删除了用户「' . $nickname . '」', 'user', (string) $id, $nickname, $requestId, $ip, $device);
        });
    }

    public function setStatus(int $id, int $status, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        if ($actor->userId === $id && $status === 2) {
            throw OrgException::invalid([], '不能停用你自己');
        }
        $row = $this->requireAlive($id);
        if ($status === 2) {
            $this->guardLastAdmin($id, false);
        }
        $nickname = OrgSql::str($row['nickname'] ?? null);
        $this->connection->transactional(function () use ($id, $status, $actor, $nickname, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_user SET status = :status, update_time = CURRENT_TIMESTAMP, updated_by = :by WHERE id = :id',
                ['status' => $status, 'by' => $actor->writerId(), 'id' => $id],
            );
            if ($status === 2) {
                $this->revokeSessions($id, 'user_disabled');
            }
            $verb = $status === 2 ? '停用了用户' : '启用了用户';
            $this->audit->record($actor, 'user', 'update', $verb . '「' . $nickname . '」', 'user', (string) $id, $nickname, $requestId, $ip, $device);
        });
    }

    /** @return list<array<string, mixed>> */
    public function disableCheck(int $id): array
    {
        $this->requireAlive($id);

        return [];
    }

    public function resetPassword(int $id, string $password, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        if (mb_strlen($password) < 6) {
            throw OrgException::invalid(['password' => ['密码太短了，至少要 6 位']]);
        }
        $row = $this->requireAlive($id);
        $hash = $this->hashPassword($password);
        $nickname = OrgSql::str($row['nickname'] ?? null);
        $this->connection->transactional(function () use ($id, $hash, $actor, $nickname, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_user SET password_hash = :hash, password_policy_tier = \'temp\','
                . ' must_change_password = true, must_change_reason = \'temp\', password_changed_time = CURRENT_TIMESTAMP,'
                . ' update_time = CURRENT_TIMESTAMP, updated_by = :by WHERE id = :id',
                ['hash' => $hash, 'by' => $actor->writerId(), 'id' => $id],
            );
            $this->revokeSessions($id, 'password_reset');
            $this->audit->record($actor, 'user', 'reset-password', '重置了用户「' . $nickname . '」的密码', 'user', (string) $id, $nickname, $requestId, $ip, $device);
        });
    }

    /** @param list<int> $roleIds */
    public function assignRoles(int $id, array $roleIds, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        if ($roleIds === []) {
            throw OrgException::invalid(['role_ids' => ['请至少选一个角色']]);
        }
        $row = $this->requireAlive($id);
        $this->assertRefs(OrgSql::int($row['dept_id'] ?? null), null, $roleIds);
        $flags = $this->flagsForRoles($roleIds);
        $stillEnabled = OrgSql::int($row['status'] ?? 1) === 1;
        $this->guardLastAdmin($id, $stillEnabled && $flags['is_super']);
        $nickname = OrgSql::str($row['nickname'] ?? null);
        $this->connection->transactional(function () use ($id, $roleIds, $flags, $actor, $nickname, $requestId, $ip, $device): void {
            $this->replaceRoles($id, $roleIds);
            $this->connection->executeStatement(
                'UPDATE sand_system_user SET is_super = :is_super, is_admin = :is_admin, authz_version = authz_version + 1,'
                . ' update_time = CURRENT_TIMESTAMP WHERE id = :id',
                [
                    'is_super' => $flags['is_super'] ? 'true' : 'false',
                    'is_admin' => $flags['is_admin'] ? 'true' : 'false',
                    'id' => $id,
                ],
            );
            $this->audit->record($actor, 'user', 'assign-role', '调整了用户「' . $nickname . '」的角色', 'user', (string) $id, $nickname, $requestId, $ip, $device);
        });
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{task_id: string, state: string}
     */
    public function export(array $filters, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $by = $actor->writerId();
        if ($by === null) {
            throw OrgException::unauthenticated();
        }
        $payload = json_encode([
            'keyword' => $filters['keyword'] ?? null,
            'status' => $filters['status'] ?? null,
            'dept_id' => $filters['dept_id'] ?? null,
            'role_id' => $filters['role_id'] ?? null,
            'post_id' => $filters['post_id'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            $payload = '{}';
        }
        $csv = $this->exportCsv($filters);
        $fileId = UserExportFile::write($this->connection, $csv, $by);
        $result = json_encode(['file_id' => $fileId], JSON_UNESCAPED_UNICODE);
        $publicId = OrgSql::str($this->connection->fetchOne(
            'INSERT INTO sand_system_task_run (type, payload, state, stage, progress, result, created_by, start_time, finish_time)'
            . ' VALUES (\'user.export\', CAST(:payload AS jsonb), \'succeeded\', \'已完成\', 100, CAST(:result AS jsonb), :by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            . ' RETURNING public_id::text',
            ['payload' => $payload, 'result' => is_string($result) ? $result : '{}', 'by' => $by],
        ));
        $this->audit->record($actor, 'user', 'export', '导出了用户列表', 'task', $publicId, '用户列表', $requestId, $ip, $device);

        return ['task_id' => $publicId, 'state' => 'succeeded'];
    }

    /** @param array<string, mixed> $filters */
    private function exportCsv(array $filters): string
    {
        $lines = ["\xEF\xBB\xBF" . '姓名,手机,登录账号,部门,岗位,状态'];
        $items = [];
        $page = 1;
        do {
            $query = $filters;
            $query['page'] = $page;
            $query['page_size'] = 100;
            $found = $this->search($query);
            $items = $found['items'];
            foreach ($items as $item) {
                $status = ($item['status'] ?? '') === 'enabled' ? '已启用' : '已停用';
                $lines[] = implode(',', [
                    $this->csvCell(is_string($item['nickname'] ?? null) ? $item['nickname'] : ''),
                    $this->csvCell(is_string($item['phone'] ?? null) ? $item['phone'] : ''),
                    $this->csvCell(is_string($item['username'] ?? null) ? $item['username'] : ''),
                    $this->csvCell(is_string($item['dept_name'] ?? null) ? $item['dept_name'] : ''),
                    $this->csvCell(is_string($item['post_name'] ?? null) ? $item['post_name'] : ''),
                    $this->csvCell($status),
                ]);
            }
            $page++;
        } while (count($items) === 100 && $page <= 100);

        return implode("\n", $lines) . "\n";
    }

    private function csvCell(string $value): string
    {
        $safe = $value;
        if ($safe !== '' && str_contains('=+-@', $safe[0])) {
            $safe = "'" . $safe;
        }
        $escaped = str_replace('"', '""', $safe);

        return '"' . $escaped . '"';
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function options(?string $keyword, ?int $deptId): array
    {
        $where = ['u.delete_time IS NULL'];
        $params = [];
        if ($keyword !== null && $keyword !== '') {
            $where[] = '(u.nickname ILIKE :kw OR u.phone ILIKE :kw OR COALESCE(u.username, \'\') ILIKE :kw)';
            $params['kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        if ($deptId !== null) {
            $where[] = 'u.dept_id = :dept';
            $params['dept'] = $deptId;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.id, u.nickname, u.dept_id, d.name AS dept_name, u.status'
            . ' FROM sand_system_user u LEFT JOIN sand_system_dept d ON d.id = u.dept_id'
            . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id',
            $params,
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (string) OrgSql::int($row['id'] ?? null),
                'nickname' => OrgSql::str($row['nickname'] ?? null),
                'avatar' => null,
                'dept_id' => (string) OrgSql::int($row['dept_id'] ?? null),
                'dept_name' => ($row['dept_name'] ?? null) === null ? null : OrgSql::str($row['dept_name']),
                'status' => OrgSql::statusName(OrgSql::int($row['status'] ?? 1)),
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{username: ?string, phone: string, nickname: string, email: ?string, dept_id: int, post_id: ?int, role_ids: list<int>, status: int, password: ?string}
     */
    private function draft(array $body, bool $withPassword): array
    {
        $nickname = OrgInput::requiredText($body, 'nickname');
        if (mb_strlen($nickname) > 20) {
            throw OrgException::invalid(['nickname' => ['姓名最多 20 个字']]);
        }
        $phone = OrgInput::requiredText($body, 'phone');
        if (preg_match('/^1[0-9]{10}$/', $phone) !== 1) {
            throw OrgException::invalid(['phone' => ['手机号要 11 位数字，请检查后再填']]);
        }
        $username = OrgInput::optionalText($body, 'username');
        if ($username !== null && preg_match('/^[A-Za-z0-9_]{4,20}$/', $username) !== 1) {
            throw OrgException::invalid(['username' => ['登录账号只能用字母、数字和下划线，4 到 20 位']]);
        }
        if ($username !== null && preg_match('/^[0-9]{11}$/', $username) === 1 && $username !== $phone) {
            throw OrgException::invalid(['username' => ['纯数字登录账号必须是本人手机号']]);
        }
        $email = OrgInput::optionalText($body, 'email');
        if ($email !== null && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) !== 1) {
            throw OrgException::invalid(['email' => ['邮箱格式不对，例如 name@company.com']]);
        }
        $password = null;
        if ($withPassword) {
            $password = OrgInput::requiredText($body, 'password');
            if (mb_strlen($password) < 6) {
                throw OrgException::invalid(['password' => ['密码太短了，至少要 6 位']]);
            }
        }
        $roleIds = OrgInput::idList($body, 'role_ids', true);
        if ($roleIds === []) {
            throw OrgException::invalid(['role_ids' => ['请至少选一个角色']]);
        }

        return [
            'username' => $username,
            'phone' => $phone,
            'nickname' => $nickname,
            'email' => $email,
            'dept_id' => OrgInput::requiredId($body, 'dept_id'),
            'post_id' => OrgInput::optionalId($body, 'post_id'),
            'role_ids' => $roleIds,
            'status' => OrgInput::status($body, 1),
            'password' => $password,
        ];
    }

    /** @param list<int> $roleIds */
    private function assertRefs(int $deptId, ?int $postId, array $roleIds): void
    {
        $dept = OrgSql::int($this->connection->fetchOne('SELECT COUNT(*) FROM sand_system_dept WHERE id = ?', [$deptId]));
        if ($dept !== 1) {
            throw OrgException::deptNotFound();
        }
        if ($postId !== null) {
            $post = OrgSql::int($this->connection->fetchOne('SELECT COUNT(*) FROM sand_system_post WHERE id = ?', [$postId]));
            if ($post !== 1) {
                throw OrgException::postNotFound();
            }
        }
        if ($roleIds === []) {
            return;
        }
        $found = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_role WHERE id IN (' . implode(',', $roleIds) . ')',
        ));
        if ($found !== count(array_unique($roleIds))) {
            throw OrgException::roleNotFound();
        }
    }

    /**
     * @param list<int> $roleIds
     *
     * @return array{is_super: bool, is_admin: bool}
     */
    private function flagsForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return ['is_super' => false, 'is_admin' => false];
        }
        $in = implode(',', $roleIds);
        $super = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_role WHERE is_super AND id IN (' . $in . ')',
        ));
        $admin = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_role_permission WHERE role_id IN (' . $in . ')'
            . ' AND permission_code IN (\'' . implode('\',\'', self::ADMIN_CODES) . '\')',
        ));

        return ['is_super' => $super > 0, 'is_admin' => $super > 0 || $admin > 0];
    }

    /**
     * @param array{username: ?string, phone: string, nickname: string, email: ?string, dept_id: int, post_id: ?int, role_ids: list<int>, status: int, password: ?string} $draft
     */
    private function insertUser(array $draft, bool $isSuper, bool $isAdmin, int $by): int
    {
        $password = $draft['password'] ?? '';
        if ($password === '') {
            throw OrgException::invalid(['password' => ['请填写临时密码']]);
        }
        $id = $this->connection->fetchOne(
            'INSERT INTO sand_system_user (username, phone, nickname, email, dept_id, post_id, password_hash,'
            . ' password_policy_tier, must_change_password, must_change_reason, is_super, is_admin, status, created_by, updated_by)'
            . ' VALUES (:username, :phone, :nickname, :email, :dept_id, :post_id, :password_hash,'
            . ' \'temp\', true, \'temp\', :is_super, :is_admin, :status, :by, :by) RETURNING id',
            [
                'username' => $draft['username'],
                'phone' => $draft['phone'],
                'nickname' => $draft['nickname'],
                'email' => $draft['email'],
                'dept_id' => $draft['dept_id'],
                'post_id' => $draft['post_id'],
                'password_hash' => $this->hashPassword($password),
                'is_super' => $isSuper ? 'true' : 'false',
                'is_admin' => $isAdmin ? 'true' : 'false',
                'status' => $draft['status'],
                'by' => $by > 0 ? $by : null,
            ],
        );

        return OrgSql::int($id);
    }

    /** @param list<int> $roleIds */
    private function replaceRoles(int $userId, array $roleIds): void
    {
        $this->connection->executeStatement('DELETE FROM sand_system_user_role WHERE user_id = ?', [$userId]);
        foreach (array_unique($roleIds) as $roleId) {
            $this->connection->insert('sand_system_user_role', ['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    private function revokeSessions(int $userId, string $reason): void
    {
        $this->connection->executeStatement(
            'UPDATE sand_system_session SET revoked_time = CURRENT_TIMESTAMP, revoked_reason = :reason'
            . ' WHERE user_id = :id AND revoked_time IS NULL',
            ['reason' => $reason, 'id' => $userId],
        );
    }

    /** 签发 7 天有效的新会话；ip 取不出合法值时写 0.0.0.0，会话表里不留空。 */
    private function issueSession(int $userId, string $token, ?string $ip, ?string $device): void
    {
        $address = $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
        $this->connection->executeStatement(
            'INSERT INTO sand_system_session (user_id, token_hash, auth_method, ip, device_label, expire_time)'
            . ' VALUES (:user, :hash, \'password\', :ip, :device, CURRENT_TIMESTAMP + INTERVAL \'7 days\')',
            [
                'user' => $userId,
                'hash' => hash('sha256', $token),
                'ip' => $address,
                'device' => $device === null ? '' : mb_substr($device, 0, 64),
            ],
        );
    }

    private function guardLastAdmin(int $userId, bool $remainsActiveSuper): void
    {
        if ($remainsActiveSuper) {
            return;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT is_super, status FROM sand_system_user WHERE id = ? AND delete_time IS NULL',
            [$userId],
        );
        if ($row === false) {
            return;
        }
        $isSuper = ($row['is_super'] ?? null) === true || ($row['is_super'] ?? null) === 't' || ($row['is_super'] ?? null) === 1 || ($row['is_super'] ?? null) === '1';
        $enabled = OrgSql::int($row['status'] ?? 2) === 1;
        if (!$isSuper || !$enabled) {
            return;
        }
        $others = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user WHERE is_super AND status = 1 AND delete_time IS NULL AND id <> ?',
            [$userId],
        ));
        if ($others === 0) {
            throw OrgException::lastAdmin();
        }
    }

    /** @return array<string, mixed> */
    private function requireAlive(int $id): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT u.id, u.username, u.nickname, u.phone, u.email, u.avatar_file_id, u.dept_id,'
            . ' d.name AS dept_name, u.post_id, p.name AS post_name, u.status, u.is_admin, u.is_super,'
            . ' u.must_change_password, u.last_login_time, u.create_time, u.password_changed_time'
            . ' FROM sand_system_user u'
            . ' LEFT JOIN sand_system_dept d ON d.id = u.dept_id'
            . ' LEFT JOIN sand_system_post p ON p.id = u.post_id'
            . ' WHERE u.id = ? AND u.delete_time IS NULL',
            [$id],
        );
        if ($row === false) {
            throw OrgException::userNotFound();
        }

        return $row;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array{ids: list<int>, names: list<string>}>
     */
    private function rolesForUsers(array $ids): array
    {
        $map = [];
        if ($ids === []) {
            return $map;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ur.user_id, r.id AS role_id, r.name FROM sand_system_user_role ur'
            . ' JOIN sand_system_role r ON r.id = ur.role_id'
            . ' WHERE ur.user_id IN (' . implode(',', $ids) . ') ORDER BY r.id',
        );
        foreach ($rows as $row) {
            $userId = OrgSql::int($row['user_id'] ?? null);
            if (!isset($map[$userId])) {
                $map[$userId] = ['ids' => [], 'names' => []];
            }
            $map[$userId]['ids'][] = OrgSql::int($row['role_id'] ?? null);
            $map[$userId]['names'][] = OrgSql::str($row['name'] ?? null);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<int> $roleIds
     * @param list<string> $roleNames
     *
     * @return array<string, mixed>
     */
    private function listItem(array $row, array $roleIds, array $roleNames): array
    {
        $isSuper = ($row['is_super'] ?? null) === true || ($row['is_super'] ?? null) === 't' || ($row['is_super'] ?? null) === 1 || ($row['is_super'] ?? null) === '1';
        $isAdmin = ($row['is_admin'] ?? null) === true || ($row['is_admin'] ?? null) === 't' || ($row['is_admin'] ?? null) === 1 || ($row['is_admin'] ?? null) === '1';
        $mustChange = ($row['must_change_password'] ?? null) === true || ($row['must_change_password'] ?? null) === 't' || ($row['must_change_password'] ?? null) === 1 || ($row['must_change_password'] ?? null) === '1';
        $roleIdText = [];
        foreach ($roleIds as $roleId) {
            $roleIdText[] = (string) $roleId;
        }
        $postId = $row['post_id'] ?? null;

        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'username' => ($row['username'] ?? null) === null ? null : OrgSql::str($row['username']),
            'nickname' => OrgSql::str($row['nickname'] ?? null),
            'avatar' => null,
            'avatar_file_id' => ($row['avatar_file_id'] ?? null) === null ? null : (string) OrgSql::int($row['avatar_file_id']),
            'phone' => OrgSql::str($row['phone'] ?? null),
            'email' => ($row['email'] ?? null) === null ? null : OrgSql::str($row['email']),
            'status' => OrgSql::statusName(OrgSql::int($row['status'] ?? 1)),
            'is_admin' => $isAdmin,
            'is_super' => $isSuper,
            'must_change_password' => $mustChange,
            'permissions' => $isSuper ? ['*'] : [],
            'dept_id' => (string) OrgSql::int($row['dept_id'] ?? null),
            'dept_name' => ($row['dept_name'] ?? null) === null ? null : OrgSql::str($row['dept_name']),
            'post_id' => $postId === null ? null : (string) OrgSql::int($postId),
            'post_name' => ($row['post_name'] ?? null) === null ? null : OrgSql::str($row['post_name']),
            'role_ids' => $roleIdText,
            'role_names' => $roleNames,
            'last_login_time' => OrgSql::time($row['last_login_time'] ?? null),
            'create_time' => OrgSql::time($row['create_time'] ?? null) ?? '',
        ];
    }

    /** @return list<string> */
    private function permissionCodes(int $userId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT rp.permission_code FROM sand_system_role_permission rp'
            . ' JOIN sand_system_user_role ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?',
            [$userId],
        );
        $codes = [];
        foreach ($rows as $code) {
            if (is_string($code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function deptPath(int $deptId): ?string
    {
        if ($deptId <= 0) {
            return null;
        }
        $names = [];
        $current = $deptId;
        $guard = 0;
        while ($current > 0 && $guard < 32) {
            $row = $this->connection->fetchAssociative('SELECT name, parent_id FROM sand_system_dept WHERE id = ?', [$current]);
            if ($row === false) {
                break;
            }
            array_unshift($names, OrgSql::str($row['name'] ?? null));
            $parent = $row['parent_id'] ?? null;
            $current = $parent === null ? 0 : OrgSql::int($parent);
            $guard++;
        }

        return $names === [] ? null : implode(' / ', $names);
    }

    /** @return list<int> */
    private function deptAndChildren(int $deptId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'WITH RECURSIVE tree AS ('
            . ' SELECT id FROM sand_system_dept WHERE id = :id'
            . ' UNION ALL SELECT d.id FROM sand_system_dept d JOIN tree t ON d.parent_id = t.id'
            . ') SELECT id FROM tree',
            ['id' => $deptId],
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = OrgSql::int($row);
        }
        if ($ids === []) {
            throw OrgException::deptNotFound();
        }

        return $ids;
    }

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    private function page(mixed $raw): int
    {
        $page = is_numeric($raw) ? (int) $raw : 1;
        if ($page < 1) {
            throw OrgException::invalid(['page' => ['页码不正确']]);
        }

        return $page;
    }

    private function pageSize(mixed $raw): int
    {
        $size = is_numeric($raw) ? (int) $raw : 20;
        if ($size < 1 || $size > 100) {
            throw OrgException::invalid(['page_size' => ['每页条数要在 1 到 100 之间']]);
        }

        return $size;
    }

    private function queryStatus(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($raw === 'enabled') {
            return 1;
        }
        if ($raw === 'disabled') {
            return 2;
        }
        throw OrgException::invalid(['status' => ['状态不正确']]);
    }

    private function queryId(mixed $raw, string $key): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || (is_string($raw) && preg_match('/^\d+$/', $raw) === 1)) {
            $id = (int) $raw;

            return $id > 0 ? $id : null;
        }
        throw OrgException::invalid([$key => ['编号不正确']]);
    }
}
