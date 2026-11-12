<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;

/** 组织模块审计：与业务写共用连接，由调用方放进同一事务。 */
final class OrgAudit
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string, mixed>|null $changes */
    public function record(
        OrgActor $actor,
        string $module,
        string $action,
        string $actionText,
        string $targetType,
        string $targetId,
        string $targetLabel,
        string $requestId,
        ?string $ip,
        ?string $deviceLabel,
        ?array $changes = null,
    ): void {
        $this->connection->insert('sand_system_audit_log', [
            'actor_type' => 'user',
            'actor_id' => $actor->userId > 0 ? $actor->userId : null,
            'actor_name' => mb_substr($actor->name, 0, 20),
            'module' => $module,
            'action' => $action,
            'action_text' => mb_substr($actionText, 0, 255),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_label' => mb_substr($targetLabel, 0, 128),
            'succeeded' => 'true',
            'changes' => $changes === null ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
            'ip' => self::ip($ip),
            'location' => '',
            'device_label' => $deviceLabel === null ? '' : mb_substr($deviceLabel, 0, 64),
            'request_id' => mb_substr($requestId, 0, 64),
        ]);
    }

    private static function ip(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }
}
