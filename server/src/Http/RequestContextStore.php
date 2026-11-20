<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Infra\Log\RequestScope;
use Webman\Http\Request;

/**
 * 把 RequestContext 放在 Workerman Request::$context 上。
 * 该数组属于当前请求对象，Request::destroy() 会清空；这里不使用静态属性。
 * ensure() 是 request_id 的统一入口：合法透传 x-request-id 头、否则生成，
 * 并同步绑定 RequestScope 使本请求期间的日志自动带 request_id。
 */
final class RequestContextStore
{
    public const ATTRIBUTE = 'sandadmin.request_context';

    private readonly RequestIds $requestIds;

    private readonly RequestHeaders $headers;

    private readonly RequestScope $scope;

    public function __construct(?RequestIds $requestIds = null, ?RequestHeaders $headers = null, ?RequestScope $scope = null)
    {
        $this->requestIds = $requestIds ?? new RequestIds();
        $this->headers = $headers ?? new RequestHeaders();
        $this->scope = $scope ?? new RequestScope();
    }

    public function set(Request $request, RequestContext $context): void
    {
        $request->context[self::ATTRIBUTE] = $context;
    }

    public function get(Request $request): ?RequestContext
    {
        $value = $request->context[self::ATTRIBUTE] ?? null;

        return $value instanceof RequestContext ? $value : null;
    }

    /**
     * 确保请求已有 RequestContext 并返回；已存在则直接复用。
     * 新建时按 x-request-id 头解析（非法值重新生成），写入匿名上下文，
     * 同时把 request_id 绑进请求作用域供日志 processor 注入。
     */
    public function ensure(Request $request): RequestContext
    {
        $existing = $this->get($request);
        if ($existing !== null) {
            return $existing;
        }
        $requestId = $this->requestIds->resolve($this->headers->optional($request, 'x-request-id'));
        $context = new RequestContext(null, $requestId);
        $this->set($request, $context);
        $this->scope->bind($requestId);

        return $context;
    }
}
