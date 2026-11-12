<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Application\Error\ErrorBody;
use Webman\Http\Response;

/** 错误 JSON：{code,message,fields,request_id}。fields 始终是对象。 */
final class JsonErrorResponse
{
    public function fromBody(ErrorBody $body): Response
    {
        $encoded = json_encode(
            [
                'code' => $body->code,
                'message' => $body->message,
                'fields' => $body->fields === [] ? new \stdClass() : $body->fields,
                'request_id' => $body->requestId,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return new Response($body->status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Request-Id' => $body->requestId,
        ], $encoded);
    }

    public function stamp(Response $response, string $requestId): Response
    {
        return $response->withHeader('X-Request-Id', $requestId);
    }
}
