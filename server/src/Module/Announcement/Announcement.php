<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/**
 * 公告聚合视图：落库行 + 派生字段（dept、已读数、应收数、expired、图片）。
 * expired = expire_time 已过；不落库。
 */
final readonly class Announcement
{
    /**
     * @param list<int> $deptIds
     * @param list<string> $deptNames
     * @param list<int> $imageFileIds
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public AnnouncementScope $scope,
        public array $deptIds,
        public array $deptNames,
        public int $readCount,
        public int $recipientCount,
        public AnnouncementLevel $level,
        public bool $pinned,
        public AnnouncementState $state,
        public array $imageFileIds,
        public ?\DateTimeImmutable $publishedTime,
        public ?\DateTimeImmutable $expireTime,
        public ?string $createdByName,
        public \DateTimeImmutable $createTime,
        public \DateTimeImmutable $updateTime,
        public ?int $createdBy = null,
        public ?int $updatedBy = null,
    ) {
    }

    /**
     * 装配派生字段（仓储只给主表行，服务层补全聚合视图）。
     *
     * @param list<int> $deptIds
     * @param list<string> $deptNames
     * @param list<int> $imageFileIds
     */
    public function withDerived(
        array $deptIds,
        array $deptNames,
        int $readCount,
        int $recipientCount,
        array $imageFileIds,
        ?string $createdByName,
    ): self {
        return new self(
            $this->id, $this->title, $this->content, $this->scope, $deptIds, $deptNames,
            $readCount, $recipientCount, $this->level, $this->pinned, $this->state,
            $imageFileIds, $this->publishedTime, $this->expireTime, $createdByName,
            $this->createTime, $this->updateTime, $this->createdBy, $this->updatedBy,
        );
    }

    public function expired(\DateTimeImmutable $now): bool
    {
        return $this->expireTime !== null && $this->expireTime <= $now;
    }

    /** 收件人可见：已发布且未过期且在发送范围内。 */
    public function visibleTo(AnnouncementActor $actor, \DateTimeImmutable $now): bool
    {
        if ($this->state !== AnnouncementState::Published || $this->expired($now)) {
            return false;
        }
        if ($this->scope === AnnouncementScope::All) {
            return true;
        }

        return $actor->deptId !== null && in_array($actor->deptId, $this->deptIds, true);
    }

    /** @return array<string, mixed> */
    public function toApi(\DateTimeImmutable $now): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'scope' => $this->scope->value,
            'dept_ids' => $this->deptIds,
            'dept_names' => $this->deptNames,
            'read_count' => $this->readCount,
            'recipient_count' => $this->recipientCount,
            'level' => $this->level->value,
            'pinned' => $this->pinned,
            'state' => $this->state->value,
            'expired' => $this->expired($now),
            'image_file_ids' => $this->imageFileIds,
            'published_time' => $this->publishedTime?->format(DATE_ATOM),
            'expire_time' => $this->expireTime?->format(DATE_ATOM),
            'created_by_name' => $this->createdByName ?? '',
            'create_time' => $this->createTime->format(DATE_ATOM),
            'update_time' => $this->updateTime->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> 重要公告弹窗条目 */
    public function toPopup(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'level' => AnnouncementLevel::Important->value,
            'published_time' => $this->publishedTime?->format(DATE_ATOM),
            'expire_time' => $this->expireTime?->format(DATE_ATOM),
        ];
    }
}
