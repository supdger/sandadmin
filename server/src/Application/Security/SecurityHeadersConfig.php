<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Security;

/**
 * config/security.php `headers` 子树的类型化视图（N3）。
 * CSP 双值对应两步上线（NET-01）：enforce=false 发 Report-Only 灰度。
 */
final readonly class SecurityHeadersConfig
{
    public function __construct(
        public string $referrerPolicy,
        public int $hstsMaxAge,
        public string $cspApi,
        public string $cspPage,
        public bool $cspEnforce,
        public string $permissionsPolicy,
    ) {
    }

    /**
     * @param array<array-key, mixed> $config config('security') 原始数组
     */
    public static function fromArray(array $config): self
    {
        $headers = is_array($config['headers'] ?? null) ? $config['headers'] : [];
        $csp = is_array($headers['csp'] ?? null) ? $headers['csp'] : [];

        return new self(
            referrerPolicy: self::readString($headers, 'referrer_policy', 'strict-origin-when-cross-origin'),
            hstsMaxAge: self::readInt($headers, 'hsts_max_age', 15552000, 0, 31536000),
            cspApi: self::readString($csp, 'api', "default-src 'none'"),
            cspPage: self::readString($csp, 'page', "default-src 'self'"),
            cspEnforce: (bool) ($csp['enforce'] ?? false),
            permissionsPolicy: self::readString($headers, 'permissions_policy', 'camera=(), microphone=(), geolocation=()'),
        );
    }

    /** 从全局配置装载；测试构造注入 fromArray 结果，不经此入口。 */
    public static function fromConfig(): self
    {
        $raw = function_exists('config') ? config('security') : null;

        return self::fromArray(is_array($raw) ? $raw : []);
    }

    /**
     * 按响应 Content-Type 选 CSP 值与头名；含 json（application/json、
     * application/*+json、text/json 等）一律按 API 档收紧。
     *
     * @return array{name: string, value: string}
     */
    public function resolveCsp(string $contentType): array
    {
        return [
            'name' => $this->cspEnforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
            'value' => str_contains(strtolower($contentType), 'json') ? $this->cspApi : $this->cspPage,
        ];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function readString(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function readInt(array $config, string $key, int $default, int $min, int $max): int
    {
        $value = $config[$key] ?? null;
        if (!is_int($value)) {
            return $default;
        }

        return max($min, min($max, $value));
    }
}
