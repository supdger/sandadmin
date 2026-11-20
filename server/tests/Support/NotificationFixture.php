<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * 消息中心相关表的种行夹具：Integration 测试共享，
 * 不建表（表结构由基线迁移负责）、按真实列种入。
 * user_id/dept_id 有外键，调用方负责先种出真实用户与部门。
 */
final class NotificationFixture
{
    /** @param array<string, mixed> $override */
    public static function insertNotification(Connection $conn, int $userId, array $override = []): int
    {
        $id = $conn->fetchOne(
            'INSERT INTO sand_system_notification (user_id, source_code, text, link, read_time, create_time)'
            . ' VALUES (:user_id, :source_code, :text, :link, :read_time, :create_time) RETURNING id',
            array_merge([
                'user_id' => $userId,
                'source_code' => 'test',
                'text' => '测试通知',
                'link' => null,
                'read_time' => null,
                'create_time' => '2026-01-02T03:04:05+00:00',
            ], $override),
        );

        return is_numeric($id) ? (int) $id : 0;
    }

    /** @param array<string, mixed> $override */
    public static function insertAnnouncement(Connection $conn, array $override = []): int
    {
        $id = $conn->fetchOne(
            'INSERT INTO sand_system_announcement'
            . ' (title, content, scope, level, pinned, state, published_time, expire_time,'
            . '  create_time, update_time, created_by, updated_by)'
            . ' VALUES (:title, :content, :scope, :level, :pinned, :state, :published_time, :expire_time,'
            . '  :create_time, :update_time, :created_by, :updated_by) RETURNING id',
            array_merge([
                'title' => 'cknotif公告',
                'content' => '<p>测试公告</p>',
                'scope' => 'all',
                'level' => 'normal',
                'pinned' => 'false',
                'state' => 'published',
                'published_time' => '2026-01-02T00:00:00+00:00',
                'expire_time' => null,
                'create_time' => '2026-01-02T00:00:00+00:00',
                'update_time' => '2026-01-02T00:00:00+00:00',
                'created_by' => null,
                'updated_by' => null,
            ], $override),
        );

        return is_numeric($id) ? (int) $id : 0;
    }
}
