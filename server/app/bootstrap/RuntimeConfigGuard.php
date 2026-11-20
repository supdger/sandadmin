<?php

declare(strict_types=1);

namespace app\bootstrap;

use InvalidArgumentException;
use Sandadmin\Domain\Stream\StreamTicker;
use Sandadmin\Infra\Runtime\ProcessRecycle;
use Sandadmin\Infra\Stream\WorkermanTicker;
use Sandadmin\Infra\Runtime\StartupConfigValidator;
use Webman\Bootstrap;

/**
 * 常驻进程配置启动自检与回收装配（3i §5 / M-12/M-13）：
 * 1. StartupConfigValidator 过一遍 runtime/stream/server/task/process
 *    的关键键，非法即拒绝启动，错误写 STDERR 可定位到 config/文件.键；
 * 2. 为每个常驻 worker 挂内存水位定时器——真实内存占用到
 *    runtime.memory_watermark_bytes（缺省=进程内存上限 80%，无上限
 *    按 256MiB 兜底）即向本进程发 SIGUSR1 触发 workerman 平滑重启。
 * 请求数上限回收由 RuntimeGuardMiddleware 按请求计数承担，
 * 这里只管不经过 HTTP 中间件的 worker（task 等）的水位检查。
 */
final class RuntimeConfigGuard implements Bootstrap
{
    /** 内存水位检查周期（秒）。 */
    private const MEMORY_CHECK_SECONDS = 10.0;

    /**
     * @param array<mixed>|null $config 测试注入；空取五组运行配置
     * @param StreamTicker|null $ticker 测试注入；空走 Workerman\Timer（仅 worker 内可用）
     */
    public static function start(mixed $worker, ?array $config = null, ?StreamTicker $ticker = null): void
    {
        try {
            StartupConfigValidator::assertValid($config ?? self::runtimeConfig());
        } catch (InvalidArgumentException $error) {
            fwrite(STDERR, '[sandadmin] 启动配置校验失败: ' . $error->getMessage() . PHP_EOL);
            if (is_object($worker)) {
                ProductionDebugGuard::haltMaster($worker);
            }
            throw $error;
        }

        $recycle = ProcessRecycle::fromConfig();
        ($ticker ?? new WorkermanTicker())->every(self::MEMORY_CHECK_SECONDS, static function () use ($recycle): void {
            $recycle->checkMemory();
        });
    }

    /** @return array<mixed> */
    private static function runtimeConfig(): array
    {
        return [
            'runtime' => config('runtime', []),
            'stream' => config('stream', []),
            'server' => config('server', []),
            'task' => config('task', []),
            'process' => config('process', []),
        ];
    }
}
