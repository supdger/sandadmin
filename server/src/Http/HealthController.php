<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Closure;
use support\Request;
use support\Response;

/**
 * 常驻进程存活探针（3i HR5-a / M-12）：进程活着就回 200 + 简单 JSON，
 * 不查库、不查 Redis、不鉴权——负载均衡/看门狗只用它判断「能不能接请求」。
 * 就绪语义（DB/迁移版本）不在这里，属 3I-5b 的 /health/ready。
 * 展示文案走 zh_CN 语言包（resource/translations/zh_CN/health.php），
 * 语言包缺失时 trans 回落到键名本身，不会吐中文硬编码。
 */
final class HealthController
{
    private readonly ?Closure $message;

    /** @param callable(): string $message 测试注入；空走 zh_CN 语言包 */
    public function __construct(?callable $message = null)
    {
        $this->message = $message === null ? null : Closure::fromCallable($message);
    }

    public function live(Request $request): Response
    {
        $message = $this->message?->__invoke() ?? trans('live', [], 'health');

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                ['status' => 'ok', 'message' => $message !== '' ? $message : 'live'],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        );
    }
}
