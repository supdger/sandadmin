<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** 契约错误：status/code/message/fields 与 api-conventions §2.2/§2.4 一致。 */
final class FileException extends \RuntimeException
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
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, list<string>> $fields */
    public static function invalid(array $fields, string $message = '请求内容有误，请按提示修改'): self
    {
        return new self(400, 'VALIDATION_FAILED', $message, $fields);
    }

    public static function notFound(): self
    {
        return new self(404, 'FILE_NOT_FOUND', '文件不存在', []);
    }

    public static function tooLarge(): self
    {
        return new self(413, 'FILE_TOO_LARGE', '文件超过 100MB 上限', []);
    }

    public static function typeNotAllowed(): self
    {
        return new self(422, 'FILE_TYPE_NOT_ALLOWED', '这种文件类型不支持上传', []);
    }

    public static function denied(): self
    {
        return new self(403, 'PERMISSION_DENIED', '你没有权限做这个操作', []);
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'UNAUTHENTICATED', '请先登录', []);
    }

    public static function internal(string $message, ?\Throwable $previous = null): self
    {
        return new self(500, 'INTERNAL_ERROR', $message, [], $previous);
    }

    public static function idempotencyReused(): self
    {
        return new self(409, 'IDEMPOTENCY_KEY_REUSED', '相同请求标识被用于不同内容，请刷新后重试', []);
    }

    /** 契约组装层出口前的兜底：错误码必须保持 §2.4 大写蛇形枚举。 */
    public static function assertCode(string $code): void
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new \InvalidArgumentException('error code 必须是大写蛇形枚举');
        }
    }
}
