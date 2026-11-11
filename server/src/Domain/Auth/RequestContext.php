<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Auth;

/**
 * 单个请求的身份与元数据。只随该请求的对象传递，禁止放进进程级可变状态。
 * 权限版本只从用户快照读取，匿名请求恒为 0。
 */
final readonly class RequestContext
{
    public function __construct(
        public ?AuthenticatedUser $user,
        public string $requestId,
    ) {
        if ($this->requestId === '') {
            throw new \InvalidArgumentException('request_id 不能为空');
        }
    }

    public function authzVersion(): int
    {
        $user = $this->user;

        return $user === null ? 0 : $user->authzVersion;
    }
}
