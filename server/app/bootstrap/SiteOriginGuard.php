<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\bootstrap;

use InvalidArgumentException;
use RuntimeException;
use Sandadmin\Application\Security\CsrfConfig;
use Webman\Bootstrap;

/**
 * A-5 启动自检：生产环境（APP_ENV=production|prod）必须配置 security.site_origin，
 * 未配置即拒绝启动；形态非法同样拒启（CsrfConfig 抛 InvalidArgumentException）。
 * 开发环境未配置时放行，中间件只做标记头校验。只在启动期判定，不持有请求状态。
 */
final class SiteOriginGuard implements Bootstrap
{
    /**
     * @param array<array-key, mixed>|null $securityConfig 测试注入；空取 config('security')
     * @param null|callable(object): void $halt 测试注入；默认让主进程停止，避免 worker 重启风暴
     */
    public static function start(mixed $worker, ?array $securityConfig = null, ?callable $halt = null): void
    {
        try {
            $siteOrigin = CsrfConfig::fromArray($securityConfig ?? self::securityConfig())->siteOrigin;
            self::assertConfigured(self::appEnv(), $siteOrigin);
        } catch (RuntimeException | InvalidArgumentException $error) {
            fwrite(STDERR, '[sandadmin] ' . $error->getMessage() . PHP_EOL);
            if (is_object($worker)) {
                ($halt ?? ProductionDebugGuard::haltMaster(...))($worker);
            }
            throw $error;
        }
    }

    /** @throws RuntimeException */
    public static function assertConfigured(mixed $appEnv, ?string $siteOrigin): void
    {
        if (ProductionDebugGuard::isProduction($appEnv) && $siteOrigin === null) {
            throw new RuntimeException('生产环境（APP_ENV=production）必须配置 security.site_origin（env SAND_SITE_ORIGIN），未配置时 v1 写操作不校验请求来源，请配置后再启动');
        }
    }

    /** @return array<array-key, mixed> */
    private static function securityConfig(): array
    {
        $config = config('security', []);

        return is_array($config) ? $config : [];
    }

    private static function appEnv(): mixed
    {
        $env = getenv('APP_ENV');

        return $env === false ? ($_ENV['APP_ENV'] ?? null) : $env;
    }
}
