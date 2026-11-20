<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Security;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Security\ClientIpResolver;
use support\Request;

/**
 * 客户端 IP 解析：不可信对端忽略一切转发头（伪造 XFF 拿不到假 IP）；
 * 可信对端按 Forwarded 优先、XFF 兜底，从右往左剥可信段。
 */
final class ClientIpResolverTest extends TestCase
{
    private const TRUSTED = ['10.0.0.0/8', '2001:db8::/32'];

    /** @param array<string, string> $headers */
    private function request(array $headers = [], string $remoteIp = '203.0.113.9'): Request
    {
        $buffer = "GET /api/admin/v1/ping HTTP/1.1\r\nHost: localhost\r\n";
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

    public function testUntrustedPeerIgnoresForgedForwardedHeaders(): void
    {
        $request = $this->request([
            'X-Forwarded-For' => '198.51.100.7',
            'X-Real-IP' => '198.51.100.8',
            'Forwarded' => 'for=198.51.100.9',
        ], '203.0.113.50');

        self::assertSame('203.0.113.50', ClientIpResolver::resolve($request, self::TRUSTED));
    }

    public function testTrustedPeerResolvesXffChain(): void
    {
        $request = $this->request(
            ['X-Forwarded-For' => '203.0.113.7, 10.0.0.2, unknown'],
            '10.0.0.1',
        );

        self::assertSame('203.0.113.7', ClientIpResolver::resolve($request, self::TRUSTED));
    }

    public function testForwardedHeaderWinsOverXff(): void
    {
        $request = $this->request([
            'Forwarded' => 'for=198.51.100.20;proto=https, for=10.0.0.3',
            'X-Forwarded-For' => '203.0.113.99',
        ], '10.0.0.1');

        self::assertSame('198.51.100.20', ClientIpResolver::resolve($request, self::TRUSTED));
    }

    public function testCidrMatchingIPv4AndIPv6(): void
    {
        $ipv4 = $this->request(['X-Forwarded-For' => '198.51.100.30'], '10.1.2.3');
        self::assertSame('198.51.100.30', ClientIpResolver::resolve($ipv4, self::TRUSTED));

        $ipv6 = $this->request(['X-Forwarded-For' => '198.51.100.40, "[2001:db8::2]:443"'], '2001:db8::1');
        self::assertSame('198.51.100.40', ClientIpResolver::resolve($ipv6, self::TRUSTED));
    }

    public function testEmptyTrustedProxiesIgnoresForwardedHeaders(): void
    {
        $request = $this->request(
            ['X-Forwarded-For' => '198.51.100.7', 'Forwarded' => 'for=198.51.100.9'],
            '10.0.0.1',
        );

        self::assertSame('10.0.0.1', ClientIpResolver::resolve($request, []));
    }

    public function testNoForwardedHeadersReturnsPeer(): void
    {
        $request = $this->request([], '10.0.0.1');

        self::assertSame('10.0.0.1', ClientIpResolver::resolve($request, self::TRUSTED));
    }

    public function testAllTrustedChainReturnsLeftmost(): void
    {
        $request = $this->request(['X-Forwarded-For' => '10.0.0.5, 10.0.0.6'], '10.0.0.1');

        self::assertSame('10.0.0.5', ClientIpResolver::resolve($request, self::TRUSTED));
    }

    public function testInvalidEntriesAreSkipped(): void
    {
        $request = $this->request(
            ['X-Forwarded-For' => 'not-an-ip, 203.0.113.8, 10.0.0.9'],
            '10.0.0.1',
        );

        self::assertSame('203.0.113.8', ClientIpResolver::resolve($request, self::TRUSTED));
    }
}
