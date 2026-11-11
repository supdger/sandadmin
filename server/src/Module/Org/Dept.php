<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * sand_system_dept 行值对象（部门）。
 * leader_id 是库里存的负责人引用，已删负责人的展示口径见 DeptLeader。
 */
final readonly class Dept implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $name,
        public ?int $leaderId,
        public ?string $phone,
        public int $sort,
        public int $status,
        public \DateTimeImmutable $createTime,
        public \DateTimeImmutable $updateTime,
    ) {
    }

    /**
     * @return array{id: int, parent_id: ?int, name: string, leader_id: ?int, phone: ?string,
     *     sort: int, status: int, create_time: string, update_time: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'leader_id' => $this->leaderId,
            'phone' => $this->phone,
            'sort' => $this->sort,
            'status' => $this->status,
            'create_time' => $this->createTime->format(DATE_ATOM),
            'update_time' => $this->updateTime->format(DATE_ATOM),
        ];
    }
}
