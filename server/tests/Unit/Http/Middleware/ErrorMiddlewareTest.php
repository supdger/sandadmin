<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Exception\InvalidRequestException;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Tests\Support\HttpStack;
use Sandadmin\Tests\Support\MapAuthenticator;
use Webman\Http\Request;
use Webman\Http\Response;

final class ErrorMiddlewareTest extends TestCase
{
    public function testMapsInvalidQueryExceptionToFieldErrors(): void
    {
        $stack = $this->stack();
        $request = self::request('X-Request-Id: req-12345678');
        $response = $stack->errors->process($request, static function (Request $request): Response {
            throw new InvalidQueryException('page_size 超出范围', ['page_size' => 'between:1,200']);
        });

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            [
                'code' => 'invalid_query',
                'message' => 'page_size 超出范围',
                'fields' => ['page_size' => 'between:1,200'],
                'request_id' => 'req-12345678',
            ],
            $this->decode($response),
        );
        self::assertSame('req-12345678', $response->getHeader('X-Request-Id'));
        self::assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
        self::assertStringContainsString('"fields":{', $response->rawBody());
    }

    public function testMapsAuthenticationAndTimezoneFailures(): void
    {
        $stack = $this->stack();
        $missing = $stack->errors->process(
            self::request('X-Request-Id: req-12345678'),
            fn (Request $request): Response => $stack->auth->process($request, static fn (Request $request): Response => new Response(204)),
        );
        self::assertSame(
            '{"code":"unauthenticated","message":"未登录","fields":{},"request_id":"req-12345678"}',
            $missing->rawBody(),
        );
        self::assertSame([], $stack->logger->entries);
        self::assertStringNotContainsString('good-token', $missing->rawBody());

        $timezone = $stack->errors->process(
            self::request("Authorization: Bearer good-token\r\nX-Request-Id: req-87654321\r\nX-Timezone: Not/AZone"),
            fn (Request $request): Response => $stack->auth->process($request, static fn (Request $request): Response => new Response(204)),
        );
        self::assertSame(400, $timezone->getStatusCode());
        self::assertSame(
            [
                'code' => 'invalid_request',
                'message' => '时区无效',
                'fields' => ['timezone' => 'timezone'],
                'request_id' => 'req-87654321',
            ],
            $this->decode($timezone),
        );
    }

    public function testUnknownErrorsOmitInternalsAndStillReturnRequestId(): void
    {
        $stack = $this->stack();
        $response = $stack->errors->process(self::request(''), static function (Request $request): Response {
            throw new \RuntimeException('db password is secret');
        });
        $body = $this->decode($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('internal_error', $body['code']);
        self::assertSame('服务器内部错误', $body['message']);
        self::assertSame([], $body['fields']);
        self::assertSame(32, strlen($body['request_id']));
        self::assertStringNotContainsString('secret', $response->rawBody());
        $logged = $stack->logger->entries[0] ?? null;
        self::assertNotNull($logged);
        self::assertSame($body['request_id'], $logged['requestId']);
        self::assertSame('db password is secret', $logged['message']);
    }

    public function testDirectInvalidRequestExceptionUsesTheSameShape(): void
    {
        $stack = $this->stack();
        $response = $stack->errors->process(self::request('X-Request-Id: req-12345678'), static function (Request $request): Response {
            throw new InvalidRequestException('时区无效', ['timezone' => 'timezone']);
        });
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('req-12345678', $this->decode($response)['request_id']);
    }

    private function stack(): HttpStack
    {
        return HttpStack::create(new MapAuthenticator([
            'good-token' => new AuthenticatedUser('user-7', 4, 'UTC'),
        ]));
    }

    private static function request(string $headers): Request
    {
        $block = $headers === '' ? '' : rtrim($headers, "\r\n") . "\r\n";

        return new Request("GET /api/notes HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }

    /** @return array{code: string, message: string, fields: array<string, string>, request_id: string} */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->rawBody(), true);
        self::assertIsArray($decoded);
        self::assertIsString($decoded['code'] ?? null);
        self::assertIsString($decoded['message'] ?? null);
        self::assertIsArray($decoded['fields'] ?? null);
        self::assertIsString($decoded['request_id'] ?? null);
        /** @var array<string, string> $fields */
        $fields = $decoded['fields'];

        return [
            'code' => $decoded['code'],
            'message' => $decoded['message'],
            'fields' => $fields,
            'request_id' => $decoded['request_id'],
        ];
    }
}
