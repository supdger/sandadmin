<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Auth;

/**
 * 单个请求的身份与元数据。只随该请求的对象传递，禁止放进进程级可变状态。
 */
final readonly class RequestContext
{
    public function __construct(
        public ?AuthenticatedUser $user,
        public int $permissionVersion,
        public string $requestId,
        public \DateTimeZone $timezone,
    ) {
        if ($this->requestId === '') {
            throw new \InvalidArgumentException('request_id 不能为空');
        }
        if ($this->permissionVersion < 0) {
            throw new \InvalidArgumentException('permission version 必须 ≥ 0');
        }
        if ($this->user !== null && $this->user->permissionVersion !== $this->permissionVersion) {
            throw new \InvalidArgumentException('permission version 必须与用户一致');
        }
    }
}
