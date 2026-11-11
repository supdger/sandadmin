<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Webman\Http\Response;

/** 成功 JSON：{data, request_id}（api-conventions §2.1）。 */
final class JsonSuccessResponse
{
    public function fromPayload(mixed $payload, string $requestId): Response
    {
        if ($requestId === '') {
            throw new \InvalidArgumentException('request_id 不能为空');
        }
        $encoded = json_encode(
            [
                'data' => $payload,
                'request_id' => $requestId,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return new Response(200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Request-Id' => $requestId,
        ], $encoded);
    }
}
