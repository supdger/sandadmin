<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Infra\Http\RequestTraceResolver;
use Webman\Http\Request;

/** 入站 trace 续接与 request id 复用：RequestContextStore 优先，其次 x-request-id 头。 */
final class RequestTraceResolverTest extends TestCase
{
    private function resolver(?Request $request = null, ?RequestContextStore $contexts = null): RequestTraceResolver
    {
        return new RequestTraceResolver(
            $contexts,
            null,
            null,
            $request === null ? static fn (): null => null : static fn (): Request => $request,
        );
    }

    private function request(string $headers = ''): Request
    {
        return new Request("GET /api/x HTTP/1.1\r\nHost: t\r\n{$headers}\r\n");
    }

    public function testNoRequestContextReturnsNullAndGeneratesId(): void
    {
        $resolver = $this->resolver();
        self::assertNull($resolver->resolve());
        $id = $resolver->requestId();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{8,128}$/', $id);
    }

    public function testInboundTraceContinuedWithNewParentId(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);
        $request = $this->request(
            'traceparent: 00-' . $traceId . '-' . $parentId . '-03' . "\r\n"
            . 'tracestate: vendor=abc' . "\r\n"
            . 'x-request-id: req-abc-123' . "\r\n",
        );
        $context = $this->resolver($request)->resolve();

        self::assertNotNull($context);
        self::assertStringStartsWith('00-' . $traceId . '-', $context->traceparent());
        self::assertStringEndsWith('-03', $context->traceparent());
        self::assertStringNotContainsString($parentId, $context->traceparent());
        self::assertSame('vendor=abc', $context->tracestate);
        self::assertSame('req-abc-123', $context->requestId);
    }

    public function testInvalidTraceparentReturnsNull(): void
    {
        $request = $this->request('traceparent: not-a-traceparent' . "\r\n");
        self::assertNull($this->resolver($request)->resolve());
        // request id 解析不受影响
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{8,128}$/', $this->resolver($request)->requestId());
    }

    public function testMalformedTracestateDroppedButTraceKept(): void
    {
        $request = $this->request(
            'traceparent: 00-' . str_repeat('c', 32) . '-' . str_repeat('d', 16) . '-01' . "\r\n"
            . 'tracestate: bad member with space' . "\r\n",
        );
        $context = $this->resolver($request)->resolve();

        self::assertNotNull($context);
        self::assertNull($context->tracestate);
    }

    public function testRequestContextStorePreferredOverHeader(): void
    {
        $contexts = new RequestContextStore();
        $request = $this->request('x-request-id: header-id-1' . "\r\n");
        $contexts->set($request, new RequestContext(null, 'ctx-req-9'));

        self::assertSame('ctx-req-9', $this->resolver($request, $contexts)->requestId());
        // 无 RequestContext 时回落 x-request-id 头（换一个未注入上下文的请求对象）
        $plain = $this->request('x-request-id: header-id-1' . "\r\n");
        self::assertSame('header-id-1', $this->resolver($plain)->requestId());
    }

    public function testNoTraceparentStillResolvesRequestId(): void
    {
        $request = $this->request('x-request-id: header-only-1' . "\r\n");
        $resolver = $this->resolver($request);

        self::assertNull($resolver->resolve());
        self::assertSame('header-only-1', $resolver->requestId());
    }
}
