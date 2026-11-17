<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use support\exception\PageNotFoundException;
use support\Request;
use support\Response;

/**
 * SPA 入口回退：真实文件与路由都未命中的 GET 请求返回 index.html。
 * 由 config/route.php 的 Route::fallback 挂为全局兜底；webman 先走 findFile
 * 再走路由，所以真实静态文件不会重复进这里，nginx 反代拦截 /api 与静态文件
 * 时本回退也不会被触发。/api/** 与 /core/** 未命中和非 GET 请求重抛 404，
 * 与未注册 fallback 时的行为完全一致。
 */
final class SpaFallback
{
    /**
     * Route::fallback 要求 callable，必须保持无注入参数的单参签名：
     * fallback 的 inputs 会与查询参数合并，第二个参数会被 ?indexFile= 之类的
     * 请求参数喂进来，变成任意文件读取。
     */
    public static function respond(Request $request): Response
    {
        return self::serveIndex($request, public_path('index.html'));
    }

    /** @internal 与 respond() 分离便于单测注入入口文件路径；请求侧只用 respond()。 */
    public static function serveIndex(Request $request, string $indexFile): Response
    {
        if (!self::isSpaPath($request->method(), $request->path()) || !is_file($indexFile)) {
            throw new PageNotFoundException();
        }

        return (new Response())->file($indexFile);
    }

    /**
     * 只接管浏览器进 SPA 页面的 GET；HEAD/OPTIONS/POST 等一律回 404。
     * /api 与 /core 两个前缀是后端接口命名空间（/core 为插件 Route::group
     * 注册的接口组），未命中按 404 处理，不当作 SPA 页面。
     */
    public static function isSpaPath(string $method, string $path): bool
    {
        if ($method !== 'GET') {
            return false;
        }

        return !self::isApiPath($path, '/api') && !self::isApiPath($path, '/core');
    }

    private static function isApiPath(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
