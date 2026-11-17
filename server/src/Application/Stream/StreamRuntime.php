<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Stream;

use Sandadmin\Domain\Stream\ProcessProfileInterface;
use Sandadmin\Domain\Stream\StreamMetrics;
use Sandadmin\Infra\Stream\MemoryStreamMetrics;
use Sandadmin\Infra\Stream\StreamProfile;
use Sandadmin\Infra\Stream\WorkermanTicker;

/**
 * 控制器取每进程 supervisor 的入口。supervisor 是进程级共享对象：
 * 「每进程挂路数」口径本就按进程计（J-5），里面只放活动会话，
 * 不装请求态数据；请求结束无残留，会话随连接关闭即出账。
 */
final class StreamRuntime
{
    private static ?StreamSupervisor $supervisor = null;

    private static ?StreamMetrics $metrics = null;

    private function __construct()
    {
    }

    public static function supervisor(string $role = 'web'): StreamSupervisor
    {
        if (self::$supervisor === null) {
            self::$metrics = new MemoryStreamMetrics();
            self::$supervisor = new StreamSupervisor(
                self::profile($role),
                new WorkermanTicker(),
                self::$metrics,
            );
        }

        return self::$supervisor;
    }

    public static function metrics(): ?StreamMetrics
    {
        return self::$metrics;
    }

    public static function profile(string $role): ProcessProfileInterface
    {
        $config = function_exists('config') ? config('stream.profiles.' . $role, []) : [];

        return StreamProfile::fromArray($role, is_array($config) ? $config : []);
    }

    /** 进程回收与测试复位用：清掉 supervisor 引用，活动会话随之回收。 */
    public static function reset(): void
    {
        self::$supervisor = null;
        self::$metrics = null;
    }
}
