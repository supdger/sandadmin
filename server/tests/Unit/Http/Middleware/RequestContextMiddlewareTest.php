<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\Middleware\RequestContextMiddleware;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Infra\Log\RequestScope;
use Webman\Http\Request;
use Webman\Http\Response;

final class RequestContextMiddlewareTest extends TestCase
{
    private RequestScope $scope;

    protected function setUp(): void
    {
        $this->scope = new RequestScope();
        $this->scope->clear();
    }

    protected function tearDown(): void
    {
        $this->scope->clear();
    }

    public function testBindsRequestIdBeforeHandler(): void
    {
        $contexts = new RequestContextStore(scope: $this->scope);
        $seen = null;
        $response = (new RequestContextMiddleware($contexts))->process(
            self::request(['X-Request-Id' => 'req-pass1234']),
            function (Request $request) use (&$seen, $contexts): Response {
                $seen = $contexts->get($request)?->requestId;

                return new Response(200, [], 'ok');
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('req-pass1234', $seen);
        self::assertSame('req-pass1234', $this->scope->fields()['request_id'] ?? null);
    }

    public function testRejectsNonResponseFromHandler(): void
    {
        $this->expectException(\RuntimeException::class);
        (new RequestContextMiddleware())->process(
            self::request(),
            static fn (Request $request): mixed => 'not-a-response',
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private static function request(array $headers = []): Request
    {
        $block = '';
        foreach ($headers as $name => $value) {
            $block .= "{$name}: {$value}\r\n";
        }

        return new Request("GET /api/admin/v1/me HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
