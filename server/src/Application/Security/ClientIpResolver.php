<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Security;

use support\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * 客户端 IP 唯一取口：TCP 对端不在 security.trusted_proxies CIDR 名单时
 * 一律返回对端，伪造转发头无效；对端可信时按 Forwarded（RFC 7239）
 * 优先、X-Forwarded-For 兜底，从右往左剥掉可信代理段取第一个不可信地址。
 * 名单为空（含生产未配置）时转发头一律忽略。
 */
final class ClientIpResolver
{
    /** 从全局配置装载；测试直接调 resolve 注入名单，不经此入口。 */
    public static function clientIp(Request $request): string
    {
        $raw = function_exists('config') ? config('security.trusted_proxies') : null;
        $proxies = [];
        if (is_array($raw)) {
            foreach ($raw as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $proxies[] = trim($value);
                }
            }
        }

        return self::resolve($request, $proxies);
    }

    /**
     * @param list<string> $trustedProxies IP 或 CIDR（IPv4/IPv6），对端命中才采信转发头
     */
    public static function resolve(Request $request, array $trustedProxies): string
    {
        $peer = trim($request->getRemoteIp());
        if ($peer === '' || $trustedProxies === []) {
            return $peer !== '' ? $peer : '0.0.0.0';
        }
        if (!self::isTrusted($peer, $trustedProxies)) {
            return $peer;
        }
        $ips = self::forwardedIps($request);
        if ($ips === []) {
            return $peer;
        }
        for ($index = count($ips) - 1; $index >= 0; $index--) {
            if (!self::isTrusted($ips[$index], $trustedProxies)) {
                return $ips[$index];
            }
        }

        return $ips[0];
    }

    /**
     * @param list<string> $trustedProxies
     */
    private static function isTrusted(string $ip, array $trustedProxies): bool
    {
        return IpUtils::checkIp($ip, $trustedProxies);
    }

    /**
     * 转发链地址，左起为最初客户端。Forwarded 头存在但没有合法 for= 时
     * 才回落 X-Forwarded-For；X-Real-IP / Client-IP / Via 不参与判定。
     *
     * @return list<string>
     */
    private static function forwardedIps(Request $request): array
    {
        $forwarded = $request->header('forwarded');
        if (is_string($forwarded) && trim($forwarded) !== '') {
            $ips = self::parseForwarded($forwarded);
            if ($ips !== []) {
                return $ips;
            }
        }
        $xff = $request->header('x-forwarded-for');
        if (!is_string($xff)) {
            return [];
        }
        $ips = [];
        foreach (explode(',', $xff) as $part) {
            $ip = self::normalizeIp(trim($part));
            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    /**
     * @return list<string>
     */
    private static function parseForwarded(string $header): array
    {
        $ips = [];
        foreach (explode(',', $header) as $element) {
            if (preg_match('/(?:^|;)\s*for\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;\s]+)/i', $element, $matches) !== 1) {
                continue;
            }
            $ip = self::normalizeIp($matches[1]);
            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    /**
     * 归一化转发节点为裸 IP：去引号、IPv6 方括号、IPv4:port 端口；
     * _hidden/unknown 等混淆标识与非法值一律返回 null 由调用方跳过。
     */
    private static function normalizeIp(string $node): ?string
    {
        $node = trim($node);
        if ($node === '') {
            return null;
        }
        if (str_starts_with($node, '"')) {
            $node = substr($node, 1);
            $endQuote = strrpos($node, '"');
            if ($endQuote !== false) {
                $node = substr($node, 0, $endQuote);
            }
        }
        if (str_starts_with($node, '[')) {
            $end = strpos($node, ']');
            if ($end === false) {
                return null;
            }
            $node = substr($node, 1, $end - 1);
        } elseif (substr_count($node, ':') === 1) {
            [$host, $port] = explode(':', $node, 2);
            if ($port !== '' && ctype_digit($port)) {
                $node = $host;
            }
        }

        return filter_var($node, FILTER_VALIDATE_IP) !== false ? $node : null;
    }
}
