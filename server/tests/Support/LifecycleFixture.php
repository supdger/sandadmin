<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * sand_system_lifecycle_request 内存表夹具：sqlite 最小建表 +
 * 一行合法默认值，模块 Unit 测试共享，不连外部库。
 * 布尔列写整数 1/0，与服务侧 true/'t'/1/'1' 归一口径一致。
 */
final class LifecycleFixture
{
    public static function createTable(Connection $conn): void
    {
        $conn->executeStatement(
            'CREATE TABLE sand_system_lifecycle_request (id integer primary key autoincrement,
                public_id varchar(36) not null, kind varchar(32) not null,
                target_code varchar(128) not null, source varchar(16) not null,
                from_version varchar(32) null, to_version varchar(32) null,
                purge_data integer not null default 0, package_file_id integer null,
                items text null, state varchar(16) not null default \'queued\',
                stage varchar(64) null, stage_key varchar(32) null,
                progress integer not null default 0, rolled_back integer not null default 0,
                error_code varchar(64) null, error_message text null,
                requested_by integer not null default 0, request_id varchar(64) null,
                executor_id varchar(64) null,
                create_time text null, start_time text null, finish_time text null,
                heartbeat_time text null)',
        );
    }

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
            'purge_data' => 0,
            'package_file_id' => null,
            'items' => null,
            'state' => 'running',
            'stage' => '正在下载',
            'stage_key' => null,
            'progress' => 60,
            'rolled_back' => 0,
            'error_code' => null,
            'error_message' => null,
            'requested_by' => 9,
            'request_id' => 'pkg_test_fixture',
            'executor_id' => 'package-ops',
            'create_time' => '2026-01-02T03:04:05+00:00',
            'start_time' => '2026-01-02T03:05:00+00:00',
            'finish_time' => null,
            'heartbeat_time' => null,
        ], $override));
    }
}
