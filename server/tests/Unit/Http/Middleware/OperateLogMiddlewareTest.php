<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\Middleware\OperateLogMiddleware;
use support\Request;
use Webman\Http\Response;

/**
 * OperateLogMiddleware 的链契约：挂在 '@' 全局层包住插件请求，
 * 响应照常透传、下游异常先记失败再原样抛出；未标记请求上
 * recordOutcome 是空操作，这两步在单测里可安全走到。
 */
final class OperateLogMiddlewareTest extends TestCase
{
    private function request(string $raw): Request
    {
        return new class($raw) extends Request {
            public function __construct(string $buffer)
            {
                parent::__construct($buffer);
            }
        };
    }

    public function testMiddlewareWiredForAllApps(): void
    {
        $config = include dirname(__DIR__, 4) . '/config/middleware.php';

        self::assertIsArray($config);
        self::assertSame([OperateLogMiddleware::class], $config['@'] ?? null);
    }

    public function testResponsePassesThrough(): void
    {
        $request = $this->request("POST /core/user/clearCache HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $expected = new Response(200, ['Content-Type' => 'application/json'], '{"code":200}');

        $response = (new OperateLogMiddleware())->process($request, fn () => $expected);

        self::assertSame($expected, $response);
    }

    public function testDownstreamThrowableRethrown(): void
    {
        $request = $this->request("POST /core/user/clearCache HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $error = new \RuntimeException('downstream');

        try {
            (new OperateLogMiddleware())->process($request, function () use ($error): Response {
                throw $error;
            });
            self::fail('下游异常应原样抛出');
        } catch (\Throwable $thrown) {
            self::assertSame($error, $thrown);
        }
    }
}
