<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Package;

use Sandadmin\Module\Package\PackageOps;
use Sandadmin\Tests\Integration\Module\Tools\ToolsCase;

/**
 * 系统更新状态页契约：running_request 只含排队/执行中；
 * 失败已回滚时 last_failed_request 下发且 current_version 回真实版本；
 * 三个时间字段一律 ATOM（ISO 8601 带偏移）。
 */
final class PackageOpsStatusTest extends ToolsCase
{
    private const ATOM_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

    private function ops(): PackageOps
    {
        return new PackageOps($this->conn);
    }

    /** @param array<string, mixed> $over */
    private function insertRequest(int $requestedBy, array $over = []): void
    {
        $row = [
            'kind' => 'system_upgrade', 'target_code' => 'host', 'source' => 'official',
            'from_version' => '1.1.0', 'to_version' => '1.2.0', 'purge_data' => false,
            'state' => 'running', 'stage' => '正在下载新版本', 'stage_key' => 'download',
            'progress' => 60, 'rolled_back' => false, 'requested_by' => $requestedBy,
            'request_id' => 'cli-' . bin2hex(random_bytes(6)),
            'start_time' => '2026-01-02 03:04:05+00', 'finish_time' => null,
        ];
        $this->conn->insert('sand_system_lifecycle_request', array_merge($row, $over), [
            'purge_data' => \Doctrine\DBAL\ParameterType::BOOLEAN,
            'rolled_back' => \Doctrine\DBAL\ParameterType::BOOLEAN,
        ]);
    }

    /** @return array<mixed> */
    private function fetchStatus(): array
    {
        $result = $this->ops()->updateStatus('req-status');
        self::assertSame(200, $result['status']);
        $data = $result['body']['data'] ?? null;
        self::assertIsArray($data);

        return $data;
    }

    public function testSucceededUpgradeNotSentAsRunning(): void
    {
        $actor = $this->userActor();
        $this->insertRequest($actor->userId, [
            'state' => 'succeeded', 'stage' => '已完成', 'stage_key' => 'verify',
            'progress' => 100, 'finish_time' => '2026-01-02 03:10:00+00',
        ]);
        $data = $this->fetchStatus();
        // 完成状态不再下发：前端据此判定「没有正在跑的升级」
        self::assertNull($data['running_request']);
        self::assertNull($data['last_failed_request']);
    }

    public function testFailedRolledBackUpgradeKeepsRealCurrentVersion(): void
    {
        $actor = $this->userActor();
        // apply() 只在成功时写 host.version；回滚场景下库里留的是升级前的版本
        $this->conn->executeStatement(
            'INSERT INTO sand_system_setting (key, value, group_code) VALUES (\'host.version\', CAST(:value AS jsonb), \'basic\')',
            ['value' => '"1.1.0"'],
        );
        $this->insertRequest($actor->userId, [
            'state' => 'failed', 'stage' => '正在切换到新版本', 'stage_key' => 'switch',
            'progress' => 80, 'rolled_back' => true,
            'error_code' => 'PKG_SWITCH_FAILED', 'error_message' => '切换失败，已回到旧版本',
            'finish_time' => '2026-01-02 03:12:00+00',
        ]);
        $data = $this->fetchStatus();
        self::assertNull($data['running_request']);
        $failed = $data['last_failed_request'] ?? null;
        self::assertIsArray($failed);
        self::assertSame('failed', $failed['state']);
        self::assertTrue($failed['rolled_back']);
        self::assertSame('1.1.0', $failed['from_version']);
        // current_version 是设置里的真实版本，不是失败申请里的 to_version
        self::assertSame('1.1.0', $data['current_version']);
        self::assertSame($failed['from_version'], $data['current_version']);
    }

    public function testRequestBodyTimesAreAtom(): void
    {
        $actor = $this->userActor();
        $this->insertRequest($actor->userId);
        $running = $this->fetchStatus()['running_request'] ?? null;
        self::assertIsArray($running);
        self::assertIsString($running['create_time']);
        self::assertMatchesRegularExpression(self::ATOM_PATTERN, $running['create_time']);
        self::assertIsString($running['start_time']);
        self::assertMatchesRegularExpression(self::ATOM_PATTERN, $running['start_time']);
        self::assertNull($running['finish_time']);
    }
}
