<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\DemoNote;

use Sandadmin\Infra\Db\Query\Page;
use Sandadmin\Infra\Db\Repository;
use Sandadmin\Infra\Db\Row;

/**
 * sand_demo_note 的演示仓储：B1 基类用法模板。
 * 表只在集成测试中自建，不进生产迁移。
 *
 * @extends Repository<DemoNote>
 */
final class DemoNoteRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_demo_note';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'title', 'status', 'create_time'];
    }

    /** @return array<string, string> */
    protected function fieldMap(): array
    {
        return ['id' => 'id', 'title' => 'title', 'status' => 'status', 'create_time' => 'create_time'];
    }

    /** @return list<string> */
    protected function likeFields(): array
    {
        return ['title'];
    }

    protected function hydrate(Row $row): DemoNote
    {
        return new DemoNote(
            $row->int('id'),
            $row->string('title'),
            $row->int('status'),
            $row->dateTime('create_time'),
        );
    }

    public function find(int $id): ?DemoNote
    {
        return $this->findById($id);
    }

    /** @return Page<DemoNote> */
    public function list(DemoNoteListQuery $query): Page
    {
        $qb = $this->qb()->from($this->table());
        $this->whereLikePrefix($qb, 'title', $query->keyword, 'keyword');
        $this->whereEq($qb, 'status', $query->status, 'status');
        $this->whereGte($qb, 'create_time', $query->createTimeFrom, 'create_time_from');
        $this->whereLte($qb, 'create_time', $query->createTimeTo, 'create_time_to');

        return $this->paginate($query, $qb);
    }

    public function create(string $title, int $status, \DateTimeImmutable $now): DemoNote
    {
        $this->insertRow(['title' => $title, 'status' => $status, 'create_time' => $now]);
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
