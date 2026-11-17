<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\bootstrap;

use Sandadmin\Infra\Auth\WeakPasswordException;
use Sandadmin\Infra\Auth\WeakPasswordList;
use Sandadmin\Infra\Auth\WeakPasswordRuntime;
use support\Log;
use Webman\Bootstrap;

/**
 * worker 启动时载入常用弱密码命中表并做自检：
 * 生产环境且 forbid_weak 开启时词表不可用即拒绝启动；
 * 其余情况打 warning 后放行（检查降级为空表，不拦请求）。
 * 只在启动期执行，不持有请求状态。
 */
final class WeakPasswordGuard implements Bootstrap
{
    public static function start(mixed $worker): void
    {
        $path = config('security.weak_password_path');
        if (!is_string($path) || $path === '') {
            $path = base_path() . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'weak-passwords.txt';
        }
        try {
            $list = WeakPasswordList::fromFile($path);
        } catch (WeakPasswordException $error) {
            if (self::mustReject(self::appEnv(), self::forbidWeak())) {
                fwrite(STDERR, '[sandadmin] ' . $error->getMessage() . PHP_EOL);
                if (is_object($worker)) {
                    ProductionDebugGuard::haltMaster($worker);
                }
                throw $error;
            }
            Log::channel('default')->warning($error->getMessage() . '，弱密码检查本次放行');
            return;
        }
        WeakPasswordRuntime::mount($list);
    }

    /** 词表缺失是否必须拒启：生产环境且弱密码检查开启时。 */
    public static function mustReject(mixed $appEnv, bool $forbidWeak): bool
    {
        return ProductionDebugGuard::isProduction($appEnv) && $forbidWeak;
    }

    public static function forbidWeak(): bool
    {
        return (bool) config('security.forbid_weak', true);
    }

    private static function appEnv(): mixed
    {
        $env = getenv('APP_ENV');

        return $env === false ? ($_ENV['APP_ENV'] ?? null) : $env;
    }
}
