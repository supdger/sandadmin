<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Http\OutboundRequest;

/** 出站请求值对象：方法/URL 归一校验、请求头键值类型与 CRLF 注入拦截。 */
final class OutboundRequestTest extends TestCase
{
    public function testHeadersAcceptedWhenClean(): void
    {
        $request = new OutboundRequest('get', 'http://127.0.0.1/', ['X-A' => 'v1', 'X-B' => 'line-part']);

        self::assertSame('GET', $request->method);
        self::assertSame(['X-A' => 'v1', 'X-B' => 'line-part'], $request->headers);
    }

    /**
     * @return array<string, array{0: array<array-key, mixed>}>
     */
    public static function crlfHeaders(): array
    {
        return [
            'name 夹带 CRLF' => [['X-A' . "\r\n" . 'Injected: yes' => 'v']],
            'name 单独 LF' => [["X-A\nInjected" => 'v']],
            'value 夹带 CRLF' => [['X-A' => 'v' . "\r\n" . 'Injected: yes']],
            'value 单独 CR' => [['X-A' => "v\rInjected"]],
        ];
    }

    /** @param array<array-key, mixed> $headers */
    #[DataProvider('crlfHeaders')]
    public function testHeaderCrLfRejected(array $headers): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OutboundRequest('GET', 'http://127.0.0.1/', $headers);
    }
}
