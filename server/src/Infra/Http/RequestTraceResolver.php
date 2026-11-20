<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Http;

use Sandadmin\Domain\Http\TraceContext;
use Sandadmin\Domain\Http\TraceContextResolver;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;
use Webman\Http\Request;

/**
 * 默认 TraceContextResolver：从当前 webman 请求读入站 traceparent/tracestate，
 * request id 复用 RequestContextStore 已解析结果（无上下文时按 x-request-id
 * 头走 RequestIds 同一规则）。Task/CLI 无请求上下文时 resolve() 返回 null、
 * requestId() 现生成，调用方自行 generate 新 trace。
 */
final class RequestTraceResolver implements TraceContextResolver
{
    /**
     * @param null|\Closure(): mixed $source 当前请求来源，测试注入；缺省读 webman request()
     */
    public function __construct(
        private readonly ?RequestContextStore $contexts = null,
        private readonly ?RequestIds $ids = null,
        private readonly ?RequestHeaders $headers = null,
        private readonly ?\Closure $source = null,
    ) {
    }

    public function resolve(): ?TraceContext
    {
        $request = $this->currentRequest();
        if (!$request instanceof Request) {
            return null;
        }
        $traceparent = $this->headerReader()->optional($request, 'traceparent');
        if ($traceparent === null) {
            return null;
        }
        $tracestate = $this->headerReader()->optional($request, 'tracestate');

        return TraceContext::continueFrom($traceparent, $tracestate, $this->resolveRequestId($request));
    }

    public function requestId(): string
    {
        $request = $this->currentRequest();
        if ($request instanceof Request) {
            return $this->resolveRequestId($request);
        }

        return $this->idGenerator()->resolve(null);
    }

    private function currentRequest(): mixed
    {
        if ($this->source !== null) {
            return ($this->source)();
        }

        return function_exists('request') ? request() : null;
    }

    private function resolveRequestId(Request $request): string
    {
        $context = $this->contextStore()->get($request);
        if ($context !== null) {
            return $context->requestId;
        }

        return $this->idGenerator()->resolve($this->headerReader()->optional($request, 'x-request-id'));
    }

    private function contextStore(): RequestContextStore
    {
        return $this->contexts ?? new RequestContextStore();
    }

    private function idGenerator(): RequestIds
    {
        return $this->ids ?? new RequestIds();
    }

    private function headerReader(): RequestHeaders
    {
        return $this->headers ?? new RequestHeaders();
    }
}
