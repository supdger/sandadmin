<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Infra\Log\RequestScope;
use Webman\Http\Request;

final class RequestContextStoreTest extends TestCase
{
    private RequestScope $scope;

    private RequestContextStore $store;

    protected function setUp(): void
    {
        $this->scope = new RequestScope();
        $this->scope->clear();
        $this->store = new RequestContextStore(scope: $this->scope);
    }

    protected function tearDown(): void
    {
        $this->scope->clear();
    }

    public function testEnsureGeneratesIdAndBindsScope(): void
    {
        $context = $this->store->ensure(self::request());

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $context->requestId);
        self::assertSame($context->requestId, $this->scope->fields()['request_id'] ?? null);
    }

    public function testEnsurePassesThroughValidHeader(): void
    {
        $context = $this->store->ensure(self::request(['X-Request-Id' => 'req-deadbeef']));

        self::assertSame('req-deadbeef', $context->requestId);
        self::assertSame('req-deadbeef', $this->scope->fields()['request_id'] ?? null);
    }

    public function testEnsureRegeneratesOnUnsafeHeader(): void
    {
        $context = $this->store->ensure(self::request(['X-Request-Id' => "bad\nid"]));

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $context->requestId);
    }

    public function testEnsureReusesExistingContext(): void
    {
        $request = self::request(['X-Request-Id' => 'req-first111']);
        $first = $this->store->ensure($request);
        // 上下文已写入用户身份后再次 ensure 不得覆盖、不得换号
        $user = new \Sandadmin\Domain\Auth\AuthenticatedUser('user-1', 3);
        $this->store->set($request, new \Sandadmin\Domain\Auth\RequestContext($user, $first->requestId));

        $second = $this->store->ensure($request);

        self::assertSame($first->requestId, $second->requestId);
        self::assertSame('user-1', $second->user?->id);
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
