<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\HealthController;
use support\Request;

final class HealthControllerTest extends TestCase
{
    public function testLiveReturns200JsonWithoutTouchingDependencies(): void
    {
        $controller = new HealthController(static fn (): string => 'probe-message');
        $response = $controller->live(self::request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
        self::assertSame(
            ['status' => 'ok', 'message' => 'probe-message'],
            json_decode($response->rawBody(), true),
        );
    }

    public function testLiveFallsBackToCodeWhenMessageIsEmpty(): void
    {
        $controller = new HealthController(static fn (): string => '');
        $response = $controller->live(self::request());

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        self::assertIsArray($body);
        self::assertSame('live', $body['message'] ?? null);
    }

    private static function request(): Request
    {
        return new Request("GET /health/live HTTP/1.1\r\nHost: example.test\r\n\r\n");
    }
}
