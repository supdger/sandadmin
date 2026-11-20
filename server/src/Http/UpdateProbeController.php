<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Application\Security\ClientIpResolver;
use Sandadmin\Module\Package\UpdateChallenge;
use Sandadmin\Module\Package\UpdateProbe;
use support\Request;
use support\Response;

/**
 * 回环探测应答端点：只对实例自身应答。回环来源 + 无转发头 +
 * 未过期挑战 + token 逐字节一致才回显，其余一律 404。
 * 不挂会话鉴权：鉴权载体是探测发起方刚签发的一次性挑战，
 * 这是私有回环健康协议，不是公开业务接口（应答也不是契约信封）。
 */
final class UpdateProbeController
{
    /** 与旧端点同口径的转发头黑名单。 */
    private const FORWARDED_HEADERS = ['x-forwarded-for', 'x-real-ip', 'forwarded'];

    public function probe(Request $request): Response
    {
        if (self::hasForwardedHeaders($request)) {
            return new Response(404, [], 'Not Found');
        }
        $token = $request->header(UpdateProbe::TOKEN_HEADER);
        $payload = UpdateChallenge::forRuntime()->answer(
            ClientIpResolver::clientIp($request),
            is_string($token) ? $token : null,
        );
        if ($payload === null) {
            return new Response(404, [], 'Not Found');
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            is_string($body) ? $body : '{}',
        );
    }

    /** 代理转发头存在即拒：回环对端必须真实，不能由前置反代改写来源。 */
    private static function hasForwardedHeaders(Request $request): bool
    {
        foreach (self::FORWARDED_HEADERS as $header) {
            $value = $request->header($header);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
