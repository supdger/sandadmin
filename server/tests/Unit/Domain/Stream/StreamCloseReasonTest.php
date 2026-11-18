<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamCloseReason;

final class StreamCloseReasonTest extends TestCase
{
    public function testErrorCodesUseRegisteredContractCodes(): void
    {
        self::assertSame('STREAM_IDLE_TIMEOUT', StreamCloseReason::Idle->errorCode());
        self::assertSame('STREAM_DURATION_LIMIT', StreamCloseReason::DurationLimit->errorCode());
        self::assertSame('STREAM_AUTHZ_REVOKED', StreamCloseReason::Revoked->errorCode());
        self::assertSame('INTERNAL_ERROR', StreamCloseReason::Failed->errorCode());
    }

    public function testGracefulReasonsCarryNoErrorCode(): void
    {
        self::assertNull(StreamCloseReason::Completed->errorCode());
        self::assertNull(StreamCloseReason::ClientGone->errorCode());
    }
}
