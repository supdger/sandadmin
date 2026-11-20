<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Http\TraceContext;

/** W3C trace-context 校验与生成：traceparent 格式、全零拒绝、tracestate 轻量校验。 */
final class TraceContextTest extends TestCase
{
    public function testGenerateProducesValidTraceparent(): void
    {
        $context = TraceContext::generate('req-1');
        self::assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/', $context->traceparent());
        self::assertSame('req-1', $context->requestId);
        self::assertNull($context->tracestate);
    }

    public function testContinueFromKeepsTraceAndSwapsParent(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);
        $context = TraceContext::continueFrom(
            '00-' . $traceId . '-' . $parentId . '-03',
            'vendor=1',
            'req-2',
        );

        self::assertNotNull($context);
        self::assertStringStartsWith('00-' . $traceId . '-', $context->traceparent());
        self::assertStringEndsWith('-03', $context->traceparent());
        self::assertStringNotContainsString($parentId, $context->traceparent());
        self::assertSame('vendor=1', $context->tracestate);
        self::assertSame('req-2', $context->requestId);
    }

    /** @return list<list<string>> */
    public static function invalidTraceparents(): array
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);

        return [
            ['garbage'],
            ['00-' . $traceId . '-' . $parentId], // 缺 flags
            ['ff-' . $traceId . '-' . $parentId . '-01'], // version ff 非法
            ['0g-' . $traceId . '-' . $parentId . '-01'],
            ['00-' . str_repeat('0', 32) . '-' . $parentId . '-01'], // 全零 trace-id
            ['00-' . $traceId . '-' . str_repeat('0', 16) . '-01'], // 全零 parent-id
            ['00-' . $traceId . '-' . $parentId . '-0g'], // flags 非 hex
            ['00-' . $traceId . '-' . $parentId . '-011'], // flags 长度错
        ];
    }

    #[DataProvider('invalidTraceparents')]
    public function testContinueFromRejectsInvalid(string $traceparent): void
    {
        self::assertNull(TraceContext::continueFrom($traceparent, null, 'req'));
    }

    public function testUppercaseHexAcceptedAndNormalized(): void
    {
        $context = TraceContext::continueFrom(
            '00-' . strtoupper(str_repeat('a', 32)) . '-' . strtoupper(str_repeat('b', 16)) . '-AB',
            null,
            'req',
        );

        self::assertNotNull($context);
        self::assertStringContainsString(str_repeat('a', 32), $context->traceparent());
        self::assertStringEndsWith('-ab', $context->traceparent());
    }

    public function testTracestateSanitized(): void
    {
        $valid = TraceContext::continueFrom(
            '00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-01',
            'a=b,c=d',
            'req',
        );
        self::assertSame('a=b,c=d', $valid?->tracestate);

        foreach (['no-equals-sign', 'a=b, bad member', str_repeat('x', 600)] as $bad) {
            $context = TraceContext::continueFrom(
                '00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-01',
                $bad,
                'req',
            );
            self::assertNotNull($context);
            self::assertNull($context->tracestate, '非法 tracestate 应被丢弃：' . $bad);
        }
    }

    public function testEmptyRequestIdRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TraceContext::generate('');
    }
}
