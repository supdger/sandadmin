<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * sand_system_user 行值对象（后台管理账号）。
 * 不含 password_hash 等口令字段，序列化键与列同名。
 */
final readonly class User implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public ?string $username,
        public string $phone,
        public string $nickname,
        public ?string $email,
        public ?int $avatarFileId,
        public ?int $deptId,
        public ?int $postId,
        public bool $isSuper,
        public bool $isAdmin,
        public int $authzVersion,
        public int $status,
        public ?\DateTimeImmutable $lastLoginTime,
        public \DateTimeImmutable $createTime,
        public \DateTimeImmutable $updateTime,
        public ?\DateTimeImmutable $deleteTime,
    ) {
    }

    /**
     * @return array{id: int, username: ?string, phone: string, nickname: string, email: ?string,
     *     avatar_file_id: ?int, dept_id: ?int, post_id: ?int, is_super: bool, is_admin: bool,
     *     authz_version: int, status: int, last_login_time: ?string, create_time: string,
     *     update_time: string, delete_time: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'phone' => $this->phone,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'avatar_file_id' => $this->avatarFileId,
            'dept_id' => $this->deptId,
            'post_id' => $this->postId,
            'is_super' => $this->isSuper,
            'is_admin' => $this->isAdmin,
            'authz_version' => $this->authzVersion,
            'status' => $this->status,
            'last_login_time' => $this->lastLoginTime?->format(DATE_ATOM),
            'create_time' => $this->createTime->format(DATE_ATOM),
            'update_time' => $this->updateTime->format(DATE_ATOM),
            'delete_time' => $this->deleteTime?->format(DATE_ATOM),
        ];
    }
}
