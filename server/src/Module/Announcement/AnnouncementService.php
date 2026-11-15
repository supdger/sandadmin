<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Doctrine\DBAL\Connection;
use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;
use Sandadmin\Infra\Db\Query\Page;

/**
 * 公告业务服务：事务边界、审计、图片引用登记、幂等键重放。
 * 部门/已读/图片/审计与主表写都在同一 DBAL 连接上，随事务一致提交。
 */
final class AnnouncementService
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
        private readonly AnnouncementAuditWriter $audit,
        private readonly ?IdempotencyStore $idempotency = null,
    ) {
    }

    /** @return Page<Announcement> */
    public function search(AnnouncementQuery $query): Page
    {
        $page = $this->announcements->search($query);
        $items = [];
        foreach ($page->items as $item) {
            $items[] = $this->assemble($item);
        }

        return new Page($items, $page->total, $page->page, $page->pageSize);
    }

    public function detail(int $id): Announcement
    {
        return $this->assemble($this->requireOne($id));
    }

    /**
     * @param array<string, mixed> $rawBody 幂等哈希用原始请求体
     */
    public function create(AnnouncementDraft $draft, AnnouncementActor $actor, array $rawBody, string $requestId, ?string $ip, ?string $deviceLabel, ?string $idempotencyKey): mixed
    {
        return $this->idempotent($actor->userId, $idempotencyKey, 'create', $rawBody, function () use ($draft, $actor, $requestId, $ip, $deviceLabel): Announcement {
            $this->assertDeptsExist($draft->deptIds);
            $this->assertFilesExist($draft->imageFileIds);
            $connection = $this->announcements->connection();
            $id = $connection->transactional(function () use ($draft, $actor, $requestId, $ip, $deviceLabel): int {
                $id = $this->announcements->create($draft, $actor->userId);
                $this->announcements->replaceDepts($id, $draft->deptIds);
                $this->announcements->replaceImageUsages($id, $draft->imageFileIds, $draft->title);
                $this->audit->record($actor, 'create', '新建了公告「' . $draft->title . '」', $id, $draft->title, $requestId, $ip, $deviceLabel, $this->draftChanges(null, $draft));

                return $id;
            });

            return $this->detail($id);
        });
    }

    /**
     * @param array<string, mixed> $rawBody 幂等哈希用原始请求体
     */
    public function update(int $id, AnnouncementDraft $draft, AnnouncementActor $actor, array $rawBody, string $requestId, ?string $ip, ?string $deviceLabel, ?string $idempotencyKey): mixed
    {
        return $this->idempotent($actor->userId, $idempotencyKey, 'update', $rawBody + ['_id' => $id], function () use ($id, $draft, $actor, $requestId, $ip, $deviceLabel): Announcement {
            $before = $this->requireOne($id);
            $this->assertDeptsExist($draft->deptIds);
            $this->assertFilesExist($draft->imageFileIds);
            $connection = $this->announcements->connection();
            $connection->transactional(function () use ($id, $draft, $actor, $before, $requestId, $ip, $deviceLabel): void {
                $this->announcements->update($id, $draft, $actor->userId);
                $this->announcements->replaceDepts($id, $draft->deptIds);
                $this->announcements->replaceImageUsages($id, $draft->imageFileIds, $draft->title);
                $this->audit->record($actor, 'update', '更新了公告「' . $draft->title . '」', $id, $draft->title, $requestId, $ip, $deviceLabel, $this->draftChanges($before, $draft));
            });

            return $this->detail($id);
        });
    }

    public function publish(int $id, AnnouncementActor $actor, string $requestId, ?string $ip, ?string $deviceLabel, ?string $idempotencyKey): mixed
    {
        return $this->idempotent($actor->userId, $idempotencyKey, 'publish', ['_id' => $id], function () use ($id, $actor, $requestId, $ip, $deviceLabel): Announcement {
            $item = $this->requireOne($id);
            if ($item->state !== AnnouncementState::Published) {
                $this->announcements->connection()->transactional(function () use ($id, $item, $actor, $requestId, $ip, $deviceLabel): void {
                    $this->announcements->publish($id, $actor->userId);
                    $this->audit->record($actor, 'publish', '发布了公告「' . $item->title . '」', $id, $item->title, $requestId, $ip, $deviceLabel);
                });
            }

            return $this->detail($id);
        });
    }

    public function revoke(int $id, AnnouncementActor $actor, string $requestId, ?string $ip, ?string $deviceLabel, ?string $idempotencyKey): mixed
    {
        return $this->idempotent($actor->userId, $idempotencyKey, 'revoke', ['_id' => $id], function () use ($id, $actor, $requestId, $ip, $deviceLabel): Announcement {
            $item = $this->requireOne($id);
            if ($item->state === AnnouncementState::Published) {
                $this->announcements->connection()->transactional(function () use ($id, $item, $actor, $requestId, $ip, $deviceLabel): void {
                    $this->announcements->withdraw($id, $actor->userId);
                    $this->audit->record($actor, 'revoke', '收回了公告「' . $item->title . '」', $id, $item->title, $requestId, $ip, $deviceLabel);
                });
            }

            return $this->detail($id);
        });
    }

    public function remove(int $id, AnnouncementActor $actor, string $requestId, ?string $ip, ?string $deviceLabel, ?string $idempotencyKey): void
    {
        $this->idempotent($actor->userId, $idempotencyKey, 'delete', ['_id' => $id], function () use ($id, $actor, $requestId, $ip, $deviceLabel): null {
            $item = $this->requireOne($id);
            $this->announcements->connection()->transactional(function () use ($id, $item, $actor, $requestId, $ip, $deviceLabel): void {
                // 已发布的先收回再删，保证审计链上有「收回」语义之外只留删除动作；
                // 已读记录与部门范围由外键级联清理
                if ($item->state === AnnouncementState::Published) {
                    $this->announcements->withdraw($id, $actor->userId);
                }
                $this->announcements->removeImageUsages($id);
                $this->announcements->remove($id);
                $this->audit->record($actor, 'delete', '删除了公告「' . $item->title . '」', $id, $item->title, $requestId, $ip, $deviceLabel);
            });

            return null;
        });
    }

    /** 收件人侧标记已读：不存在或不可见一律 ANNOUNCEMENT_NOT_FOUND，幂等。 */
    public function markRead(int $id, AnnouncementActor $actor, ?string $idempotencyKey): void
    {
        $this->idempotent($actor->userId, $idempotencyKey, 'read', ['_id' => $id], function () use ($id, $actor): null {
            // 可见性判定需要装配好的 dept_ids
            $item = $this->detail($id);
            if (!$item->visibleTo($actor, new \DateTimeImmutable())) {
                throw AnnouncementException::notFound();
            }
            $this->announcements->markRead($id, $actor->userId);

            return null;
        });
    }

    /**
     * @return array{read_count: int, unread_count: int, items: list<array{user_id: int, username: string, nickname: string, read_time: ?string}>}
     */
    public function readStats(int $id): array
    {
        $item = $this->requireOne($id);
        $deptIds = $this->announcements->deptIdsFor($id);
        $items = $this->announcements->readStats($item->scope, $deptIds, $id);
        $readCount = 0;
        foreach ($items as $row) {
            if ($row['read_time'] !== null) {
                $readCount++;
            }
        }

        return [
            'read_count' => $readCount,
            'unread_count' => count($items) - $readCount,
            'items' => $items,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function popupFor(AnnouncementActor $actor): array
    {
        $items = [];
        foreach ($this->announcements->popupFor($actor, new \DateTimeImmutable()) as $item) {
            $items[] = $item->toPopup();
        }

        return $items;
    }

    private function requireOne(int $id): Announcement
    {
        $item = $this->announcements->findById($id);
        if ($item === null) {
            throw AnnouncementException::notFound();
        }

        return $item;
    }

    private function assemble(Announcement $item): Announcement
    {
        $deptIds = $this->announcements->deptIdsFor($item->id);

        return $item->withDerived(
            $deptIds,
            $this->announcements->deptNames($deptIds),
            $this->announcements->readCountFor($item->id),
            $this->announcements->recipientCount($item->scope, $deptIds),
            $this->announcements->imageFileIdsFor($item->id),
            $this->announcements->creatorName($item->createdBy),
        );
    }

    /** @param list<int> $deptIds */
    private function assertDeptsExist(array $deptIds): void
    {
        $found = $this->announcements->existingDeptIds($deptIds);
        if (count($found) !== count($deptIds)) {
            throw AnnouncementException::invalid(['dept_ids' => ['有部门不存在，请重新选择']]);
        }
    }

    /** @param list<int> $fileIds */
    private function assertFilesExist(array $fileIds): void
    {
        $found = $this->announcements->existingFileIds($fileIds);
        if (count($found) !== count($fileIds)) {
            throw AnnouncementException::invalid(['image_file_ids' => ['有图片文件不存在或已删除']]);
        }
    }

    /**
     * 幂等键处理：同键同请求重放首次结果；同键不同内容抛 409。
     *
     * @param array<string, mixed> $payload
     */
    private function idempotent(int $userId, ?string $key, string $action, array $payload, \Closure $work): mixed
    {
        if ($key === null || $this->idempotency === null) {
            return $work();
        }
        $hash = hash('sha256', 'announcement.' . $action . '|' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        $found = $this->idempotency->findActive($userId, $key);
        if ($found !== null) {
            if ($found['request_hash'] !== $hash) {
                throw AnnouncementException::idempotencyConflict();
            }

            return $found['response'] ?? null;
        }
        $response = $work();
        try {
            $this->idempotency->record($userId, $key, $hash, $this->snapshot($response));
        } catch (IdempotencyConflict) {
            throw AnnouncementException::idempotencyConflict();
        }

        return $response;
    }

    /** Announcement 无法直接序列化进幂等存储；快照为契约数组。 */
    private function snapshot(mixed $response): mixed
    {
        if ($response instanceof Announcement) {
            return $response->toApi(new \DateTimeImmutable());
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function draftChanges(?Announcement $before, AnnouncementDraft $draft): array
    {
        if ($before === null) {
            return ['state' => ['before' => null, 'after' => 'draft']];
        }

        return [
            'title' => ['before' => $before->title, 'after' => $draft->title],
            'scope' => ['before' => $before->scope->value, 'after' => $draft->scope->value],
            'level' => ['before' => $before->level->value, 'after' => $draft->level->value],
            'pinned' => ['before' => $before->pinned, 'after' => $draft->pinned],
        ];
    }
}
