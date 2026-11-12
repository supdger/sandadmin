<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

/**
 * GET /api/admin/v1/lifecycle-requests/{request_id} 装配。
 * request_id 为 public_id（uuid）；不存在或无权查看一律 404。
 */
final class LifecycleHttp
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    /** @return array{status: int, body: array<string, mixed>} */
    public static function show(LifecycleRepository $requests, ?LifecycleActor $actor, string $requestId, string $envelopeId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', $envelopeId);
        }
        if (preg_match(self::UUID_PATTERN, $requestId) !== 1) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $envelopeId, ['request_id' => ['申请编号不正确']]);
        }
        $item = $requests->findByPublicId(strtolower($requestId));
        if ($item === null || !$item->visibleTo($actor)) {
            return self::failure(404, 'LIFECYCLE_REQUEST_NOT_FOUND', '申请不存在或无权查看', $envelopeId);
        }

        return [
            'status' => 200,
            'body' => ['data' => $item->toApi(), 'request_id' => $envelopeId],
        ];
    }

    /**
     * @param array<string, list<string>> $fields
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function failure(int $status, string $code, string $message, string $envelopeId, array $fields = []): array
    {
        return [
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => $envelopeId,
            ],
        ];
    }
}
