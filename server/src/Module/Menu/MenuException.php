<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Menu;

/** 菜单管理业务异常。错误码只用契约里已登记的码。失败不得伪装成 200。 */
final class MenuException extends \RuntimeException
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

    public static function notFound(): self
    {
        return new self(404, 'MENU_NOT_FOUND', '要操作的菜单不存在或已被删除', []);
    }

    public static function locked(string $message): self
    {
        return new self(409, 'MENU_LOCKED', $message, []);
    }

    public static function badUrl(): self
    {
        return new self(422, 'MENU_URL_INVALID', '链接地址不正确，只能是 http 或 https 开头', ['url' => ['链接地址不正确，只能是 http 或 https 开头']]);
    }

    public static function tooDeep(): self
    {
        return new self(422, 'MENU_DEPTH_EXCEEDED', '菜单最多两级，不能再往里放', ['parent_key' => ['菜单最多两级，不能再往里放']]);
    }

    public static function stale(): self
    {
        return new self(409, 'STALE_VERSION', '这条刚被别人改过，刷新后再改', []);
    }
}
