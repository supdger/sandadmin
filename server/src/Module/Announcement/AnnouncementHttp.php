<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Sandadmin\Infra\Db\Exception\InvalidQueryException;

/**
 * /api/admin/v1/announcements 与 /me/announcements/popup 的 HTTP 装配：
 * 入口做鉴权/形状校验，返回 ['status', 'body'] 交给控制器打包；
 * 业务错误由 AnnouncementException 携带契约码冒泡到控制器。
 */
final class AnnouncementHttp
{
    private const ID_PATTERN = '/^\d{1,19}$/';
    private const IDEMPOTENCY_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    /**
     * @param array<array-key, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(AnnouncementService $service, ?AnnouncementActor $actor, array $query, string $requestId): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:index');
        try {
            $list = AnnouncementQuery::fromQuery($query);
        } catch (InvalidQueryException $exception) {
            throw AnnouncementException::invalid(self::fieldErrors($exception));
        }
        $page = $service->search($list);
        $now = new \DateTimeImmutable();
        $items = [];
        foreach ($page->items as $item) {
            $items[] = $item->toApi($now);
        }

        return self::success([
            'items' => $items,
            'total' => $page->total,
            'page' => $page->page,
            'page_size' => $page->pageSize,
        ], $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function show(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, string $requestId): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:index');
        $id = self::id($announcementId);

        return self::success($service->detail($id)->toApi(new \DateTimeImmutable()), $requestId);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function create(AnnouncementService $service, ?AnnouncementActor $actor, array $body, ?string $idempotencyKey, string $requestId, ?string $ip, ?string $deviceLabel): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:create');
        self::checkKey($idempotencyKey);
        $draft = AnnouncementDraft::fromArray($body);
        $item = $service->create($draft, $actor, $body, $requestId, $ip, $deviceLabel, $idempotencyKey);

        return self::success(self::apiOf($item), $requestId);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function update(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, array $body, ?string $idempotencyKey, string $requestId, ?string $ip, ?string $deviceLabel): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:update');
        self::checkKey($idempotencyKey);
        $id = self::id($announcementId);
        $draft = AnnouncementDraft::fromArray($body);
        $item = $service->update($id, $draft, $actor, $body, $requestId, $ip, $deviceLabel, $idempotencyKey);

        return self::success(self::apiOf($item), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function destroy(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, ?string $idempotencyKey, string $requestId, ?string $ip, ?string $deviceLabel): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:delete');
        self::checkKey($idempotencyKey);
        $service->remove(self::id($announcementId), $actor, $requestId, $ip, $deviceLabel, $idempotencyKey);

        return self::success(new \stdClass(), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function publish(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, ?string $idempotencyKey, string $requestId, ?string $ip, ?string $deviceLabel): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:publish');
        self::checkKey($idempotencyKey);
        $item = $service->publish(self::id($announcementId), $actor, $requestId, $ip, $deviceLabel, $idempotencyKey);

        return self::success(self::apiOf($item), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function revoke(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, ?string $idempotencyKey, string $requestId, ?string $ip, ?string $deviceLabel): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:revoke');
        self::checkKey($idempotencyKey);
        $item = $service->revoke(self::id($announcementId), $actor, $requestId, $ip, $deviceLabel, $idempotencyKey);

        return self::success(self::apiOf($item), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function markRead(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, ?string $idempotencyKey, string $requestId): array
    {
        if ($actor === null) {
            throw AnnouncementException::unauthenticated();
        }
        self::checkKey($idempotencyKey);
        $service->markRead(self::id($announcementId), $actor, $idempotencyKey);

        return self::success(new \stdClass(), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function readStats(AnnouncementService $service, ?AnnouncementActor $actor, string $announcementId, string $requestId): array
    {
        $actor = self::requirePermission($actor, 'system:announcement:index');

        return self::success($service->readStats(self::id($announcementId)), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function popup(AnnouncementService $service, ?AnnouncementActor $actor, string $requestId): array
    {
        if ($actor === null) {
            throw AnnouncementException::unauthenticated();
        }

        return self::success(['items' => $service->popupFor($actor)], $requestId);
    }

    private static function requirePermission(?AnnouncementActor $actor, string $permission): AnnouncementActor
    {
        if ($actor === null) {
            throw AnnouncementException::unauthenticated();
        }
        if (!$actor->can($permission)) {
            throw AnnouncementException::permissionDenied();
        }

        return $actor;
    }

    private static function checkKey(?string $key): void
    {
        if ($key !== null && preg_match(self::IDEMPOTENCY_PATTERN, $key) !== 1) {
            throw AnnouncementException::invalid(['idempotency_key' => ['幂等键不正确']]);
        }
    }

    private static function id(string $raw): int
    {
        if (preg_match(self::ID_PATTERN, $raw) !== 1) {
            throw AnnouncementException::invalid(['announcement_id' => ['公告编号不正确']]);
        }
        $id = (int) $raw;
        if ($id <= 0) {
            throw AnnouncementException::invalid(['announcement_id' => ['公告编号不正确']]);
        }

        return $id;
    }

    /** @return array<array-key, mixed> */
    private static function apiOf(mixed $item): array
    {
        // 幂等重放时服务层返回已存快照（json_decode 出 stdClass 或数组）
        if ($item instanceof Announcement) {
            return $item->toApi(new \DateTimeImmutable());
        }
        if (is_object($item)) {
            return (array) $item;
        }

        return is_array($item) ? $item : [];
    }


    /** @return array<string, list<string>> */
    private static function fieldErrors(InvalidQueryException $exception): array
    {
        $fields = [];
        foreach ($exception->fields as $field => $rule) {
            $fields[$field] = [$exception->getMessage()];
        }

        return $fields === [] ? ['query' => [$exception->getMessage()]] : $fields;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function success(mixed $data, string $requestId): array
    {
        return [
            'status' => 200,
            'body' => ['data' => $data, 'request_id' => $requestId],
        ];
    }

    /**
     * @param array<string, list<string>> $fields
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function failure(int $status, string $code, string $message, array $fields, string $requestId): array
    {
        return [
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => $requestId,
            ],
        ];
    }
}
