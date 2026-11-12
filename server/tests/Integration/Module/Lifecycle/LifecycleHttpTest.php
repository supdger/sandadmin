<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Lifecycle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Lifecycle\LifecycleActor;
use Sandadmin\Module\Lifecycle\LifecycleHttp;
use Sandadmin\Module\Lifecycle\LifecycleRepository;

/**
 * 生命周期详情端点：uuid 校验、申请人/管理员可见性、五步阶段与
 * cancelled 状态原样带出。需要 SAND_TEST_DB_URL 指向基线库。
 */
final class LifecycleHttpTest extends TestCase
{
    private static Connection $conn;
    private static int $userId;
    private static int $otherId;
    private LifecycleRepository $repository;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380000002%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name='生命周期部'");
        self::$conn->executeStatement("INSERT INTO sand_system_dept (name, status) VALUES ('生命周期部', 1) ON CONFLICT DO NOTHING");
        $dept = self::toInt(self::$conn->fetchOne("SELECT id FROM sand_system_dept WHERE name='生命周期部'"));
        $insert = "INSERT INTO sand_system_user (phone, nickname, password_hash, dept_id, status) VALUES (:p,:n,'h',:d,1) RETURNING id";
        self::$userId = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000021', 'n' => '申请人', 'd' => $dept]));
        self::$otherId = self::toInt(self::$conn->fetchOne($insert, ['p' => '13800000022', 'n' => '旁观人', 'd' => $dept]));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '1380000002%'");
            self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name='生命周期部'");
            self::$conn->close();
        }
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function toStr(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** @return array<array-key, mixed> */
    private function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected function setUp(): void
    {
        self::$conn->executeStatement('DELETE FROM sand_system_lifecycle_request');
        $this->repository = new LifecycleRepository(self::$conn);
    }

    /** @param array<string, mixed> $over */
    private function insertRequest(array $over = []): string
    {
        $row = [
            'kind' => 'system_upgrade', 'target_code' => 'host', 'source' => 'official',
            'from_version' => '1.0.0', 'to_version' => '1.1.0', 'purge_data' => false,
            'state' => 'running', 'stage' => '下载升级包', 'stage_key' => 'download',
            'progress' => 40, 'rolled_back' => false, 'requested_by' => self::$userId,
            'request_id' => 'cli-' . bin2hex(random_bytes(6)),
        ];
        $row = array_merge($row, $over);
        self::$conn->insert('sand_system_lifecycle_request', $row, [
            'purge_data' => \Doctrine\DBAL\ParameterType::BOOLEAN,
            'rolled_back' => \Doctrine\DBAL\ParameterType::BOOLEAN,
        ]);

        return self::toStr(self::$conn->fetchOne(
            'SELECT public_id FROM sand_system_lifecycle_request WHERE request_id = :rid',
            ['rid' => $row['request_id']],
        ));
    }

    public function testRequesterSeesFullDetail(): void
    {
        $publicId = $this->insertRequest();
        $outcome = LifecycleHttp::show($this->repository, new LifecycleActor(self::$userId, false), $publicId, 'req_t');
        self::assertSame(200, $outcome['status']);
        $data = $this->arr($outcome['body']['data']);
        self::assertSame($publicId, $data['id']);
        self::assertSame('system_upgrade', $data['kind']);
        self::assertSame('host', $data['target_code']);
        self::assertSame('running', $data['state']);
        self::assertSame('download', $data['stage_key']);
        self::assertSame('下载升级包', $data['stage']);
        self::assertSame(40, $data['progress']);
        self::assertFalse($data['rolled_back']);
        self::assertSame('1.0.0', $data['from_version']);
        self::assertSame('1.1.0', $data['to_version']);
        self::assertNotNull($data['create_time']);
    }

    public function testAllStageKeysAndStatesPersisted(): void
    {
        foreach (['check', 'backup', 'download', 'switch', 'verify'] as $i => $stageKey) {
            self::$conn->executeStatement('DELETE FROM sand_system_lifecycle_request');
            $publicId = $this->insertRequest(['stage_key' => $stageKey, 'progress' => $i * 20]);
            $outcome = LifecycleHttp::show($this->repository, new LifecycleActor(self::$userId, false), $publicId, 'r');
            self::assertSame($stageKey, $this->arr($outcome['body']['data'])['stage_key']);
        }
        $publicId = $this->insertRequest([
            'state' => 'succeeded', 'stage' => '检查结果', 'stage_key' => 'verify',
            'progress' => 100, 'finish_time' => '2026-01-02 03:04:05',
        ]);
        $data = $this->arr(LifecycleHttp::show($this->repository, new LifecycleActor(self::$userId, false), $publicId, 'r')['body']['data']);
        self::assertSame('succeeded', $data['state']);
        self::assertNotNull($data['finish_time']);
    }

    public function testCancelledVisibleWithMessageSupport(): void
    {
        // 已取消记录带出 state=cancelled 与阶段信息，前端可显示「已取消，系统没有改动」
        $publicId = $this->insertRequest([
            'state' => 'cancelled', 'stage' => '已取消，系统没有改动', 'progress' => 0,
            'finish_time' => '2026-01-02 00:00:00',
        ]);
        $data = $this->arr(LifecycleHttp::show($this->repository, new LifecycleActor(self::$userId, false), $publicId, 'r')['body']['data']);
        self::assertSame('cancelled', $data['state']);
        self::assertSame('已取消，系统没有改动', $data['stage']);
    }

    public function testBatchItemsAndFailureFields(): void
    {
        $publicId = $this->insertRequest([
            'kind' => 'plugin_upgrade_batch', 'target_code' => '*',
            'items' => json_encode([
                ['code' => 'sand-ai', 'from_version' => '1.0.0', 'to_version' => '1.1.0', 'state' => 'succeeded'],
                ['code' => 'sand-crm', 'from_version' => '2.0.0', 'to_version' => '2.1.0', 'state' => 'running'],
            ]),
            'state' => 'failed', 'stage_key' => null, 'error_code' => 'PKG_VERIFY_FAILED',
            'error_message' => '安装包验签失败', 'rolled_back' => true,
        ]);
        $data = $this->arr(LifecycleHttp::show($this->repository, new LifecycleActor(self::$userId, false), $publicId, 'r')['body']['data']);
        self::assertSame('failed', $data['state']);
        self::assertSame('PKG_VERIFY_FAILED', $data['error_code']);
        self::assertSame('安装包验签失败', $data['error_message']);
        self::assertTrue($data['rolled_back']);
        self::assertNull($data['stage_key']);
        $items = $data['items'];
        self::assertIsArray($items);
        self::assertCount(2, $items);
        self::assertSame('sand-ai', $this->arr($items[0] ?? null)['code']);
        self::assertSame('running', $this->arr($items[1] ?? null)['state']);
    }

    public function testVisibilityRules(): void
    {
        $publicId = $this->insertRequest();
        // 其他人（无管理权限）看别人的申请 → 404
        $outcome = LifecycleHttp::show($this->repository, new LifecycleActor(self::$otherId, false), $publicId, 'r');
        self::assertSame(404, $outcome['status']);
        self::assertSame('LIFECYCLE_REQUEST_NOT_FOUND', $outcome['body']['code']);
        // 管理员视图（system:plugin:index/system:update:index 等效 viewAll）→ 可见
        $outcome = LifecycleHttp::show($this->repository, new LifecycleActor(self::$otherId, true), $publicId, 'r');
        self::assertSame(200, $outcome['status']);
        // 未登录 → 401
        $outcome = LifecycleHttp::show($this->repository, null, $publicId, 'r');
        self::assertSame(401, $outcome['status']);
        self::assertSame('UNAUTHENTICATED', $outcome['body']['code']);
    }

    public function testMissingAndMalformedRequestId(): void
    {
        $actor = new LifecycleActor(self::$userId, true);
        // 不存在的 uuid → 404 契约错误
        $outcome = LifecycleHttp::show($this->repository, $actor, '00000000-0000-0000-0000-000000000000', 'r');
        self::assertSame(404, $outcome['status']);
        self::assertSame('LIFECYCLE_REQUEST_NOT_FOUND', $outcome['body']['code']);
        // 非 uuid → 400 校验错误，字段定位 request_id
        $outcome = LifecycleHttp::show($this->repository, $actor, 'not-a-uuid', 'r');
        self::assertSame(400, $outcome['status']);
        self::assertSame('VALIDATION_FAILED', $outcome['body']['code']);
        $fields = $outcome['body']['fields'];
        self::assertIsObject($fields);
        self::assertObjectHasProperty('request_id', $fields);
        // 信封 request_id 始终回显
        self::assertSame('r', $outcome['body']['request_id']);
    }
}
