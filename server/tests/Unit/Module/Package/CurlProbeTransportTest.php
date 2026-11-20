<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\CurlProbeTransport;
use Sandadmin\Tests\Support\FakeHttpServer;

/** 回环探测走统一出站客户端：仍只放行回环，真实 GET 经假服务验证。 */
final class CurlProbeTransportTest extends TestCase
{
    private ?FakeHttpServer $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;
    }

    public function testGetAgainstLoopbackServer(): void
    {
        $this->server = FakeHttpServer::start(['status' => 200, 'body' => '{"ok":true}']);
        $transport = new CurlProbeTransport();
        $reply = $transport->get(
            'http://127.0.0.1:' . $this->server->port . '/api/admin/v1/system/update/probe',
            ['x-sand-update-probe' => 'tok'],
        );

        self::assertSame(200, $reply['status']);
        self::assertSame('{"ok":true}', $reply['body']);
        $requests = $this->server->requests();
        self::assertCount(1, $requests);
        self::assertStringContainsString('x-sand-update-probe: tok', $requests[0]['head']);
        // 统一客户端注入的追踪头不破坏探测协议
        self::assertStringContainsString('traceparent: 00-', $requests[0]['head']);
    }

    public function testNonLoopbackStillRejectedBeforeTransport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CurlProbeTransport())->get('http://192.168.1.1:8787/x', []);
    }

    public function testLoopbackProbeBypassesEnvironmentProxy(): void
    {
        // 环境变量代理指向死端口：探测必须显式禁用代理，否则回环请求会被转发出去
        putenv('http_proxy=http://127.0.0.1:9');
        putenv('no_proxy=');
        try {
            $this->server = FakeHttpServer::start(['status' => 200, 'body' => '{"ok":true}']);
            $reply = (new CurlProbeTransport())->get(
                'http://127.0.0.1:' . $this->server->port . '/api/admin/v1/system/update/probe',
                [],
            );

            self::assertSame(200, $reply['status']);
            self::assertSame('{"ok":true}', $reply['body']);
        } finally {
            putenv('http_proxy');
            putenv('no_proxy');
        }
    }
}
