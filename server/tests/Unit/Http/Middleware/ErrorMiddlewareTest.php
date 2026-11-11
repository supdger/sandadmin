<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Exception\InvalidRequestException;
use Sandadmin\Domain\Exception\SessionExpiredException;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Tests\Support\HttpStack;
use Sandadmin\Tests\Support\MapAuthenticator;
use Webman\Http\Request;
use Webman\Http\Response;

final class ErrorMiddlewareTest extends TestCase
{
    public function testMapsInvalidQueryExceptionToContractFieldErrors(): void
    {
        $stack = $this->stack();
        $request = self::request('X-Request-Id: req-12345678');
        $response = $stack->errors->process($request, static function (Request $request): Response {
            throw new InvalidQueryException('page_size 超出范围', ['page_size' => '每页条数最多 100']);
        });

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            [
                'code' => 'VALIDATION_FAILED',
                'message' => 'page_size 超出范围',
                'fields' => ['page_size' => ['每页条数最多 100']],
                'request_id' => 'req-12345678',
            ],
            $this->decode($response),
        );
        self::assertSame('req-12345678', $response->getHeader('X-Request-Id'));
        self::assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
        self::assertStringContainsString('"fields":{"page_size":["每页条数最多 100"]}', $response->rawBody());
    }

    public function testMapsAuthenticationFailuresToContractCodes(): void
    {
        $stack = $this->stack();
        $missing = $stack->errors->process(
            self::request('X-Request-Id: req-12345678'),
            fn (Request $request): Response => $stack->auth->process($request, static fn (Request $request): Response => new Response(204)),
        );
        self::assertSame(
            '{"code":"UNAUTHENTICATED","message":"请先登录","fields":{},"request_id":"req-12345678"}',
            $missing->rawBody(),
        );
        self::assertSame([], $stack->logger->entries);
        self::assertStringNotContainsString('good-token', $missing->rawBody());
    }

    public function testMapsExpiredSessionToSessionExpiredCode(): void
    {
        $stack = HttpStack::create(new class implements \Sandadmin\Domain\Auth\Authenticator {
            public function authenticate(string $token): AuthenticatedUser
            {
                throw new SessionExpiredException();
            }
        });
        $request = self::request("Cookie: sandadmin_session=old-token\r\nX-Request-Id: req-87654321\r\n");
        $response = $stack->errors->process(
            $request,
            fn (Request $request): Response => $stack->auth->process($request, static fn (Request $request): Response => new Response(204)),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            [
                'code' => 'SESSION_EXPIRED',
                'message' => '登录已过期，请重新登录',
                'fields' => [],
                'request_id' => 'req-87654321',
            ],
            $this->decode($response),
        );
    }

    public function testMapsInvalidRequestExceptionToValidationFailed(): void
    {
        $stack = $this->stack();
        $response = $stack->errors->process(self::request('X-Request-Id: req-12345678'), static function (Request $request): Response {
            throw new InvalidRequestException('请求头不合法', ['idempotency_key' => '长度不能超过 128']);
        });

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            [
                'code' => 'VALIDATION_FAILED',
                'message' => '请求头不合法',
                'fields' => ['idempotency_key' => ['长度不能超过 128']],
                'request_id' => 'req-12345678',
            ],
            $this->decode($response),
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
        self::assertSame('INTERNAL_ERROR', $body['code']);
        self::assertSame('系统出了点问题，请稍后再试', $body['message']);
        self::assertSame([], $body['fields']);
        self::assertSame(32, strlen($body['request_id']));
        self::assertStringNotContainsString('secret', $response->rawBody());
        $logged = $stack->logger->entries[0] ?? null;
        self::assertNotNull($logged);
        self::assertSame($body['request_id'], $logged['requestId']);
        self::assertSame('db password is secret', $logged['message']);
    }

    private function stack(): HttpStack
    {
        return HttpStack::create(new MapAuthenticator([
            'good-token' => new AuthenticatedUser('user-7', 4),
        ]));
    }

    private static function request(string $headers): Request
    {
        $block = $headers === '' ? '' : rtrim($headers, "\r\n") . "\r\n";

        return new Request("GET /api/admin/v1/me HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }

    /** @return array{code: string, message: string, fields: array<string, list<string>>, request_id: string} */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->rawBody(), true);
        self::assertIsArray($decoded);
        self::assertIsString($decoded['code'] ?? null);
        self::assertIsString($decoded['message'] ?? null);
        self::assertIsArray($decoded['fields'] ?? null);
        self::assertIsString($decoded['request_id'] ?? null);
        /** @var array<string, list<string>> $fields */
        $fields = $decoded['fields'];

        return [
            'code' => $decoded['code'],
            'message' => $decoded['message'],
            'fields' => $fields,
            'request_id' => $decoded['request_id'],
        ];
    }
}
