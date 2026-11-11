<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Http\JsonErrorResponse;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 捕获下游异常并输出 {code,message,fields,request_id}。
 * 成功响应补上 X-Request-Id。request id 写在当前请求对象上。
 *
 * Webman 在每一层中间件外包了 try/catch，控制器异常会进 config/exception.php，
 * 到不了外层中间件。现有站点仍使用 {code,msg} 处理器，这里不替换它。
 */
final class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ErrorResponder $responder,
        private readonly JsonErrorResponse $json,
        private readonly RequestContextStore $contexts,
        private readonly RequestIds $requestIds,
        private readonly RequestHeaders $headers,
    ) {
    }

    public function process(Request $request, callable $handler): Response
    {
        $fallbackRequestId = $this->ensureRequestId($request);
        try {
            $response = $handler($request);
        } catch (\Throwable $error) {
            $response = $this->json->fromBody(
                $this->responder->respond($error, $this->currentRequestId($request, $fallbackRequestId)),
            );
        }
        if (!$response instanceof Response) {
            throw new \RuntimeException('错误中间件下游必须返回 Response');
        }

        return $this->json->stamp($response, $this->currentRequestId($request, $fallbackRequestId));
    }

    private function ensureRequestId(Request $request): string
    {
        $existing = $this->contexts->get($request);
        if ($existing !== null) {
            return $existing->requestId;
        }
        $requestId = $this->requestIds->resolve($this->headers->optional($request, 'x-request-id'));
        $this->contexts->set($request, new RequestContext(null, $requestId));

        return $requestId;
    }

    private function currentRequestId(Request $request, string $fallback): string
    {
        $context = $this->contexts->get($request);

        return $context !== null ? $context->requestId : $fallback;
    }
}
