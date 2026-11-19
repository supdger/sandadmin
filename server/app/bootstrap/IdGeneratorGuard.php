<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\bootstrap;

use Sandadmin\Infra\Id\SnowflakeConfig;
use Sandadmin\Infra\Id\SnowflakeConfigException;
use Sandadmin\Infra\Id\SnowflakeGenerator;
use Sandadmin\Infra\Id\SnowflakeRuntime;
use Sandadmin\Task\SystemClock;
use Webman\Bootstrap;

/**
 * 发号器启动自检（v4 J-3）：idgen.node_id 必须落在 node_range 部署号段内、
 * max_clock_backwards_ms 必填正整数，任一不满足拒绝启动——
 * 防止拿错配置的进程发出与其他部署重复的号。
 * 自检通过后为本进程挂载共享发号器实例；只在启动期执行，不持有请求状态。
 */
final class IdGeneratorGuard implements Bootstrap
{
    /**
     * @param array<mixed>|null $idgenConfig 测试注入；空取 config('idgen')
     * @param callable(string): void|null $alarm 测试注入告警通道；默认走 default 日志通道
     */
    public static function start(mixed $worker, ?array $idgenConfig = null, ?callable $alarm = null): void
    {
        try {
            $config = SnowflakeConfig::fromArray($idgenConfig ?? self::idgenConfig());
        } catch (SnowflakeConfigException $error) {
            fwrite(STDERR, '[sandadmin] ' . $error->getMessage() . PHP_EOL);
            if (is_object($worker)) {
                ProductionDebugGuard::haltMaster($worker);
            }
            throw $error;
        }
        SnowflakeRuntime::mount(new SnowflakeGenerator(
            $config->nodeId,
            new SystemClock(),
            $config->maxBackwardsMs,
            null,
            $alarm,
        ));
    }

    /** @return array<mixed> */
    private static function idgenConfig(): array
    {
        $config = config('idgen', []);

        return is_array($config) ? $config : [];
    }
}
