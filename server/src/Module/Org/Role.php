<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * sand_system_role 行值对象（角色）。
 * data_scope 为字符串枚举：all / dept_and_child / dept / self / custom。
 */
final readonly class Role implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public ?string $remark,
        public string $dataScope,
        public bool $isSystem,
        public bool $isSuper,
        public int $status,
        public int $authzVersion,
        public \DateTimeImmutable $createTime,
        public \DateTimeImmutable $updateTime,
    ) {
    }

    /**
     * @return array{id: int, name: string, code: string, remark: ?string, data_scope: string,
     *     is_system: bool, is_super: bool, status: int, authz_version: int,
     *     create_time: string, update_time: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'remark' => $this->remark,
            'data_scope' => $this->dataScope,
            'is_system' => $this->isSystem,
            'is_super' => $this->isSuper,
            'status' => $this->status,
            'authz_version' => $this->authzVersion,
            'create_time' => $this->createTime->format(DATE_ATOM),
            'update_time' => $this->updateTime->format(DATE_ATOM),
        ];
    }
}
