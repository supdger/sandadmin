<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Security;

/**
 * config/security.php `cors` 子树的类型化视图：v1 组跨域白名单。
 * 默认 allowed_origins 为空 = 仅同源；永不签发 Access-Control-Allow-Credentials（A-5）。
 */
final readonly class CorsConfig
{
    /**
     * @param list<string> $allowedOrigins 精确匹配的 Origin 白名单（已 parseOrigin 归一）
     * @param list<string> $allowedMethods
     * @param list<string> $allowedHeaders
     */
    public function __construct(
        public array $allowedOrigins,
        public array $allowedMethods,
        public array $allowedHeaders,
        public int $maxAge,
    ) {
    }

    /**
     * @param array<array-key, mixed> $config config('security') 原始数组
     */
    public static function fromArray(array $config): self
    {
        $cors = is_array($config['cors'] ?? null) ? $config['cors'] : [];

        return new self(
            allowedOrigins: self::readOrigins($cors['allowed_origins'] ?? null),
            allowedMethods: self::readStrings($cors['allowed_methods'] ?? null, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            allowedHeaders: self::readStrings(
                $cors['allowed_headers'] ?? null,
                ['Content-Type', 'Authorization', 'X-Requested-With', 'Idempotency-Key', 'X-Request-Id'],
            ),
            maxAge: self::readInt($cors, 'max_age', 600, 0, 86400),
        );
    }

    /** 从全局配置装载；测试构造注入 fromArray 结果，不经此入口。 */
    public static function fromConfig(): self
    {
        $raw = function_exists('config') ? config('security') : null;

        return self::fromArray(is_array($raw) ? $raw : []);
    }

    /**
     * 白名单命中判定：返回规范后的白名单条目（用于回写 ACAO，不回显请求原值）；
     * 未命中返回 null。不反射、不子串、不通配。
     */
    public function match(string $origin): ?string
    {
        $normalized = CsrfConfig::parseStrictOrigin($origin);
        if ($normalized === null) {
            return null;
        }

        return in_array($normalized, $this->allowedOrigins, true) ? $normalized : null;
    }

    /**
     * 预检 Access-Control-Allow-Headers：配置白名单并入 CSRF 标记头，大小写去重。
     *
     * @return list<string>
     */
    public function allowHeaders(string $requiredHeaderName): array
    {
        $merged = $this->allowedHeaders;
        $seen = array_map('strtolower', $merged);
        if (!in_array(strtolower($requiredHeaderName), $seen, true)) {
            $merged[] = $requiredHeaderName;
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    private static function readOrigins(mixed $raw): array
    {
        $origins = [];
        foreach (is_array($raw) ? $raw : [] as $origin) {
            if (!is_string($origin) || trim($origin) === '') {
                continue;
            }
            $parsed = CsrfConfig::parseStrictOrigin($origin);
            if ($parsed === null) {
                throw new \InvalidArgumentException(
                    "security.cors.allowed_origins 含非法源：{$origin}（应为 scheme://host[:port]）",
                );
            }
            if (!in_array($parsed, $origins, true)) {
                $origins[] = $parsed;
            }
        }

        return $origins;
    }

    /**
     * @param mixed $raw
     * @param list<string> $default
     * @return list<string>
     */
    private static function readStrings(mixed $raw, array $default): array
    {
        if (!is_array($raw)) {
            return $default;
        }
        $values = [];
        foreach ($raw as $value) {
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        return $values === [] ? $default : $values;
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
