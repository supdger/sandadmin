<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * sand_system_lifecycle_request 种行夹具：Integration 测试共享，
 * 不建表（表结构由基线迁移负责）、只管按真实列种入合法行。
 * request_id 每次随机，便于按前缀清理；布尔列按真实类型写。
 */
final class LifecycleFixture
{
    /** @param array<string, mixed> $override */
    public static function insert(Connection $conn, string $publicId, array $override = []): void
    {
        $conn->insert('sand_system_lifecycle_request', array_merge([
            'public_id' => $publicId,
            'kind' => 'plugin_install',
            'target_code' => 'flow',
            'source' => 'official',
            'from_version' => null,
            'to_version' => '1.1.0',
            'purge_data' => false,
            'package_file_id' => null,
            'items' => null,
            'state' => 'running',
            'stage' => '正在下载',
            'stage_key' => null,
            'progress' => 60,
            'rolled_back' => false,
            'error_code' => null,
            'error_message' => null,
            'requested_by' => 9,
            'request_id' => 'fx_' . bin2hex(random_bytes(8)),
            'executor_id' => 'package-ops',
            'create_time' => '2026-01-02T03:04:05+00:00',
            'start_time' => '2026-01-02T03:05:00+00:00',
            'finish_time' => null,
            'heartbeat_time' => null,
        ], $override), [
            'purge_data' => ParameterType::BOOLEAN,
            'rolled_back' => ParameterType::BOOLEAN,
        ]);
    }
}
