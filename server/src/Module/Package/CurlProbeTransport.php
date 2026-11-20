<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Sandadmin\Domain\Http\OutboundException;
use Sandadmin\Domain\Http\OutboundHttpClientInterface;
use Sandadmin\Domain\Http\OutboundRequest;
use Sandadmin\Infra\Http\CurlOutboundClient;

/**
 * 回环出站探测。只放行字面回环地址：不解析 DNS、不接受任意主机名，
 * 任何外部更新源地址在这一层直接被拒（防 SSRF、防变相在线更新）。
 * 传输走统一出站客户端（src/Infra/Http），超时口径沿用旧实现：
 * 连接 1 秒、整体 3 秒、不跟跳转、不重试；回环地址进 SSRF 白名单。
 */
final class CurlProbeTransport implements ProbeTransport
{
    private const CONNECT_TIMEOUT_MS = 1000;

    private const TOTAL_TIMEOUT_MS = 3000;

    private const ALLOWED_HOSTS = ['127.0.0.1', 'localhost', '::1', '[::1]'];

    private ?OutboundHttpClientInterface $client;

    public function __construct(?OutboundHttpClientInterface $client = null)
    {
        $this->client = $client;
    }

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
        try {
            $response = $this->outbound()->request(new OutboundRequest('GET', $url, $headers));
        } catch (OutboundException $error) {
            throw new \RuntimeException('回环请求失败：' . $error->getMessage(), 0, $error);
        }

        return ['status' => $response->status, 'body' => $response->body];
    }

    private function outbound(): OutboundHttpClientInterface
    {
        return $this->client ??= CurlOutboundClient::fromConfig([
            'connect_timeout_ms' => self::CONNECT_TIMEOUT_MS,
            'read_timeout_ms' => self::TOTAL_TIMEOUT_MS,
            'total_timeout_ms' => self::TOTAL_TIMEOUT_MS,
            'max_attempts' => 1,
            'allow_private_hosts' => self::ALLOWED_HOSTS,
        ]);
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
