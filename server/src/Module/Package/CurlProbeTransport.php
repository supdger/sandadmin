<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * cURL 回环出站。只放行字面回环地址：不解析 DNS、不接受任意主机名，
 * 任何外部更新源地址在这一层直接被拒（防 SSRF、防变相在线更新）。
 * 超时口径沿用旧实现：连接 1 秒、整体 3 秒，不走代理、不跟跳转。
 */
final class CurlProbeTransport implements ProbeTransport
{
    private const CONNECT_TIMEOUT = 1;

    private const TOTAL_TIMEOUT = 3;

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function get(string $url, array $headers): array
    {
        if (!self::isLoopbackUrl($url)) {
            throw new \InvalidArgumentException('探测目标不是本机回环地址，已拒绝发起请求');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL 不可用，无法发起回环探测');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('无法初始化回环请求');
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
            // 置空代理：回环请求必须直达本机，不能经环境变量里的代理转发
            CURLOPT_PROXY => '',
            CURLOPT_HTTPHEADER => $lines,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body)) {
            throw new \RuntimeException('回环请求失败：' . $error);
        }

        return ['status' => $status, 'body' => $body];
    }

    public static function isLoopbackUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !is_string($parts['scheme'] ?? null) || !is_string($parts['host'] ?? null)) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        return self::isLoopbackHost($parts['host']);
    }

    private static function isLoopbackHost(string $host): bool
    {
        $lower = strtolower($host);
        if ($lower === 'localhost' || $lower === '::1' || $lower === '[::1]') {
            return true;
        }
        if (filter_var($lower, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return str_starts_with($lower, '127.');
        }

        return false;
    }
}
