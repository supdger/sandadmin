<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Id;

/**
 * 发号器的进程级持有点：webman 常驻进程启动时挂载一次，
 * 之后全进程共享同一实例——同毫秒序列只在单实例内连续，
 * 各处自建实例会在同一毫秒从 0 重排而发出重复号。
 */
final class SnowflakeRuntime
{
    private static ?SnowflakeGenerator $generator = null;

    private function __construct()
    {
    }

    /** 由启动引导挂载；重复挂载以最后一次为准。 */
    public static function mount(SnowflakeGenerator $generator): void
    {
        self::$generator = $generator;
    }

    /** 未挂载即拒用：走到抛错说明启动自检缺失或已拒启，不允许无配置发号。 */
    public static function generator(): SnowflakeGenerator
    {
        if (self::$generator === null) {
            throw new SnowflakeConfigException('发号器未挂载，启动自检缺失或已拒启');
        }

        return self::$generator;
    }

    public static function next(): int
    {
        return self::generator()->next();
    }

    /** 测试复位；运行期只在 worker 启动时挂载一次，不主动卸载。 */
    public static function reset(): void
    {
        self::$generator = null;
    }
}
