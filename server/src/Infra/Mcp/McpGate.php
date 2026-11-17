<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp;

use support\Request;

/**
 * MCP 端点入口判定：真实回环对端 + 无转发头 + Origin 本机白名单。
 * 回环/转发头策略与 app\support\DevLoginGate 同源，但本类在 src 层
 * 不能反向依赖 app 壳，故自持一份纯函数实现。
 */
final class McpGate
{
    private const LOOPBACK_IPS = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];

    private const FORWARDED_HEADERS = ['x-forwarded-for', 'x-real-ip', 'forwarded'];

    private const ORIGIN_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    public static function remoteAllowed(Request $request): bool
    {
        return self::isLoopback($request->getRemoteIp())
            && !self::hasForwardedHeaders($request);
    }

    public static function isLoopback(string $remoteIp): bool
    {
        return in_array($remoteIp, self::LOOPBACK_IPS, true);
    }

    public static function hasForwardedHeaders(Request $request): bool
    {
        foreach (self::FORWARDED_HEADERS as $header) {
            $value = $request->header($header);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Origin 本机白名单：缺省放行（非浏览器客户端不带 Origin），
     * 'null' 与解析失败、非本机 host 一律拒绝。
     */
    public static function originAllowed(?string $origin): bool
    {
        if ($origin === null || trim($origin) === '') {
            return true;
        }
        $host = parse_url(trim($origin), PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), self::ORIGIN_HOSTS, true);
    }
}
