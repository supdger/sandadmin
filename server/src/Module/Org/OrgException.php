<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * 组织模块（用户、部门、角色、岗位）业务异常。
 * 错误码只用 api-conventions 已登记的码。
 */
final class OrgException extends \RuntimeException
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
    ) {
        if (preg_match(self::CODE_PATTERN, $errorCode) !== 1) {
            throw new \InvalidArgumentException('error code 必须是大写蛇形枚举');
        }
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

    public static function userNotFound(): self
    {
        return new self(404, 'USER_NOT_FOUND', '用户不存在', []);
    }

    public static function deptNotFound(): self
    {
        return new self(404, 'DEPT_NOT_FOUND', '部门不存在', []);
    }

    public static function postNotFound(): self
    {
        return new self(404, 'POST_NOT_FOUND', '岗位不存在', []);
    }

    public static function roleNotFound(): self
    {
        return new self(404, 'NOT_FOUND', '要找的内容不存在或已被删除', []);
    }

    public static function usernameTaken(): self
    {
        return new self(409, 'USERNAME_TAKEN', '这个账号已经被使用了', ['username' => ['这个账号已经被使用了']]);
    }

    public static function phoneTaken(): self
    {
        return new self(422, 'VALIDATION_FAILED', '这个手机号已经有人用了', ['phone' => ['这个手机号已经有人用了']]);
    }

    public static function deptNameTaken(): self
    {
        return new self(409, 'DEPT_NAME_TAKEN', '同一上级下已经有同名部门了', ['name' => ['同一上级下已经有同名部门了']]);
    }

    public static function deptNotEmpty(string $message): self
    {
        return new self(409, 'DEPT_NOT_EMPTY', $message, []);
    }

    public static function postNameTaken(): self
    {
        return new self(409, 'POST_NAME_TAKEN', '这个岗位名称已经有人用了', ['name' => ['这个岗位名称已经有人用了']]);
    }

    public static function roleNameTaken(): self
    {
        return new self(409, 'ROLE_NAME_TAKEN', '这个角色名称已经有人用了，请换一个', ['name' => ['这个角色名称已经有人用了，请换一个']]);
    }

    public static function systemRole(): self
    {
        return new self(403, 'SYSTEM_ROLE_READONLY', '系统角色不能修改', []);
    }

    public static function lastAdmin(): self
    {
        return new self(409, 'LAST_ADMIN', '系统里至少要留一个能用的超级管理员，请先把另一个人设为超级管理员', []);
    }

    public static function disableBlocked(string $message): self
    {
        return new self(409, 'USER_DISABLE_BLOCKED', $message, []);
    }

    public static function stale(): self
    {
        return new self(409, 'STALE_VERSION', '这条刚被别人改过，刷新后再改', []);
    }

    public static function selfLockout(): self
    {
        return new self(403, 'SELF_LOCKOUT', '不能移除你自己正在使用的权限', []);
    }

    public static function deptRequired(): self
    {
        return new self(422, 'DEPT_REQUIRED', '请选择这个角色能查看的部门', ['dept_ids' => ['请选择这个角色能查看的部门']]);
    }

    public static function scopeInvalid(): self
    {
        return new self(422, 'DATA_SCOPE_INVALID', '数据范围选择不正确', ['data_scope' => ['数据范围选择不正确']]);
    }

    public static function idempotency(): self
    {
        return new self(409, 'IDEMPOTENCY_KEY_REUSED', '相同请求标识被用于不同内容，请刷新后重试', []);
    }
}
