<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Sandadmin\Infra\Db\DbalConnection;
use Sandadmin\Infra\Idempotency\PdoIdempotencyStore;

/** 公告模块 Webman 装配：连接、仓储、服务、幂等存储一次构建。 */
final readonly class AnnouncementRuntime
{
    public function __construct(
        public AnnouncementService $service,
        public AnnouncementActorProvider $actors,
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
            $connection = DbalConnection::connection($database);
            $repository = new AnnouncementRepository($connection);
            $idempotency = null;
            try {
                $pdo = $connection->getNativeConnection();
                if ($pdo instanceof \PDO) {
                    $idempotency = new PdoIdempotencyStore($pdo);
                }
            } catch (\Throwable) {
                // 幂等表不可用时退化为不记录，接口照常工作
            }

            return new self(
                new AnnouncementService($repository, new AnnouncementAuditWriter($connection), $idempotency),
                new DevAnnouncementActorProvider($connection),
            );
        } catch (\Throwable $exception) {
            error_log('公告运行时初始化失败 ' . $exception::class . ': ' . $exception->getMessage());

            return null;
        }
    }
}
