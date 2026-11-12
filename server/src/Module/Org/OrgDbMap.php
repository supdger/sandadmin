<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/** 把数据库唯一约束和检查约束翻成契约错误码。认不出的异常原样抛出。 */
final class OrgDbMap
{
    public static function map(\Throwable $error): never
    {
        if ($error instanceof OrgException) {
            throw $error;
        }
        $text = $error->getMessage();
        if (str_contains($text, 'uk_sand_system_user_phone')) {
            throw OrgException::phoneTaken();
        }
        if (str_contains($text, 'uk_sand_system_user_username')) {
            throw OrgException::usernameTaken();
        }
        if (str_contains($text, 'uk_sand_system_dept_parent_name')) {
            throw OrgException::deptNameTaken();
        }
        if (str_contains($text, 'uk_sand_system_post_name')) {
            throw OrgException::postNameTaken();
        }
        if (str_contains($text, 'uk_sand_system_role_name')) {
            throw OrgException::roleNameTaken();
        }
        if (str_contains($text, 'ck_sand_system_user_phone_format')) {
            throw OrgException::invalid(['phone' => ['手机号要 11 位数字，请检查后再填']]);
        }
        if (str_contains($text, 'ck_sand_system_user_username')) {
            throw OrgException::invalid(['username' => ['登录账号只能用字母、数字和下划线，4 到 20 位']]);
        }
        if (str_contains($text, 'ck_sand_system_user_email')) {
            throw OrgException::invalid(['email' => ['邮箱格式不对，例如 name@company.com']]);
        }
        throw $error;
    }
}
