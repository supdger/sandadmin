<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Notification;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * 消息列表 = 本人站内通知 + 当前可见公告。
 * 公告不按人复制进通知表，未读以 announcement_read 是否有记录为准。
 */
final class NotificationRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function countUnread(int $userId, ?int $deptId, \DateTimeImmutable $now): int
    {
        $params = ['uid' => $userId, 'now' => $now->format(DATE_ATOM)];
        $scope = $this->scopeSql($deptId, $params);
        $count = $this->connection->fetchOne(
            'SELECT ('
            . ' SELECT COUNT(*) FROM sand_system_notification n'
            . ' WHERE n.user_id = :uid AND n.read_time IS NULL'
            . ') + ('
            . ' SELECT COUNT(*) FROM sand_system_announcement a'
            . " WHERE a.state = 'published'"
            . ' AND (a.expire_time IS NULL OR a.expire_time > :now)'
            . ' AND NOT EXISTS (SELECT 1 FROM sand_system_announcement_read r'
            . '  WHERE r.announcement_id = a.id AND r.user_id = :uid)'
            . $scope
            . ')',
            $params,
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listInbox(int $userId, ?int $deptId, bool $unreadOnly, int $page, int $pageSize, \DateTimeImmutable $now): array
    {
        $params = ['uid' => $userId, 'now' => $now->format(DATE_ATOM)];
        $scope = $this->scopeSql($deptId, $params);
        $unread = $unreadOnly ? ' WHERE inbox.read_time IS NULL' : '';
        $from = '(' . $this->inboxSql($scope) . ') inbox' . $unread;
        $totalRaw = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $from, $params);
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;
        $params['limit'] = $pageSize;
        $params['offset'] = ($page - 1) * $pageSize;
        $types = ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER, 'uid' => ParameterType::INTEGER];
        if (array_key_exists('dept', $params)) {
            $types['dept'] = ParameterType::INTEGER;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . $from
            . ' ORDER BY pinned DESC, create_time DESC, id DESC'
            . ' LIMIT :limit OFFSET :offset',
            $params,
            $types,
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->toItem($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function markOneRead(int $userId, int $notificationId, \DateTimeImmutable $now): bool
    {
        $updated = $this->connection->executeStatement(
            'UPDATE sand_system_notification SET read_time = :time'
            . ' WHERE id = :id AND user_id = :uid AND read_time IS NULL',
            [
                'time' => $now->format(DATE_ATOM),
                'id' => $notificationId,
                'uid' => $userId,
            ],
        );
        if ($updated > 0) {
            return true;
        }
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM sand_system_notification WHERE id = :id AND user_id = :uid',
            ['id' => $notificationId, 'uid' => $userId],
        );

        return $exists !== false && $exists !== null;
    }

    public function markAllRead(int $userId, ?int $deptId, \DateTimeImmutable $now): void
    {
        $this->connection->transactional(function (Connection $connection) use ($userId, $deptId, $now): void {
            $connection->executeStatement(
                'UPDATE sand_system_notification SET read_time = :time'
                . ' WHERE user_id = :uid AND read_time IS NULL',
                ['time' => $now->format(DATE_ATOM), 'uid' => $userId],
            );
            $params = ['uid' => $userId, 'now' => $now->format(DATE_ATOM), 'time' => $now->format(DATE_ATOM)];
            $scope = $this->scopeSql($deptId, $params);
            $connection->executeStatement(
                'INSERT INTO sand_system_announcement_read (announcement_id, user_id, read_time)'
                . ' SELECT a.id, :uid, :time FROM sand_system_announcement a'
                . " WHERE a.state = 'published'"
                . ' AND (a.expire_time IS NULL OR a.expire_time > :now)'
                . ' AND NOT EXISTS (SELECT 1 FROM sand_system_announcement_read r'
                . '  WHERE r.announcement_id = a.id AND r.user_id = :uid)'
                . $scope
                . ' ON CONFLICT (announcement_id, user_id) DO NOTHING',
                $params,
            );
        });
    }

    /**
     * @param array<string, mixed> $params
     */
    private function scopeSql(?int $deptId, array &$params): string
    {
        if ($deptId === null) {
            return " AND a.scope = 'all'";
        }
        $params['dept'] = $deptId;

        return " AND (a.scope = 'all' OR EXISTS ("
            . 'SELECT 1 FROM sand_system_announcement_dept d'
            . ' WHERE d.announcement_id = a.id AND d.dept_id = :dept))';
    }

    private function inboxSql(string $scope): string
    {
        return 'SELECT n.id::text AS id, \'notification\' AS kind, n.source_code, n.text,'
            . ' NULL::text AS title, NULL::text AS announcement_id, n.link, n.read_time, n.create_time, false AS pinned'
            . ' FROM sand_system_notification n WHERE n.user_id = :uid'
            . ' UNION ALL'
            . ' SELECT a.id::text, \'announcement\', NULL::text, a.title, a.title, a.id::text, NULL::text,'
            . ' r.read_time, COALESCE(a.published_time, a.create_time), a.pinned'
            . ' FROM sand_system_announcement a'
            . ' LEFT JOIN sand_system_announcement_read r'
            . '   ON r.announcement_id = a.id AND r.user_id = :uid'
            . " WHERE a.state = 'published'"
            . ' AND (a.expire_time IS NULL OR a.expire_time > :now)'
            . $scope;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function toItem(array $row): array
    {
        $kind = is_string($row['kind'] ?? null) ? $row['kind'] : 'notification';
        $readTime = $row['read_time'] ?? null;
        $createTime = $row['create_time'] ?? null;
        $announcementId = $row['announcement_id'] ?? null;

        return [
            'id' => is_scalar($row['id'] ?? null) ? (string) $row['id'] : '',
            'kind' => $kind === 'announcement' ? 'announcement' : 'notification',
            'source_code' => is_string($row['source_code'] ?? null) ? $row['source_code'] : null,
            'text' => is_string($row['text'] ?? null) ? $row['text'] : '',
            'title' => is_string($row['title'] ?? null) ? $row['title'] : null,
            'announcement_id' => is_scalar($announcementId) && (string) $announcementId !== '' ? (string) $announcementId : null,
            'link' => is_string($row['link'] ?? null) ? $row['link'] : null,
            'read_time' => is_string($readTime) ? (new \DateTimeImmutable($readTime))->format(DATE_ATOM) : null,
            'create_time' => is_string($createTime) ? (new \DateTimeImmutable($createTime))->format(DATE_ATOM) : '',
        ];
    }
}
