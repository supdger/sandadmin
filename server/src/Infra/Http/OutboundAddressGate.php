<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Http;

use Sandadmin\Domain\Http\OutboundException;

/**
 * 出站 SSRF 闸门：只放行 http/https；主机名解析出全部地址后逐一校验，
 * 回环/私网/链路本地/组播/保留/未指定一律拒绝，命中 allow 名单的主机
 * 才放行内网地址。resolve() 返回解析结果供调用方钉住 DNS（防 rebinding）。
 */
final class OutboundAddressGate
{
    /**
     * @param list<string> $allowedHosts 精确 host 或 *.后缀，大小写不敏感
     * @param null|\Closure(string): array<array-key, string> $resolver 测试注入的 DNS 替身；
     *        缺省走 dns_get_record(A+AAAA)，解析不到再退 gethostbynamel
     */
    public function __construct(
        private readonly array $allowedHosts = [],
        private readonly ?\Closure $resolver = null,
    ) {
    }

    /**
     * 校验 URL 并返回应钉住的解析地址；URL 非法、解析失败或命中非公网
     * 且未白名单时抛 OutboundException(kind=ssrf)。
     *
     * @return list<string>
     */
    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new OutboundException('出站 URL 无法解析', 'ssrf');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new OutboundException('出站仅允许 http/https 协议', 'ssrf');
        }
        $host = $parts['host'] ?? null;
        if (!is_string($host)) {
            throw new OutboundException('出站 URL 缺少主机名', 'ssrf');
        }
        $host = self::normalizeHost($host);
        $port = $parts['port'] ?? null;
        if ($port !== null && $port < 1) {
            throw new OutboundException('出站 URL 端口非法', 'ssrf');
        }

        $ips = $this->resolveHost($host);
        if ($ips === []) {
            throw new OutboundException('出站主机名解析失败或无有效地址：' . $host, 'ssrf');
        }
        $whitelisted = $this->hostAllowed($host);
        foreach ($ips as $ip) {
            if (self::isNonPublicIp($ip) && !$whitelisted) {
                throw new OutboundException('出站目标落在非公网网段：' . $host, 'ssrf');
            }
        }

        return $ips;
    }

    /** IPv6 字面量带方括号（parse_url 原样返回），去掉后再判定与白名单匹配。 */
    private static function normalizeHost(string $host): string
    {
        $host = trim($host);
        if (strlen($host) > 2 && $host[0] === '[' && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return rtrim($host, '.');
    }

    /** @return list<string> */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $ips = $this->resolver !== null
            ? ($this->resolver)($host)
            : self::lookup($host);

        return self::onlyIps($ips);
    }

    /** @return list<string> */
    private static function lookup(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? null;
                if (is_string($ip)) {
                    $ips[] = $ip;
                }
                $ipv6 = $record['ipv6'] ?? null;
                if (is_string($ipv6)) {
                    $ips[] = $ipv6;
                }
            }
        }
        if ($ips === []) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
        }

        return self::onlyIps($ips);
    }

    /**
     * @param array<array-key, mixed> $ips
     *
     * @return list<string>
     */
    private static function onlyIps(array $ips): array
    {
        $valid = [];
        foreach ($ips as $ip) {
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $valid[$ip] = true;
            }
        }

        return array_keys($valid);
    }

    private function hostAllowed(string $host): bool
    {
        $host = strtolower($host);
        foreach ($this->allowedHosts as $rule) {
            $rule = strtolower(trim($rule));
            if ($rule === '') {
                continue;
            }
            if (str_starts_with($rule, '*.')) {
                // '*.example.com' 匹配任意一级子域，不匹配裸域 example.com
                if (str_ends_with($host, substr($rule, 1)) && $host !== substr($rule, 2)) {
                    return true;
                }
                continue;
            }
            if ($host === $rule) {
                return true;
            }
        }

        return false;
    }

    /** 回环/私网/链路本地/组播/保留/未指定都算非公网；解析不了的按非公网拒绝。 */
    public static function isNonPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $packed = inet_pton($ip);

            return $packed === false || self::isNonPublicV4($packed);
        }
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return true;
        }

        return self::isNonPublicV6($packed);
    }

    /** @param string $packed inet_pton 的 4 字节 */
    private static function isNonPublicV4(string $packed): bool
    {
        $ip = (int) hexdec(bin2hex($packed));
        // [网络号, 掩码]：覆盖 0/8、10/8、100.64/10、127/8、169.254/16、
        // 172.16/12、192.0.0/24、192.0.2/24、192.168/16、198.18/15、
        // 198.51.100/24、203.0.113/24、224/4 组播、240/4 保留
        $ranges = [
            [0x00000000, 0xFF000000],
            [0x0A000000, 0xFF000000],
            [0x64400000, 0xFFC00000],
            [0x7F000000, 0xFF000000],
            [0xA9FE0000, 0xFFFF0000],
            [0xAC100000, 0xFFF00000],
            [0xC0000000, 0xFFFFFF00],
            [0xC0000200, 0xFFFFFF00],
            [0xC0A80000, 0xFFFF0000],
            [0xC6120000, 0xFFFE0000],
            [0xC6336400, 0xFFFFFF00],
            [0xCB007100, 0xFFFFFF00],
            [0xE0000000, 0xF0000000],
            [0xF0000000, 0xF0000000],
        ];
        foreach ($ranges as [$net, $mask]) {
            if (($ip & $mask) === $net) {
                return true;
            }
        }

        return false;
    }

    /** @param string $packed inet_pton 的 16 字节 */
    private static function isNonPublicV6(string $packed): bool
    {
        $zeros = str_repeat("\0", 16);
        // :: 与 ::1 及 ::/96 兼容地址：前 12 字节全零时按末 4 字节走 IPv4 规则
        if (substr($packed, 0, 12) === str_repeat("\0", 12)) {
            return self::isNonPublicV4(substr($packed, 12));
        }
        // ::ffff:a.b.c.d IPv4 映射地址先解映射再按 IPv4 规则判
        if (substr($packed, 0, 10) === str_repeat("\0", 10) && substr($packed, 10, 2) === "\xff\xff") {
            return self::isNonPublicV4(substr($packed, 12));
        }
        $first = ord($packed[0]);
        $second = ord($packed[1]);
        if ($first === 0xFF) {
            return true; // ff00::/8 组播
        }
        if (($first & 0xFE) === 0xFC) {
            return true; // fc00::/7 本地唯一
        }
        if ($first === 0xFE && ($second & 0xC0) === 0x80) {
            return true; // fe80::/10 链路本地
        }
        // 2001:db8::/32 文档保留段
        return $first === 0x20 && $second === 0x01
            && ord($packed[2]) === 0x0D && ord($packed[3]) === 0xB8;
    }
}
