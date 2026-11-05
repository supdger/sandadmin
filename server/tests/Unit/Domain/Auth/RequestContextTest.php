<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Auth;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Auth\RequestContext;

final class RequestContextTest extends TestCase
{
    public function testKeepsUserPermissionVersionRequestIdAndTimezone(): void
    {
        $user = new AuthenticatedUser('user-1', 3, 'Asia/Shanghai');
        $context = new RequestContext($user, 3, 'req-12345678', new \DateTimeZone('Asia/Shanghai'));

        self::assertSame($user, $context->user);
        self::assertSame(3, $context->permissionVersion);
        self::assertSame('req-12345678', $context->requestId);
        self::assertSame('Asia/Shanghai', $context->timezone->getName());
    }

    public function testAnonymousContextAllowsVersionZero(): void
    {
        $context = new RequestContext(null, 0, 'req-abcdef12', new \DateTimeZone('UTC'));

        self::assertNull($context->user);
        self::assertSame(0, $context->permissionVersion);
    }

    public function testRejectsEmptyRequestId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestContext(null, 0, '', new \DateTimeZone('UTC'));
    }

    public function testRejectsPermissionVersionThatDiffersFromUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestContext(new AuthenticatedUser('user-1', 2), 9, 'req-abcdef12', new \DateTimeZone('UTC'));
    }

    public function testRejectsEmptyUserIdAndNegativePermissionVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuthenticatedUser('', 1);
    }
}
