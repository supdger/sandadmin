<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\RequestIds;

final class RequestIdsTest extends TestCase
{
    private RequestIds $ids;

    protected function setUp(): void
    {
        $this->ids = new RequestIds();
    }

    public function testKeepsASafeIncomingId(): void
    {
        self::assertSame('req_1234-abcd', $this->ids->resolve('req_1234-abcd'));
    }

    public function testReplacesMissingUnsafeOrTooShortIds(): void
    {
        foreach ([null, '', "req-1\nSET", '../etc/passwd', 'short', 'has space'] as $incoming) {
            $generated = $this->ids->resolve($incoming);
            self::assertSame(32, strlen($generated));
            self::assertSame(1, preg_match('/^[a-f0-9]{32}$/', $generated));
            self::assertNotSame($incoming, $generated);
        }
    }
}
