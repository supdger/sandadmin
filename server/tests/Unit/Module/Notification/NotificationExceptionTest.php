<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Notification;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Notification\NotificationException;

/** 消息中心异常工厂：状态码与契约错误码一一对应。 */
final class NotificationExceptionTest extends TestCase
{
    public function testInvalidKeepsFields(): void
    {
        $e = NotificationException::invalid(['idempotency_key' => ['幂等键不正确']]);
        self::assertSame(400, $e->status);
        self::assertSame(ErrorCodeRegistry::VALIDATION_FAILED, $e->errorCode);
        self::assertSame(['idempotency_key' => ['幂等键不正确']], $e->fields);
    }

    public function testInvalidDefaultMessage(): void
    {
        $e = NotificationException::invalid([]);
        self::assertSame('请求内容有误，请按提示修改', $e->getMessage());
        self::assertSame([], $e->fields);
    }

    public function testNotFound(): void
    {
        $e = NotificationException::notFound();
        self::assertSame(404, $e->status);
        self::assertSame(ErrorCodeRegistry::NOT_FOUND, $e->errorCode);
        self::assertSame('这条消息不存在', $e->getMessage());
    }

    public function testUnauthenticated(): void
    {
        $e = NotificationException::unauthenticated();
        self::assertSame(401, $e->status);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $e->errorCode);
    }
}
