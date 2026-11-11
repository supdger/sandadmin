<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * 删除部门预检结果（对应契约 DeptDeleteCheck）。
 * affected_roles 是「指定部门」数据范围在本部门删除后指定集合会变空的角色，
 * 删除成功时它们在同一事务被改成 data_scope='self'。
 */
final readonly class DeptDeleteCheck implements \JsonSerializable
{
    /** @param list<array{id: int, name: string}> $affectedRoles */
    public function __construct(
        public int $userCount,
        public int $childCount,
        public array $affectedRoles,
    ) {
    }

    /** @return array{user_count: int, child_count: int, affected_roles: list<array{id: int, name: string}>} */
    public function jsonSerialize(): array
    {
        return [
            'user_count' => $this->userCount,
            'child_count' => $this->childCount,
            'affected_roles' => $this->affectedRoles,
        ];
    }
}
