<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Notification;

/** 消息中心业务异常，映射成契约错误信封。 */
final class NotificationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $fields
     */
    private function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields,
    ) {
        parent::__construct($message);
    }

    /** @param array<string, list<string>> $fields */
    public static function invalid(array $fields, string $message = '请求内容有误，请按提示修改'): self
    {
        return new self(400, 'VALIDATION_FAILED', $message, $fields);
    }

    public static function notFound(): self
    {
        return new self(404, 'NOT_FOUND', '这条消息不存在', []);
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'UNAUTHENTICATED', '请先登录', []);
    }
}
