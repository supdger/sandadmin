<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Lifecycle\LifecycleHttp;
use Sandadmin\Module\Lifecycle\LifecycleRuntime;
use support\Request;
use support\Response;
use Throwable;

/**
 * GET /api/admin/v1/lifecycle-requests/{request_id}。
 * 可见性（申请人本人或 system:plugin:index/system:update:index）
 * 在 LifecycleHttp 内判定；契约不提供取消入口。
 */
final class LifecycleController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function show(Request $request, string $request_id): Response
    {
        $envelopeId = $this->envelopeId($request);
        try {
            $runtime = LifecycleRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', $envelopeId);
            }
            $outcome = LifecycleHttp::show(
                $runtime->requests,
                $runtime->actors->actor($request),
                $request_id,
                $envelopeId,
            );

            return $this->respond($outcome['status'], $outcome['body'], $envelopeId);
        } catch (Throwable $error) {
            error_log('LifecycleController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', $envelopeId);
        }
    }

    private function envelopeId(Request $request): string
    {
        $incoming = $request->header('x-request-id');
        if (is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return 'req_' . bin2hex(random_bytes(8));
    }

    /** @param array<string, mixed> $body */
    private function respond(int $status, array $body, string $envelopeId): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $envelopeId],
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    private function failure(int $status, string $code, string $message, string $envelopeId): Response
    {
        $outcome = LifecycleHttp::failure($status, $code, $message, $envelopeId);

        return $this->respond($outcome['status'], $outcome['body'], $envelopeId);
    }
}
