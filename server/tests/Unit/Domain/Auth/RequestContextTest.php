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
    public function testKeepsUserRequestIdAndAuthzVersion(): void
    {
        $user = new AuthenticatedUser('user-1', 3);
        $context = new RequestContext($user, 'req-12345678');

        self::assertSame($user, $context->user);
        self::assertSame(3, $context->authzVersion());
        self::assertSame('req-12345678', $context->requestId);
    }

    public function testAnonymousContextHasAuthzVersionZero(): void
    {
        $context = new RequestContext(null, 'req-abcdef12');

        self::assertNull($context->user);
        self::assertSame(0, $context->authzVersion());
    }

    public function testRejectsEmptyRequestId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestContext(null, '');
    }

    public function testRejectsEmptyUserId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuthenticatedUser('', 1);
    }

    public function testRejectsZeroAuthzVersionOnUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuthenticatedUser('user-1', 0);
    }
}
