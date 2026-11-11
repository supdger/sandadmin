<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

use Sandadmin\Infra\Db\DbalConnection;

/** 生命周期详情 Webman 装配。 */
final readonly class LifecycleRuntime
{
    public function __construct(
        public LifecycleRepository $requests,
        public LifecycleActorProvider $actors,
    ) {
    }

    public static function fromWebman(): ?self
    {
        if (!function_exists('config')) {
            return null;
        }
        $database = config('database.connections.pgsql');
        if (!is_array($database)) {
            return null;
        }
        try {
            return new self(
                new LifecycleRepository(DbalConnection::connection($database)),
                new UnavailableLifecycleActorProvider(),
            );
        } catch (\Throwable $exception) {
            error_log('生命周期运行时初始化失败 ' . $exception::class . ': ' . $exception->getMessage());

            return null;
        }
    }
}
