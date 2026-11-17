<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra;

/**
 * 开发构建标记。启动引导按 .sand-dev-build 标记文件定义常量；
 * 生产制品打包剔除标记后常量不成立，dev 链（免登录等）整体不挂。
 */
final class DevBuild
{
    private function __construct()
    {
    }

    public static function enabled(): bool
    {
        return defined('SAND_DEV_BUILD') && SAND_DEV_BUILD === true;
    }
}
