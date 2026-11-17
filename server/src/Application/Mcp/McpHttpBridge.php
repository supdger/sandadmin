<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Mcp;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Mcp\Server\Authorization\AccessToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sandadmin\Domain\Mcp\McpIdentity;
use support\Request;
use support\Response;

/**
 * webman Request/Response 与 SDK 需要的 PSR-7 之间的转换。
 * 凭据已在入口校验过，这里把解析出的身份按 SDK 约定写进
 * AccessToken 属性（claims.sub = 身份主体，scopes = 凭据 scope）。
 */
final class McpHttpBridge
{
    private const BODY_MAX_BYTES = 4 * 1024 * 1024;

    public static function toPsr7(Request $request, McpIdentity $identity): ServerRequestInterface
    {
        $factory = new HttpFactory();
        $headers = $request->header();
        $normalized = [];
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $normalized[$name] = $value;
                }
            }
        }
        $host = $request->host();
        $target = $request->uri();
        $uri = 'http://' . (is_string($host) && $host !== '' ? $host : 'localhost') . $target;
        $body = $factory->createStream(mb_substr($request->rawBody(), 0, self::BODY_MAX_BYTES));

        return (new ServerRequest($request->method(), $uri, $normalized, $body))
            ->withAttribute(AccessToken::class, new AccessToken($identity->scopes, ['sub' => $identity->subject]));
    }

    public static function fromPsr7(ResponseInterface $response): Response
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        $body = $response->getBody();
        $payload = $body->getSize() === null
            ? $body->read(self::BODY_MAX_BYTES)
            : $body->getContents();

        return new Response($response->getStatusCode(), $headers, $payload);
    }
}
