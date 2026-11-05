<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Repository\Demo;

use Sandadmin\Domain\Demo\DemoNote;
use Sandadmin\Infra\Db\Query\ListQuery;
use Sandadmin\Infra\Db\Query\Page;
use Sandadmin\Infra\Db\Repository;

/**
 * 示例 CRUD 仓储，表 sand_demo_note(id, title, status, created_at)。仅作 B1 证明与模板。
 *
 * @extends Repository<DemoNote>
 */
final class DemoNoteRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_demo_note';
    }

    protected function fieldMap(): array
    {
        return ['id' => 'id', 'title' => 'title', 'status' => 'status', 'created_at' => 'created_at'];
    }

    protected function hydrate(array $row): DemoNote
    {
        $id = $row['id'] ?? null;
        $title = $row['title'] ?? null;
        $status = $row['status'] ?? null;
        $created = $row['created_at'] ?? null;
        if (!is_numeric($id) || !is_string($title) || !is_numeric($status) || !is_string($created)) {
            throw new \UnexpectedValueException('sand_demo_note 行结构异常');
        }

        return new DemoNote((int) $id, $title, (int) $status, new \DateTimeImmutable($created));
    }

    public function find(int $id): ?DemoNote
    {
        $note = $this->findById($id);

        return $note instanceof DemoNote ? $note : null;
    }

    /** @return Page<DemoNote> */
    public function list(ListQuery $query): Page
    {
        return $this->paginate($query);
    }

    public function create(string $title, int $status, \DateTimeImmutable $now): DemoNote
    {
        $this->insertRow(['title' => $title, 'status' => $status, 'created_at' => $now->format('Y-m-d H:i:s')]);
        $id = (int) $this->connection->lastInsertId();

        return new DemoNote($id, $title, $status, $now);
    }

    public function rename(int $id, string $title): bool
    {
        return $this->updateRow($id, ['title' => $title]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->deleteRow($id) > 0;
    }
}