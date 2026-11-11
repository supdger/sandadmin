<?php

declare(strict_types=1);

namespace app\bootstrap;

use RuntimeException;
use Webman\Bootstrap;

/**
 * 安全 P0-F4（宿主层）：APP_ENV=production|prod 时 app.debug 必须为 false，否则拒绝启动。
 *
 * 由 config/bootstrap.php 最先注册；只在启动期判定，worker 内不改 debug，不持有请求状态。
 */
final class ProductionDebugGuard implements Bootstrap
{
    private const PRODUCTION = ['production', 'prod'];

    public static function isProduction(mixed $appEnv): bool
    {
        return is_string($appEnv) && in_array(strtolower(trim($appEnv)), self::PRODUCTION, true);
    }

    public static function isDebugOn(mixed $debug): bool
    {
        if (is_string($debug)) {
            return in_array(strtolower(trim($debug)), ['1', 'true', 'on', 'yes'], true);
        }
        return (bool) $debug;
    }

    /** @throws RuntimeException */
    public static function assertSafe(mixed $appEnv, mixed $debug): void
    {
        if (self::isProduction($appEnv) && self::isDebugOn($debug)) {
            throw new RuntimeException('生产环境（APP_ENV=production）禁止开启 app.debug，请设置 APP_DEBUG=false 后再启动');
        }
    }

    /**
     * @param mixed $worker
     * @param null|callable(object): void $halt 测试注入；默认让主进程停止，避免 worker 重启风暴
     */
    public static function start($worker, ?callable $halt = null): void
    {
        $env = getenv('APP_ENV');
        try {
            self::assertSafe($env === false ? ($_ENV['APP_ENV'] ?? null) : $env, config('app.debug', false));
        } catch (RuntimeException $error) {
            fwrite(STDERR, '[sandadmin] ' . $error->getMessage() . PHP_EOL);
            if ($worker !== null) {
                ($halt ?? self::haltMaster(...))($worker);
            }
            throw $error;
        }
    }

    public static function haltMaster(object $worker): void
    {
        if (DIRECTORY_SEPARATOR !== '/' || !function_exists('posix_kill') || !function_exists('posix_getppid') || !defined('SIGINT')) {
            return;
        }
        set_error_handler(static fn (): bool => true);
        try {
            posix_kill(posix_getppid(), SIGINT);
        } finally {
            restore_error_handler();
        }
    }
}
