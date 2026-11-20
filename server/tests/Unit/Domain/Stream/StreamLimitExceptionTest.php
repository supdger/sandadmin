<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamLimitException;

final class StreamLimitExceptionTest extends TestCase
{
    public function testMapsTo429WithRegisteredCode(): void
    {
        $exception = new StreamLimitException();

        self::assertSame('STREAM_LIMIT_REACHED', $exception->errorCode());
        self::assertSame(429, $exception->httpStatus());
    }
}
