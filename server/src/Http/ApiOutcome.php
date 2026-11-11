<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

/**
 * 1.x 契约信封：成功 {data, request_id}，失败 {code, message, fields, request_id}。
 */
final class ApiOutcome
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function ok(mixed $data, string $requestId): array
    {
        return ['status' => 200, 'body' => ['data' => $data, 'request_id' => $requestId]];
    }

    /**
     * @param array<string, list<string>> $fields
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function fail(int $status, string $code, string $message, array $fields, string $requestId): array
    {
        return [
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields === [] ? new \stdClass() : $fields,
                'request_id' => $requestId,
            ],
        ];
    }
}
