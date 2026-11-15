<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\process;

use Sandadmin\Infra\Db\DbalConnection;
use Sandadmin\Module\Monitor\MetricSampler as Sampler;

/**
 * 运行状态周期采样：每 60 秒往 sand_system_metric_sample 落一条，
 * /monitor/series 的折线数据源。页面打开时 /monitor/server 也会
 * 实时采一条（ON CONFLICT DO NOTHING，同一秒不重复）。
 */
final class MetricSampler
{
    private const INTERVAL_SECONDS = 60;

    private ?Sampler $sampler = null;

    public function onWorkerStart(object $worker): void
    {
        if (!class_exists(\Workerman\Timer::class)) {
            return;
        }
        \Workerman\Timer::add(self::INTERVAL_SECONDS, function (): void {
            $this->tick();
        });
    }

    private function tick(): void
    {
        try {
            $sampler = $this->sampler();
            if ($sampler === null) {
                return;
            }
            $sampler->persist($sampler->collect(), new \DateTimeImmutable());
        } catch (\Throwable $error) {
            $this->sampler = null;
            error_log('metric_sampler tick 异常 ' . $error::class . ': ' . $error->getMessage());
        }
    }

    private function sampler(): ?Sampler
    {
        if ($this->sampler !== null) {
            return $this->sampler;
        }
        if (!function_exists('config')) {
            return null;
        }
        $database = config('database.connections.pgsql');
        if (!is_array($database)) {
            return null;
        }
        try {
            $this->sampler = new Sampler(DbalConnection::connection($database));

            return $this->sampler;
        } catch (\Throwable $error) {
            error_log('metric_sampler 连接失败 ' . $error::class . ': ' . $error->getMessage());

            return null;
        }
    }
}
