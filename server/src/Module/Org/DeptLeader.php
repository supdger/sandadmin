<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * 部门负责人展示视图（对应契约 Dept.leader_* 一组字段）。
 * leader_id 为空或对应用户已软删时展示字段全为 null（按「未设置」）；
 * 负责人 status=2 时 leader_status='disabled'，否则 'enabled'。
 * 契约字段 leader_avatar 是 URL，库里只存文件编号，由文件层在 HTTP 出口换算。
 */
final readonly class DeptLeader implements \JsonSerializable
{
    /** @param 'enabled'|'disabled'|null $leaderStatus */
    public function __construct(
        public int $deptId,
        public ?int $leaderId,
        public ?string $leaderName,
        public ?int $avatarFileId,
        public ?string $leaderStatus,
    ) {
    }

    /**
     * @return array{dept_id: int, leader_id: ?int, leader_name: ?string,
     *     leader_avatar_file_id: ?int, leader_status: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'dept_id' => $this->deptId,
            'leader_id' => $this->leaderId,
            'leader_name' => $this->leaderName,
            'leader_avatar_file_id' => $this->avatarFileId,
            'leader_status' => $this->leaderStatus,
        ];
    }
}
