<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Notification;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Db\DbalConnection;
use Sandadmin\Module\Announcement\AnnouncementActorProvider;
// SAND-DEV-BEGIN
use Sandadmin\Module\Announcement\DevAnnouncementActorProvider;
// SAND-DEV-END
use Sandadmin\Module\Announcement\UnavailableAnnouncementActorProvider;

/** 消息中心装配。操作人与公告共用开发登录解析。 */
final readonly class NotificationRuntime
{
    public function __construct(
        public NotificationRepository $inbox,
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

            return new self(
                new NotificationRepository($connection),
                self::actorProvider($connection),
            );
        } catch (\Throwable $exception) {
            error_log('消息中心初始化失败 ' . $exception::class . ': ' . $exception->getMessage());

            return null;
        }
    }

    // 哨兵块里的身份提供方只存在于开发构建，发布打包时整段剔除。
    private static function actorProvider(Connection $connection): AnnouncementActorProvider
    {
        // SAND-DEV-BEGIN
        if (class_exists(DevAnnouncementActorProvider::class)) {
            return new DevAnnouncementActorProvider($connection);
        }
        // SAND-DEV-END

        return new UnavailableAnnouncementActorProvider();
    }
}
