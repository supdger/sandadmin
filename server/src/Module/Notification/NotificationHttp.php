<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Notification;

use Sandadmin\Http\ApiOutcome;
use Sandadmin\Module\Announcement\AnnouncementActor;

/** 消息中心 HTTP 装配。登录即可，不另设权限码。 */
final class NotificationHttp
{
    private const ID_PATTERN = '/^[1-9][0-9]{0,17}$/';

    private const IDEMPOTENCY_PATTERN = '/^[A-Za-z0-9._~-]{8,128}$/';

    /** @return array{status: int, body: array<string, mixed>} */
    public static function unreadCount(NotificationRepository $inbox, ?AnnouncementActor $actor, string $requestId): array
    {
        $actor = self::requireActor($actor);

        return ApiOutcome::ok(
            ['count' => $inbox->countUnread($actor->userId, $actor->deptId, new \DateTimeImmutable())],
            $requestId,
        );
    }

    /**
     * @param array<array-key, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(NotificationRepository $inbox, ?AnnouncementActor $actor, array $query, string $requestId): array
    {
        $actor = self::requireActor($actor);
        $page = self::positiveInt($query['page'] ?? null, 1);
        $pageSize = self::positiveInt($query['page_size'] ?? null, 20);
        if ($pageSize > 100) {
            $pageSize = 100;
        }
        $unreadOnly = ($query['unread_only'] ?? null) === true
            || ($query['unread_only'] ?? null) === 'true'
            || ($query['unread_only'] ?? null) === '1';
        $pageResult = $inbox->listInbox(
            $actor->userId,
            $actor->deptId,
            $unreadOnly,
            $page,
            $pageSize,
            new \DateTimeImmutable(),
        );

        return ApiOutcome::ok([
            'items' => $pageResult['items'],
            'total' => $pageResult['total'],
            'page' => $page,
            'page_size' => $pageSize,
        ], $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function markRead(NotificationRepository $inbox, ?AnnouncementActor $actor, string $notificationId, ?string $idempotencyKey, string $requestId): array
    {
        $actor = self::requireActor($actor);
        self::checkKey($idempotencyKey);
        $id = self::id($notificationId);
        $found = $inbox->markOneRead($actor->userId, $id, new \DateTimeImmutable());
        if (!$found) {
            throw NotificationException::notFound();
        }

        return ApiOutcome::ok(new \stdClass(), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function markAllRead(NotificationRepository $inbox, ?AnnouncementActor $actor, ?string $idempotencyKey, string $requestId): array
    {
        $actor = self::requireActor($actor);
        self::checkKey($idempotencyKey);
        $inbox->markAllRead($actor->userId, $actor->deptId, new \DateTimeImmutable());

        return ApiOutcome::ok(new \stdClass(), $requestId);
    }

    /**
     * @param array<string, list<string>> $fields
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function failure(int $status, string $code, string $message, array $fields, string $requestId): array
    {
        return ApiOutcome::fail($status, $code, $message, $fields, $requestId);
    }

    private static function requireActor(?AnnouncementActor $actor): AnnouncementActor
    {
        if ($actor === null || $actor->userId <= 0) {
            throw NotificationException::unauthenticated();
        }

        return $actor;
    }

    private static function checkKey(?string $key): void
    {
        if ($key !== null && preg_match(self::IDEMPOTENCY_PATTERN, $key) !== 1) {
            throw NotificationException::invalid(['idempotency_key' => ['幂等键不正确']]);
        }
    }

    private static function id(string $raw): int
    {
        if (preg_match(self::ID_PATTERN, $raw) !== 1) {
            throw NotificationException::invalid(['notification_id' => ['消息编号不正确']]);
        }

        return (int) $raw;
    }

    private static function positiveInt(mixed $raw, int $fallback): int
    {
        if (!is_numeric($raw)) {
            return $fallback;
        }
        $value = (int) $raw;

        return $value > 0 ? $value : $fallback;
    }
}
