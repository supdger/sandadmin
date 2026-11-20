<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;

/**
 * 1.x 契约信封：成功 {code:"OK", message:"操作成功", data, request_id}，
 * 失败 {code, message, fields, request_id}（api-conventions §2.1/§2.2）。
 * 成功信封只从这里出形状，模块侧 helper 一律委托本方法。
 */
final class ApiOutcome
{
    private const OK_MESSAGE = '操作成功';

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function ok(mixed $data, string $requestId): array
    {
        return [
            'status' => 200,
            'body' => [
                'code' => ErrorCodeRegistry::OK,
                'message' => self::OK_MESSAGE,
                'data' => $data,
                'request_id' => $requestId,
            ],
        ];
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
