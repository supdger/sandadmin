<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Lifecycle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Lifecycle\LifecycleActor;
use Sandadmin\Module\Lifecycle\LifecycleHttp;
use Sandadmin\Module\Lifecycle\LifecycleRepository;
use Sandadmin\Tests\Support\LifecycleFixture;

/** 生命周期详情装配：鉴权、编号校验、404 口径与成功信封。 */
final class LifecycleHttpTest extends TestCase
{
    private const PUBLIC_ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    private Connection $conn;
    private LifecycleRepository $requests;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        LifecycleFixture::createTable($this->conn);
        $this->requests = new LifecycleRepository($this->conn);
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    /** @param array<string, mixed> $row */
    private function seed(array $row = []): void
    {
        LifecycleFixture::insert($this->conn, self::PUBLIC_ID, $row);
    }

    public function testShowRequiresLogin(): void
    {
        $result = LifecycleHttp::show($this->requests, null, self::PUBLIC_ID, 'req-1');

        self::assertSame(401, $result['status']);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $result['body']['code']);
        self::assertSame('req-1', $result['body']['request_id']);
    }

    public function testShowRejectsNonUuid(): void
    {
        foreach (['1', 'not-a-uuid', '1b9d6bcd-bbfd-4b2d-9b5d'] as $bad) {
            $result = LifecycleHttp::show($this->requests, new LifecycleActor(9, true), $bad, 'req-2');
            self::assertSame(400, $result['status'], $bad);
            self::assertSame(ErrorCodeRegistry::VALIDATION_FAILED, $result['body']['code']);
            $fields = $result['body']['fields'] ?? null;
            self::assertInstanceOf(\stdClass::class, $fields);
            self::assertObjectHasProperty('request_id', $fields);
        }
    }

    public function testShowNotFound(): void
    {
        $result = LifecycleHttp::show($this->requests, new LifecycleActor(9, true), self::PUBLIC_ID, 'req-3');

        self::assertSame(404, $result['status']);
        self::assertSame(ErrorCodeRegistry::LIFECYCLE_REQUEST_NOT_FOUND, $result['body']['code']);
    }

    public function testShowHidesStrangerRequest(): void
    {
        $this->seed(['requested_by' => 9]);
        $result = LifecycleHttp::show($this->requests, new LifecycleActor(8, false), self::PUBLIC_ID, 'req-4');

        self::assertSame(404, $result['status'], '无权查看与不存在同一个 404');
    }

    public function testShowReturnsRequestToOwner(): void
    {
        $this->seed(['requested_by' => 9, 'kind' => 'system_upgrade', 'stage_key' => 'download']);
        $result = LifecycleHttp::show($this->requests, new LifecycleActor(9, false), self::PUBLIC_ID, 'req-5');

        self::assertSame(200, $result['status']);
        self::assertSame(ErrorCodeRegistry::OK, $result['body']['code']);
        $data = $result['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame(self::PUBLIC_ID, $data['id']);
        self::assertSame('system_upgrade', $data['kind']);
        self::assertSame('download', $data['stage_key']);
        self::assertSame(60, $data['progress']);
    }

    public function testShowAllowsViewAllActor(): void
    {
        $this->seed(['requested_by' => 9]);
        $result = LifecycleHttp::show($this->requests, new LifecycleActor(8, true), self::PUBLIC_ID, 'req-6');

        self::assertSame(200, $result['status']);
        $data = $result['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame(self::PUBLIC_ID, $data['id']);
    }
}
