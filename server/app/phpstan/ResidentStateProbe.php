<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\phpstan;

/**
 * PHPStan 常驻态规则的判红探针：故意包含可变静态属性与 $GLOBALS 写入。
 * 本目录不在 phpstan.neon.dist 的 paths 里，正常 `phpstan analyse` 不扫它；
 * 单独 `vendor/bin/phpstan analyse app/phpstan` 必须判红，证明规则生效。
 */
final class ResidentStateProbe
{
    /** @var list<string> */
    public static array $leaked = [];

    public static function pollute(): void
    {
        self::$leaked[] = 'x';
        $GLOBALS['sandadmin_probe'] = true;
    }
}
