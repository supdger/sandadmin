<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** 一次请求里「谁在操作文件」的身份；权限码清单由上游（B2 合入后）给出。 */
final readonly class FileActor
{
    /** @param list<string> $permissions 持有的权限码；'*' 表示全部（超管） */
    public function __construct(
        public int $userId,
        public array $permissions,
    ) {
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id 必须为正整数');
        }
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true)
            || in_array('*', $this->permissions, true);
    }
}
