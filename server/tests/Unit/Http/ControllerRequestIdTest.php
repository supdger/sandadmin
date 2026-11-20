<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\DashboardController;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Infra\Log\RequestScope;
use support\Request;

/**
 * 控制器信封 request_id 必须与 RequestScope 日志编号同源：
 * 控制器不再自建编号，一律经 RequestContextStore::ensure 取用。
 */
final class ControllerRequestIdTest extends TestCase
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

    public function testEnvelopeRequestIdMatchesScope(): void
    {
        $controller = new DashboardController(new RequestContextStore(scope: $this->scope));

        $response = $controller->onboarding(self::request(['X-Request-Id' => 'req-envelope001']));
        $body = self::body($response);

        self::assertSame('req-envelope001', $body['request_id'] ?? null);
        self::assertSame('req-envelope001', $response->getHeader('X-Request-Id'));
        self::assertSame('req-envelope001', $this->scope->fields()['request_id'] ?? null);
    }

    public function testGeneratedIdMatchesScopeWithoutHeader(): void
    {
        $controller = new DashboardController(new RequestContextStore(scope: $this->scope));

        $response = $controller->onboarding(self::request());
        $body = self::body($response);
        $requestId = $body['request_id'] ?? null;

        self::assertIsString($requestId);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $requestId);
        self::assertSame($requestId, $this->scope->fields()['request_id'] ?? null);
    }

    public function testConsecutiveRequestsDoNotShareScopeId(): void
    {
        $controller = new DashboardController(new RequestContextStore(scope: $this->scope));

        $first = self::body($controller->onboarding(self::request()));
        // 模拟请求结束：Webman\Context 按请求复位，作用域清空后进入下一请求
        $this->scope->clear();

        $second = self::body($controller->onboarding(self::request()));

        self::assertIsString($first['request_id'] ?? null);
        self::assertIsString($second['request_id'] ?? null);
        self::assertNotSame($first['request_id'], $second['request_id']);
        self::assertSame($second['request_id'], $this->scope->fields()['request_id'] ?? null);
    }

    /** @return array<string, mixed> */
    private static function body(\Webman\Http\Response $response): array
    {
        $decoded = json_decode($response->rawBody(), true);
        if (!is_array($decoded)) {
            return [];
        }
        /** @var array<string, mixed> $decoded */

        return $decoded;
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

        return new Request("GET /api/admin/v1/dashboard/onboarding HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
