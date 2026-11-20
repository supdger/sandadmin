<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Http;

/**
 * W3C trace-context（traceparent/tracestate）+ 出站沿用的 request id。
 * continueFrom 续接入站 trace-id 但换新的 parent-id（本端是新 span）；
 * 入站值非法时返回 null，由调用方 generate 新 trace。
 */
final readonly class TraceContext
{
    private function __construct(
        public string $version,
        public string $traceId,
        public string $parentId,
        public string $traceFlags,
        public ?string $tracestate,
        public string $requestId,
    ) {
    }

    /** 全新 trace：随机 trace-id/parent-id、flags 01、无 tracestate。 */
    public static function generate(string $requestId): self
    {
        self::assertRequestId($requestId);

        return new self('00', self::randomHex(16), self::randomHex(8), '01', null, $requestId);
    }

    /**
     * 校验并续接入站 traceparent。合法时保留 version/trace-id/flags 与
     * 通过轻量校验的 tracestate，parent-id 换新；非法返回 null。
     */
    public static function continueFrom(string $traceparent, ?string $tracestate, string $requestId): ?self
    {
        self::assertRequestId($requestId);
        $parts = explode('-', trim($traceparent));
        if (count($parts) !== 4) {
            return null;
        }
        [$version, $traceId, $parentId, $flags] = $parts;
        if (!self::isHex($version, 2) || strtolower($version) === 'ff'
            || !self::isHex($traceId, 32) || $traceId === str_repeat('0', 32)
            || !self::isHex($parentId, 16) || $parentId === str_repeat('0', 16)
            || !self::isHex($flags, 2)) {
            return null;
        }

        return new self(
            strtolower($version),
            strtolower($traceId),
            self::randomHex(8),
            strtolower($flags),
            self::sanitizeTracestate($tracestate),
            $requestId,
        );
    }

    /** 出站 traceparent 头值，格式 00-<trace-id>-<parent-id>-<flags>。 */
    public function traceparent(): string
    {
        return $this->version . '-' . $this->traceId . '-' . $this->parentId . '-' . $this->traceFlags;
    }

    private static function isHex(string $value, int $length): bool
    {
        return strlen($value) === $length && ctype_xdigit($value);
    }

    /** @param positive-int $bytes */
    private static function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private static function assertRequestId(string $requestId): void
    {
        if ($requestId === '') {
            throw new \InvalidArgumentException('request_id 不能为空');
        }
    }

    /** tracestate 只做结构性检查：member 形如 key=value，不合规整体丢弃不拒绝 trace。 */
    private static function sanitizeTracestate(?string $tracestate): ?string
    {
        if ($tracestate === null) {
            return null;
        }
        $value = trim($tracestate);
        if ($value === '' || strlen($value) > 512) {
            return null;
        }
        $members = explode(',', $value);
        if (count($members) > 32) {
            return null;
        }
        foreach ($members as $member) {
            if (preg_match('/^[\x21-\x7E]+=[\x20-\x7E]*$/', trim($member)) !== 1) {
                return null;
            }
        }

        return $value;
    }
}
