<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\App;

use app\support\DevLoginGate;
use PHPUnit\Framework\TestCase;
use support\Request;
use support\exception\PageNotFoundException;

/** 开发登录放行判定：开关、回环、转发头三面；转发头出现即拒绝。 */
final class DevLoginGateTest extends TestCase
{
    /** @param array<string, string> $headers */
    private function request(array $headers = [], string $remoteIp = '127.0.0.1'): Request
    {
        $buffer = "POST /api/auth/dev-login HTTP/1.1\r\nHost: localhost\r\n";
        foreach ($headers as $name => $value) {
            $buffer .= "$name: $value\r\n";
        }
        $buffer .= "Content-Length: 0\r\n\r\n";

        return new class($buffer, $remoteIp) extends Request {
            public function __construct(string $buffer, private readonly string $fakeIp)
            {
                parent::__construct($buffer);
            }

            public function getRemoteIp(): string
            {
                return $this->fakeIp;
            }
        };
    }

    protected function tearDown(): void
    {
        putenv('SAND_DEV_LOGIN');
    }

    public function testAllowsLoopbackWhenEnabled(): void
    {
        putenv('SAND_DEV_LOGIN=1');
        self::assertTrue(DevLoginGate::allowed($this->request()));
        self::assertTrue(DevLoginGate::allowed($this->request([], '::1')));
        self::assertTrue(DevLoginGate::allowed($this->request([], '::ffff:127.0.0.1')));
    }

    public function testRejectsWhenDisabled(): void
    {
        putenv('SAND_DEV_LOGIN=0');
        self::assertFalse(DevLoginGate::allowed($this->request()));
    }

    public function testRejectsNonLoopback(): void
    {
        putenv('SAND_DEV_LOGIN=1');
        self::assertFalse(DevLoginGate::allowed($this->request([], '10.0.0.9')));
    }

    public function testRejectsAnyForwardedHeader(): void
    {
        putenv('SAND_DEV_LOGIN=1');
        foreach (['X-Forwarded-For', 'X-Real-IP', 'Forwarded'] as $name) {
            $request = $this->request([$name => '203.0.113.9']);
            self::assertTrue(DevLoginGate::hasForwardedHeaders($request), $name);
            self::assertFalse(DevLoginGate::allowed($request), $name);
        }
    }

    public function testControllerThrows404OnForwardedHeader(): void
    {
        putenv('SAND_DEV_LOGIN=1');
        $controller = new \app\controller\DevLoginController();
        $this->expectException(PageNotFoundException::class);
        $controller->devLogin($this->request(['X-Forwarded-For' => '1.2.3.4']));
    }

    public function testAvailableFalseWhenNotAllowed(): void
    {
        putenv('SAND_DEV_LOGIN=0');
        $controller = new \app\controller\DevLoginController();
        $response = $controller->available($this->request());
        $payload = json_decode($response->rawBody(), true);
        $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        self::assertFalse($data['available'] ?? true);

        putenv('SAND_DEV_LOGIN=1');
        $response = $controller->available($this->request());
        $payload = json_decode($response->rawBody(), true);
        $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        self::assertTrue($data['available'] ?? false);
        self::assertSame('admin', $data['username'] ?? '');
    }
}
