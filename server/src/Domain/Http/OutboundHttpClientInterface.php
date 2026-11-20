<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Http;

/**
 * 出站 HTTP 调用端口。统一承载超时、重试、SSRF 与追踪头注入，
 * 业务代码不直接接触 curl/Guzzle 等传输原语。
 */
interface OutboundHttpClientInterface
{
    /**
     * 发起一次出站请求。传输失败、SSRF 拦截与超时抛 OutboundException；
     * 非 2xx 状态照常返回 OutboundResponse，由调用方判定。
     */
    public function request(OutboundRequest $request): OutboundResponse;
}
