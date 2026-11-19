<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Security;

/**
 * A-5 CSRF 配置视图：本站源（site_origin）+ 写操作必带的自定义请求头。
 * siteOrigin 为 null 表示未配置——中间件跳过 Origin/Referer 校验但仍要求标记头。
 * 源一律经 parseOrigin 归一（小写、无尾斜杠、折叠 http/https 默认端口），比对用严格相等。
 */
final readonly class CsrfConfig
{
    public function __construct(
        public ?string $siteOrigin,
        public string $requiredHeaderName,
        public ?string $requiredHeaderValue,
    ) {
    }

    /**
     * @param array<array-key, mixed> $config config('security') 原始数组
     */
    public static function fromArray(array $config): self
    {
        $csrf = is_array($config['csrf'] ?? null) ? $config['csrf'] : [];
        $siteOrigin = $config['site_origin'] ?? null;
        $headerName = $csrf['required_header_name'] ?? null;
        $headerValue = $csrf['required_header_value'] ?? null;

        return new self(
            siteOrigin: self::readSiteOrigin($siteOrigin),
            requiredHeaderName: is_string($headerName) && trim($headerName) !== ''
                ? trim($headerName)
                : 'X-Requested-With',
            requiredHeaderValue: is_string($headerValue) && trim($headerValue) !== '' ? trim($headerValue) : null,
        );
    }

    /** 从全局配置装载；测试构造注入 fromArray 结果，不经此入口。 */
    public static function fromConfig(): self
    {
        $raw = function_exists('config') ? config('security') : null;

        return self::fromArray(is_array($raw) ? $raw : []);
    }

    /**
     * 严格形态校验：只允许 http(s)://host[:port]，含 userinfo/路径/query/fragment、
     * 通配符或非 http/https scheme 即 null；parse_url 会吞掉 userinfo，宽松解析有伪装面。
     */
    public static function parseStrictOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        $parts = parse_url($origin);
        if (
            !is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
            || str_contains($origin, '*')
        ) {
            return null;
        }
        $scheme = $parts['scheme'] ?? null;
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return self::parseOrigin($origin);
    }

    /**
     * 归一化 origin/URL 为 scheme://host[:port]：小写、去尾斜杠、折叠默认端口。
     * 解析不出 scheme+host 即返回 null（调用方按不匹配处理）。
     */
    public static function parseOrigin(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return null;
        }
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
            return null;
        }
        $scheme = strtolower($scheme);
        $origin = $scheme . '://' . strtolower($host);
        $port = $parts['port'] ?? null;
        if (is_int($port) && !self::isDefaultPort($scheme, $port)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    /** 部署值容错后的形态校验：只允许 scheme://host[:port]，路径/认证信息视为配置错误。 */
    private static function readSiteOrigin(mixed $raw): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $value = trim($raw);
        $parsed = self::parseStrictOrigin($value);
        if ($parsed === null) {
            throw new \InvalidArgumentException(
                "security.site_origin 配置非法：{$value}（应为 scheme://host[:port]，不含路径/认证信息）",
            );
        }

        return $parsed;
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
    }
}
