<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Security;

/**
 * A-5 CSRF 配置视图：本站源（site_origin）+ 写操作必带的自定义请求头。
 * siteOrigin 为 null 表示未配置——中间件跳过 Origin/Referer 校验但仍要求标记头。
 * 源一律小写规范（scheme+host[:port]），与请求头比对用严格相等。
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
        $headerValue = $csrf['required_header_value'] ?? null;

        return new self(
            siteOrigin: is_string($siteOrigin) && trim($siteOrigin) !== '' ? self::normalizeOrigin($siteOrigin) : null,
            requiredHeaderName: is_string($csrf['required_header_name'] ?? null) && trim($csrf['required_header_name']) !== ''
                ? trim($csrf['required_header_name'])
                : 'X-Requested-With',
            requiredHeaderValue: is_string($headerValue) && trim($headerValue) !== '' ? $headerValue : null,
        );
    }

    /** 从全局配置装载；测试构造注入 fromArray 结果，不经此入口。 */
    public static function fromConfig(): self
    {
        $raw = function_exists('config') ? config('security') : null;

        return self::fromArray(is_array($raw) ? $raw : []);
    }

    /** 部署值容错：去空白、去尾部斜杠、统一小写（scheme/host 大小写不敏感）。 */
    public static function normalizeOrigin(string $origin): string
    {
        return strtolower(rtrim(trim($origin), '/'));
    }
}
