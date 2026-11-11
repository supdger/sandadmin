<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 网页安装开关。生产环境一律视为编译版，不允许在线安装或升级。
 * 未设置 SAND_COMPILED 时也按编译版处理，避免漏配就放开安装。
 */
final class PackageGate
{
    public const COMPILED_MESSAGE = '这是编译版，不能在网页上安装或升级插件。请让负责部署的人在开发环境装好后重新打包上传。';

    public const UPDATE_COMPILED_MESSAGE = '这是编译版，不能在网页上升级。请让负责部署的人在开发环境升级后重新打包上传。';

    public const COMPILED_PLUGIN_MESSAGE = '不能在网页上安装编译过的插件。请让负责部署的人在开发环境装好后重新打包上传。';

    public static function production(): bool
    {
        $env = getenv('APP_ENV');

        return is_string($env) && $env === 'production';
    }

    public static function compiled(): bool
    {
        if (self::production()) {
            return true;
        }
        $flag = getenv('SAND_COMPILED');
        if (!is_string($flag) || $flag === '') {
            return true;
        }

        return $flag !== '0';
    }

    public static function opsEnabled(): bool
    {
        if (self::compiled()) {
            return false;
        }

        return getenv('SAND_PACKAGE_OPS') === '1';
    }
}
