<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Dict;

/** 选项管理业务异常。错误码只用已登记的码。 */
final class DictException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $fields
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }

    /** @param array<string, list<string>> $fields */
    public static function invalid(array $fields, string $message = '请求内容有误，请按提示修改'): self
    {
        return new self(422, 'VALIDATION_FAILED', $message, $fields);
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'UNAUTHENTICATED', '请先登录', []);
    }

    public static function denied(): self
    {
        return new self(403, 'PERMISSION_DENIED', '你没有权限做这个操作', []);
    }

    public static function typeNotFound(): self
    {
        return new self(404, 'NOT_FOUND', '要找的内容不存在或已被删除', []);
    }

    public static function itemNotFound(): self
    {
        return new self(404, 'NOT_FOUND', '要找的内容不存在或已被删除', []);
    }

    public static function typeTaken(): self
    {
        return new self(409, 'DICT_TYPE_TAKEN', '这个分组名称已经存在了', ['name' => ['这个分组名称已经存在了']]);
    }

    public static function itemTaken(): self
    {
        return new self(409, 'DICT_ITEM_TAKEN', '这个选项已经存在了', ['label' => ['这个选项已经存在了']]);
    }
}
