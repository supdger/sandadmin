<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Lifecycle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Lifecycle\LifecycleKind;
use Sandadmin\Module\Lifecycle\LifecycleRepository;
use Sandadmin\Module\Lifecycle\LifecycleStage;
use Sandadmin\Task\TaskState;
use Sandadmin\Tests\Support\LifecycleFixture;

/**
 * 生命周期仓储水合：items JSON 解码、stage_key 枚举兜底、布尔与时间归一。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL。
 */
final class LifecycleRepositoryTest extends TestCase
{
    private const PUBLIC_ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    private static Connection $conn;
    private LifecycleRepository $requests;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::wipe();
            self::$conn->close();
        }
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->requests = new LifecycleRepository(self::$conn);
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_lifecycle_request WHERE request_id LIKE 'fx\_%'");
    }

    public function testFindByPublicIdHydratesRow(): void
    {
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, [
            'kind' => 'plugin_upgrade_batch',
            'state' => 'running',
            'stage_key' => 'verify',
            'purge_data' => true,
            'rolled_back' => true,
            'items' => '[{"code":"flow","from_version":"1.0.0","to_version":"1.1.0","state":"running"}]',
        ]);
        $item = $this->requests->findByPublicId(self::PUBLIC_ID);

        self::assertNotNull($item);
        self::assertSame(LifecycleKind::PluginUpgradeBatch, $item->kind);
        self::assertSame(TaskState::Running, $item->state);
        self::assertSame(LifecycleStage::Verify, $item->stageKey);
        self::assertTrue($item->deletePluginRow);
        self::assertTrue($item->rolledBack);
        self::assertSame(
            [['code' => 'flow', 'from_version' => '1.0.0', 'to_version' => '1.1.0', 'state' => 'running']],
            $item->items,
        );
    }

    public function testItemsDecodeSkipsMalformedEntries(): void
    {
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, [
            'items' => '[{"code":"flow"},42,{"code":"sand-x","state":"failed"}]',
        ]);
        $item = $this->requests->findByPublicId(self::PUBLIC_ID);

        self::assertNotNull($item);
        self::assertSame(
            [
                ['code' => 'flow', 'from_version' => null, 'to_version' => null, 'state' => 'queued'],
                ['code' => 'sand-x', 'from_version' => null, 'to_version' => null, 'state' => 'failed'],
            ],
            $item->items,
            '非对象条目丢弃、缺 state 落回 queued',
        );
    }

    public function testNullItemsStaysNull(): void
    {
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, ['items' => null]);
        $item = $this->requests->findByPublicId(self::PUBLIC_ID);

        self::assertNotNull($item);
        self::assertNull($item->items);
    }

    public function testUnknownStageKeyFallsBackToNull(): void
    {
        LifecycleFixture::insert(self::$conn, self::PUBLIC_ID, ['stage_key' => 'bogus']);
        $item = $this->requests->findByPublicId(self::PUBLIC_ID);

        self::assertNotNull($item);
        self::assertNull($item->stageKey, '未登记阶段键用 tryFrom 兜底为 null');
    }

    public function testFindByPublicIdMissesReturnNull(): void
    {
        self::assertNull($this->requests->findByPublicId('00000000-0000-4000-8000-000000000000'));
    }
}
