<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Infra\Mcp\McpGate;
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
    public function probe(Request $request): Response
    {
        if (McpGate::hasForwardedHeaders($request)) {
            return new Response(404, [], 'Not Found');
        }
        $token = $request->header(UpdateProbe::TOKEN_HEADER);
        $payload = UpdateChallenge::forRuntime()->answer(
            $request->getRemoteIp(),
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
}
