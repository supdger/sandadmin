<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support\Factory;

use Sandadmin\Module\Announcement\Announcement;
use Sandadmin\Module\Announcement\AnnouncementLevel;
use Sandadmin\Module\Announcement\AnnouncementScope;
use Sandadmin\Module\Announcement\AnnouncementState;

/** Unit 用内存工厂：不连库造公告聚合值对象。 */
final class AnnouncementFactory
{
    private const FIXED_TIME = '2026-01-01T00:00:00+00:00';

    /**
     * @param list<int> $deptIds
     * @param list<string> $deptNames
     * @param list<int> $imageFileIds
     */
    public static function make(
        int $id = 1,
        string $title = '测试公告',
        string $content = '<p>正文</p>',
        AnnouncementScope $scope = AnnouncementScope::All,
        array $deptIds = [],
        array $deptNames = [],
        int $readCount = 0,
        int $recipientCount = 0,
        AnnouncementLevel $level = AnnouncementLevel::Normal,
        bool $pinned = false,
        AnnouncementState $state = AnnouncementState::Draft,
        array $imageFileIds = [],
        ?\DateTimeImmutable $publishedTime = null,
        ?\DateTimeImmutable $expireTime = null,
        ?string $createdByName = '管理员',
        ?\DateTimeImmutable $createTime = null,
        ?\DateTimeImmutable $updateTime = null,
        ?int $createdBy = null,
        ?int $updatedBy = null,
    ): Announcement {
        $fixed = new \DateTimeImmutable(self::FIXED_TIME);
        return new Announcement(
            $id,
            $title,
            $content,
            $scope,
            $deptIds,
            $deptNames,
            $readCount,
            $recipientCount,
            $level,
            $pinned,
            $state,
            $imageFileIds,
            $publishedTime,
            $expireTime,
            $createdByName,
            $createTime ?? $fixed,
            $updateTime ?? $fixed,
            $createdBy,
            $updatedBy,
        );
    }
}
