<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Mcp\McpGate;

/** MCP 入口门槛：回环判定与 Origin 本机白名单。 */
final class McpGateTest extends TestCase
{
    public function testLoopback(): void
    {
        self::assertTrue(McpGate::isLoopback('127.0.0.1'));
        self::assertTrue(McpGate::isLoopback('::1'));
        self::assertTrue(McpGate::isLoopback('::ffff:127.0.0.1'));
        self::assertFalse(McpGate::isLoopback('192.168.1.10'));
        self::assertFalse(McpGate::isLoopback('10.0.0.2'));
        self::assertFalse(McpGate::isLoopback(''));
    }

    public function testOriginLocalhostAccepted(): void
    {
        self::assertTrue(McpGate::originAllowed('http://localhost:5173'));
        self::assertTrue(McpGate::originAllowed('http://127.0.0.1:9653'));
        self::assertTrue(McpGate::originAllowed('http://[::1]:8080'));
        self::assertTrue(McpGate::originAllowed('https://localhost'));
    }

    public function testOriginAbsentAllowed(): void
    {
        // 非浏览器客户端通常不带 Origin，放行；伪造的 'null' 拒绝
        self::assertTrue(McpGate::originAllowed(null));
        self::assertTrue(McpGate::originAllowed(''));
        self::assertFalse(McpGate::originAllowed('null'));
    }

    public function testOriginForeignRejected(): void
    {
        self::assertFalse(McpGate::originAllowed('https://evil.example.com'));
        self::assertFalse(McpGate::originAllowed('http://localhost.evil.example.com'));
        self::assertFalse(McpGate::originAllowed('not a url'));
        self::assertFalse(McpGate::originAllowed('file:///etc/passwd'));
    }
}
