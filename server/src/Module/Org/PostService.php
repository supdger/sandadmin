<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;

/** 岗位。删除后用户的岗位由外键置空。 */
final class PostService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly OrgAudit $audit,
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
        }
        $sqlWhere = implode(' AND ', $where);
        $total = OrgSql::int($this->connection->fetchOne('SELECT COUNT(*) FROM sand_system_post WHERE ' . $sqlWhere, $params));
        $params['lim'] = $pageSize;
        $params['off'] = ($page - 1) * $pageSize;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, remark, sort, status, create_time,'
            . ' (SELECT COUNT(*) FROM sand_system_user u WHERE u.post_id = sand_system_post.id AND u.delete_time IS NULL) AS user_count'
            . ' FROM sand_system_post WHERE ' . $sqlWhere . ' ORDER BY sort, id LIMIT :lim OFFSET :off',
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
    public function create(array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $draft = $this->draft($body);
        try {
            $id = OrgSql::int($this->connection->transactional(function () use ($draft, $actor, $requestId, $ip, $device): int {
                $newId = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_post (name, remark, sort, status, created_by, updated_by)'
                    . ' VALUES (:name, :remark, :sort, :status, :by, :by) RETURNING id',
                    [
                        'name' => $draft['name'],
                        'remark' => $draft['remark'],
                        'sort' => $draft['sort'],
                        'status' => $draft['status'],
                        'by' => $actor->writerId(),
                    ],
                ));
                $this->audit->record($actor, 'post', 'create', '添加了岗位' . $draft['name'], 'post', (string) $newId, $draft['name'], $requestId, $ip, $device);

                return $newId;
            }));
        } catch (\Throwable $error) {
            OrgDbMap::map($error);
        }

        return $this->show($id);
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
        try {
            $this->connection->transactional(function () use ($id, $draft, $actor, $before, $requestId, $ip, $device): void {
                $this->connection->executeStatement(
                    'UPDATE sand_system_post SET name = :name, remark = :remark, sort = :sort, status = :status,'
                    . ' updated_by = :by, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                    [
                        'name' => $draft['name'],
                        'remark' => $draft['remark'],
                        'sort' => $draft['sort'],
                        'status' => $draft['status'],
                        'by' => $actor->writerId(),
                        'id' => $id,
                    ],
                );
                $this->audit->record(
                    $actor,
                    'post',
                    'update',
                    '保存了岗位' . $draft['name'],
                    'post',
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

    public function remove(int $id, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $row = $this->requireRow($id);
        $name = OrgSql::str($row['name'] ?? null);
        $this->connection->transactional(function () use ($id, $actor, $name, $requestId, $ip, $device): void {
            $this->connection->executeStatement('DELETE FROM sand_system_post WHERE id = ?', [$id]);
            $this->audit->record($actor, 'post', 'delete', '删除了岗位' . $name, 'post', (string) $id, $name, $requestId, $ip, $device);
        });
    }

    /** @return array<string, mixed> */
    public function show(int $id): array
    {
        return $this->item($this->requireRow($id));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{name: string, remark: ?string, sort: int, status: int}
     */
    private function draft(array $body): array
    {
        $name = OrgInput::requiredText($body, 'name');
        if (mb_strlen($name) > 20) {
            throw OrgException::invalid(['name' => ['岗位名称最多 20 个字']]);
        }
        $remark = OrgInput::optionalText($body, 'remark');
        if ($remark !== null && mb_strlen($remark) > 100) {
            throw OrgException::invalid(['remark' => ['说明最多 100 个字']]);
        }

        return [
            'name' => $name,
            'remark' => $remark,
            'sort' => OrgInput::sort($body),
            'status' => OrgInput::status($body, 1),
        ];
    }

    /** @return array<string, mixed> */
    private function requireRow(int $id): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, remark, sort, status, create_time,'
            . ' (SELECT COUNT(*) FROM sand_system_user u WHERE u.post_id = sand_system_post.id AND u.delete_time IS NULL) AS user_count'
            . ' FROM sand_system_post WHERE id = ?',
            [$id],
        );
        if ($row === false) {
            throw OrgException::postNotFound();
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'name' => OrgSql::str($row['name'] ?? null),
            'remark' => ($row['remark'] ?? null) === null ? null : OrgSql::str($row['remark']),
            'sort' => OrgSql::int($row['sort'] ?? 0),
            'status' => OrgSql::statusName(OrgSql::int($row['status'] ?? 1)),
            'user_count' => OrgSql::int($row['user_count'] ?? 0),
            'create_time' => OrgSql::time($row['create_time'] ?? null) ?? '',
        ];
    }
}
