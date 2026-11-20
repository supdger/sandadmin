<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\bootstrap;

use Sandadmin\Infra\Id\SnowflakeConfig;
use Sandadmin\Infra\Id\SnowflakeConfigException;
use Sandadmin\Infra\Id\SnowflakeGenerator;
use Sandadmin\Infra\Id\SnowflakeNodePlan;
use Sandadmin\Infra\Id\SnowflakeRuntime;
use Sandadmin\Task\SystemClock;
use Webman\Bootstrap;

/**
 * 发号器启动自检（v4 J-3）：idgen.node_id 是本机节点基值，各发号进程按
 * SnowflakeNodePlan 的槽位拿「基值 + 偏移 + worker 序号」；基值加进程总数
 * 减一超出 node_range、必填项缺失或进程不在布局内即拒绝启动——
 * 防止配置错误或进程号段重叠发出与其他部署/进程重复的号。
 * 自检通过后为本进程挂载共享发号器实例；只在启动期执行，不持有请求状态。
 */
final class IdGeneratorGuard implements Bootstrap
{
    /**
     * @param array<mixed>|null $idgenConfig 测试注入；空取 config('idgen')
     * @param callable(string): void|null $alarm 测试注入告警通道；默认走 default 日志通道
     * @param array<mixed>|null $processConfig 测试注入；空取 config('process')
     * @param array<mixed>|null $pluginConfig 测试注入；空取 config('plugin')
     */
    public static function start(mixed $worker, ?array $idgenConfig = null, ?callable $alarm = null, ?array $processConfig = null, ?array $pluginConfig = null): void
    {
        try {
            $plan = SnowflakeNodePlan::fromConfigs(
                $processConfig ?? self::processConfig(),
                $pluginConfig ?? self::pluginConfig(),
            );
            $config = SnowflakeConfig::fromArray($idgenConfig ?? self::idgenConfig(), $plan->workerTotal);
            [$processName, $workerId] = self::workerIdentity($worker);
            $nodeId = $plan->resolveNodeId($config->nodeBase, $processName, $workerId);
        } catch (SnowflakeConfigException $error) {
            fwrite(STDERR, '[sandadmin] ' . $error->getMessage() . PHP_EOL);
            if (is_object($worker)) {
                ProductionDebugGuard::haltMaster($worker);
            }
            throw $error;
        }
        SnowflakeRuntime::mount(new SnowflakeGenerator(
            $nodeId,
            new SystemClock(),
            $config->maxBackwardsMs,
            null,
            $alarm,
        ));
    }

    /**
     * 进程身份：webman 进程对象取公开属性 name/id；测试可传 ['name'=>..,'id'=>..]。
     *
     * @return array{0:string,1:int}
     */
    private static function workerIdentity(mixed $worker): array
    {
        $vars = is_object($worker) ? get_object_vars($worker) : (is_array($worker) ? $worker : []);
        $name = $vars['name'] ?? '';
        $id = $vars['id'] ?? -1;

        return [is_string($name) ? $name : '', is_int($id) ? $id : -1];
    }

    /** @return array<mixed> */
    private static function idgenConfig(): array
    {
        $config = config('idgen', []);

        return is_array($config) ? $config : [];
    }

    /** @return array<mixed> */
    private static function processConfig(): array
    {
        $config = config('process', []);

        return is_array($config) ? $config : [];
    }

    /** @return array<mixed> */
    private static function pluginConfig(): array
    {
        $config = config('plugin', []);

        return is_array($config) ? $config : [];
    }
}
