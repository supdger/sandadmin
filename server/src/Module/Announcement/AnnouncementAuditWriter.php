<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Doctrine\DBAL\Connection;

/**
 * 公告操作审计：写 sand_system_audit_log（只增不改）。
 * 与公告写操作共用一个连接，由服务层包进同一事务；
 * changes 只记非敏感字段差异，不落内容全文。
 */
final class AnnouncementAuditWriter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed>|null $changes 改前/改后差异
     */
    public function record(
        AnnouncementActor $actor,
        string $action,
        string $actionText,
        int $announcementId,
        string $title,
        string $requestId,
        ?string $ip,
        ?string $deviceLabel,
        ?array $changes = null,
    ): void {
        $this->connection->insert('sand_system_audit_log', [
            'actor_type' => 'user',
            'actor_id' => $actor->userId > 0 ? $actor->userId : null,
            'actor_name' => mb_substr($actor->name, 0, 20),
            'module' => 'announcement',
            'action' => $action,
            'action_text' => mb_substr($actionText, 0, 255),
            'target_type' => 'announcement',
            'target_id' => (string) $announcementId,
            'target_label' => mb_substr($title, 0, 128),
            'succeeded' => 'true',
            'changes' => $changes === null ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
            'location' => '',
            'device_label' => $deviceLabel ?? '',
            'request_id' => $requestId,
        ]);
    }
}
