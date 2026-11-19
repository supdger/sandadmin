<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * 消息中心相关表的内存表夹具：sqlite 最小建表 + 常用种入，
 * 模块 Unit 测试共享，不连外部库。布尔列写整数 1/0。
 */
final class NotificationFixture
{
    public static function createTables(Connection $conn): void
    {
        $conn->executeStatement(
            'CREATE TABLE sand_system_notification (id integer primary key autoincrement,
                user_id integer not null, source_code varchar(64) null, text text not null,
                link varchar(255) null, read_time text null, create_time text null)',
        );
        $conn->executeStatement(
            'CREATE TABLE sand_system_announcement (id integer primary key autoincrement,
                title varchar(128) not null, scope varchar(16) not null default \'all\',
                state varchar(16) not null default \'published\', pinned integer not null default 0,
                published_time text null, expire_time text null, create_time text null)',
        );
        $conn->executeStatement(
            'CREATE TABLE sand_system_announcement_dept (
                announcement_id integer not null, dept_id integer not null)',
        );
        $conn->executeStatement(
            'CREATE TABLE sand_system_announcement_read (
                announcement_id integer not null, user_id integer not null, read_time text null,
                UNIQUE (announcement_id, user_id))',
        );
    }

    /** @param array<string, mixed> $override */
    public static function insertNotification(Connection $conn, int $userId, array $override = []): int
    {
        $conn->insert('sand_system_notification', array_merge([
            'user_id' => $userId,
            'source_code' => 'test',
            'text' => '测试通知',
            'link' => null,
            'read_time' => null,
            'create_time' => '2026-01-02T03:04:05+00:00',
        ], $override));

        return (int) $conn->lastInsertId();
    }

    /** @param array<string, mixed> $override */
    public static function insertAnnouncement(Connection $conn, array $override = []): int
    {
        $conn->insert('sand_system_announcement', array_merge([
            'title' => '测试公告',
            'scope' => 'all',
            'state' => 'published',
            'pinned' => 0,
            'published_time' => '2026-01-02T00:00:00+00:00',
            'expire_time' => null,
            'create_time' => '2026-01-02T00:00:00+00:00',
        ], $override));

        return (int) $conn->lastInsertId();
    }
}
