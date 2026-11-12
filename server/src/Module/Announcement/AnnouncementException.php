<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/**
 * 公告模块业务异常：携带契约错误码、HTTP 状态与字段级错误，
 * 由 AnnouncementHttp 捕获后映射为统一错误信封。
 */
final class AnnouncementException extends \RuntimeException
{
    private const CODE_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    /**
     * @param array<string, list<string>> $fields
     */
    private function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields,
        ?\Throwable $previous = null,
    ) {
        if (preg_match(self::CODE_PATTERN, $errorCode) !== 1) {
            throw new \InvalidArgumentException('error code 必须是大写蛇形枚举');
        }
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, list<string>> $fields */
    public static function invalid(array $fields, string $message = '请求内容有误，请按提示修改'): self
    {
        return new self(400, 'VALIDATION_FAILED', $message, $fields);
    }

    public static function notFound(): self
    {
        return new self(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或对你不可见', []);
    }

    public static function permissionDenied(): self
    {
        return new self(403, 'PERMISSION_DENIED', '你没有权限做这个操作', []);
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'UNAUTHENTICATED', '请先登录', []);
    }

    public static function idempotencyConflict(): self
    {
        return new self(409, 'IDEMPOTENCY_KEY_REUSED', '相同请求标识被用于不同内容，请刷新后重试', []);
    }
}
