<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Http;

use Sandadmin\Job\JobHttp;
use Sandadmin\Job\JobRuntime;
use support\Request;
use support\Response;

final class JobController
{
    public function show(Request $request, string $id): Response
    {
        $requestId = $this->requestId($request);
        $runtime = JobRuntime::fromWebman();
        if ($runtime === null) {
            $body = [
                'code' => 'job_store_unavailable',
                'message' => '任务存储尚未就绪',
                'fields' => new \stdClass(),
                'request_id' => $requestId,
            ];

            return $this->respond(503, $body, $requestId);
        }
        $outcome = JobHttp::show($runtime->store, $id, $requestId);

        return $this->respond($outcome['status'], $outcome['body'], $requestId);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function respond(int $status, array $body, string $requestId): Response
    {
        return new Response(
            $status,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Request-Id' => $requestId,
            ],
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->header('x-request-id', '');
        if (!is_string($incoming)) {
            $incoming = '';
        }
        if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1) {
            return $incoming;
        }

        return bin2hex(random_bytes(16));
    }
}
