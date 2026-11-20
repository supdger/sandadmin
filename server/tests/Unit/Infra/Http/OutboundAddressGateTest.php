<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Http\OutboundException;
use Sandadmin\Infra\Http\OutboundAddressGate;

/** SSRF 闸门：协议白名单、IP 网段矩阵、DNS 解析与主机白名单。 */
final class OutboundAddressGateTest extends TestCase
{
    /** @param list<string> $allowedHosts */
    private function gate(array $allowedHosts = [], ?\Closure $resolver = null): OutboundAddressGate
    {
        return new OutboundAddressGate($allowedHosts, $resolver);
    }

    /** @param array<int, string> $ips */
    private function resolverReturning(array $ips): \Closure
    {
        return static fn (string $host) => array_values($ips);
    }

    /** @param list<string> $urls */
    private function assertAllBlocked(array $urls, OutboundAddressGate $gate): void
    {
        foreach ($urls as $url) {
            try {
                $gate->resolve($url);
                self::fail('非公网目标被放行：' . $url);
            } catch (OutboundException $error) {
                self::assertSame('ssrf', $error->kind(), $url);
            }
        }
    }

    public function testSchemeWhitelist(): void
    {
        $gate = $this->gate(['127.0.0.1', 'localhost', '::1', '[::1]']);
        foreach (['ftp://127.0.0.1/x', 'file:///etc/passwd', 'gopher://127.0.0.1:70/x', 'dict://127.0.0.1/'] as $url) {
            try {
                $gate->resolve($url);
                self::fail('非法协议被放行：' . $url);
            } catch (OutboundException $error) {
                self::assertSame('ssrf', $error->kind(), $url);
            }
        }
    }

    public function testMissingHostRejected(): void
    {
        $this->assertAllBlocked(['http://', 'https://:8080/path'], $this->gate());
    }

    public function testPrivateAndReservedIpv4Blocked(): void
    {
        $this->assertAllBlocked([
            'http://127.0.0.1/',
            'http://127.53.0.9/',
            'http://10.0.0.1/',
            'http://10.255.255.255/',
            'http://172.16.0.1/',
            'http://172.31.255.1/',
            'http://192.168.1.1/',
            'http://169.254.169.254/',
            'http://0.0.0.0/',
            'http://100.64.0.1/',
            'http://198.18.0.1/',
            'http://224.0.0.1/',
            'http://239.255.255.255/',
            'http://240.0.0.1/',
            'http://255.255.255.255/',
            'http://192.0.2.10/',
            'http://198.51.100.7/',
            'http://203.0.113.9/',
        ], $this->gate());
    }

    public function testPublicIpv4Allowed(): void
    {
        $gate = $this->gate();
        foreach (['http://8.8.8.8/', 'https://1.1.1.1/x', 'http://93.184.216.34:8080/p'] as $url) {
            $ips = $gate->resolve($url);
            self::assertNotSame([], $ips, $url);
        }
        self::assertSame(['8.8.8.8'], $gate->resolve('http://8.8.8.8/'));
    }

    public function testPrivateAndReservedIpv6Blocked(): void
    {
        $this->assertAllBlocked([
            'http://[::1]/',
            'http://[::]/',
            'http://[fc00::1]/',
            'http://[fd12:3456::1]/',
            'http://[fe80::1]/',
            'http://[febf::1]/',
            'http://[ff02::1]/',
            'http://[::ffff:127.0.0.1]/',
            'http://[::ffff:10.0.0.1]/',
            'http://[::ffff:169.254.169.254]/',
            'http://[2001:db8::1]/',
        ], $this->gate());
    }

    public function testPublicIpv6Allowed(): void
    {
        $gate = $this->gate();
        self::assertSame(['2606:4700:4700::1111'], $gate->resolve('http://[2606:4700:4700::1111]/'));
    }

    public function testHostnameResolutionOutcomes(): void
    {
        $blocked = $this->gate([], $this->resolverReturning(['10.0.0.1']));
        try {
            $blocked->resolve('http://internal.test/');
            self::fail('解析到私网的主机名被放行');
        } catch (OutboundException $error) {
            self::assertSame('ssrf', $error->kind());
        }

        $mixed = $this->gate([], $this->resolverReturning(['8.8.8.8', '192.168.0.1']));
        try {
            $mixed->resolve('http://mixed.test/');
            self::fail('公网+私网混合解析结果被放行');
        } catch (OutboundException $error) {
            self::assertSame('ssrf', $error->kind());
        }

        $empty = $this->gate([], $this->resolverReturning([]));
        try {
            $empty->resolve('http://empty.test/');
            self::fail('解析为空的主机名被放行');
        } catch (OutboundException $error) {
            self::assertSame('ssrf', $error->kind());
        }

        $public = $this->gate([], $this->resolverReturning(['8.8.8.8', '1.1.1.1']));
        self::assertSame(['8.8.8.8', '1.1.1.1'], $public->resolve('http://public.test/'));
    }

    public function testWhitelistExactHost(): void
    {
        $gate = $this->gate(['db.local'], $this->resolverReturning(['10.1.2.3']));
        self::assertSame(['10.1.2.3'], $gate->resolve('http://db.local:8080/x'));
        // 白名单对字面 IP 同样生效：127.0.0.1 命中精确条目放行
        $gate = $this->gate(['127.0.0.1']);
        self::assertSame(['127.0.0.1'], $gate->resolve('http://127.0.0.1/'));
        // 未命中白名单的私网字面量仍被拒
        $this->assertAllBlocked(['http://127.0.0.2/'], $this->gate(['127.0.0.1']));
    }

    public function testWhitelistSuffix(): void
    {
        $gate = $this->gate(['*.internal.test', 'Corp.Example.com'], $this->resolverReturning(['192.168.10.1']));
        self::assertNotSame([], $gate->resolve('http://api.internal.test/'));
        self::assertNotSame([], $gate->resolve('http://deep.sub.internal.test/'));
        self::assertNotSame([], $gate->resolve('http://corp.example.com/'));
        // 裸域不匹配 *. 后缀规则；前缀相似的域名也不匹配
        try {
            $gate->resolve('http://internal.test/');
            self::fail('裸域被 *. 后缀规则放行');
        } catch (OutboundException) {
        }
        try {
            $gate->resolve('http://evilinternal.test/');
            self::fail('相似域名被 *. 后缀规则放行');
        } catch (OutboundException) {
        }
    }

    public function testLiteralIpSkipsResolver(): void
    {
        $gate = new OutboundAddressGate([], static function (string $host) {
            throw new \LogicException('IP 字面量不应触发 DNS 解析：' . $host);
        });
        self::assertSame(['8.8.8.8'], $gate->resolve('http://8.8.8.8/'));
    }

    public function testPortOutOfRangeRejected(): void
    {
        $this->assertAllBlocked(['http://8.8.8.8:99999/'], $this->gate());
    }
}
