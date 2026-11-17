<?php

declare(strict_types=1);

namespace app\bootstrap;

use Sandadmin\Infra\Log\LogRetentionException;
use Sandadmin\Infra\Log\LogRetentionPolicy;
use Webman\Bootstrap;

/**
 * 日志留存启动自检（T-4/N9）：任一通道 retention.days 低于所属类别下限
 * 即拒绝启动——安全类下限 183 天、普通类下限 30 天。只在启动期判定，
 * 不持有请求状态。
 */
final class LogRetentionGuard implements Bootstrap
{
    /**
     * @param array<mixed>|null $logConfig 测试注入；空取 config('log')
     */
    public static function start(mixed $worker, ?array $logConfig = null): void
    {
        try {
            LogRetentionPolicy::fromArray($logConfig ?? self::logConfig())->assertValid();
        } catch (LogRetentionException $error) {
            fwrite(STDERR, '[sandadmin] ' . $error->getMessage() . PHP_EOL);
            if (is_object($worker)) {
                ProductionDebugGuard::haltMaster($worker);
            }
            throw $error;
        }
    }

    /** @return array<mixed> */
    private static function logConfig(): array
    {
        $config = config('log', []);

        return is_array($config) ? $config : [];
    }
}
