<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Auth;

/**
 * 弱密码表的进程级持有点：webman 常驻进程启动时挂载一次，
 * 之后每条请求只查内存哈希集合，不重复读文件。
 * 词表是加载后不再变的只读数据，不属于请求态。
 */
final class WeakPasswordRuntime
{
    private static ?WeakPasswordList $list = null;

    private function __construct()
    {
    }

    /** 由启动引导挂载；重复挂载以最后一次为准。 */
    public static function mount(WeakPasswordList $list): void
    {
        self::$list = $list;
    }

    /**
     * 词表未挂载时放行：生产环境是否必须挂载由启动自检负责
     * （缺失即拒启），走到这里说明本进程允许降级。
     */
    public static function isWeak(string $password): bool
    {
        return self::$list !== null && self::$list->isWeak($password);
    }

    public static function ready(): bool
    {
        return self::$list !== null;
    }

    /** 测试复位；运行期只在 worker 启动时挂载一次，不主动卸载。 */
    public static function reset(): void
    {
        self::$list = null;
    }
}
