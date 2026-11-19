<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Lifecycle;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Lifecycle\LifecycleActor;
use Sandadmin\Module\Lifecycle\LifecycleHttp;
use Sandadmin\Module\Lifecycle\LifecycleRepository;
use Sandadmin\Tests\Support\Disconnected;

/**
 * 生命周期详情装配的入库前分支：鉴权与编号校验都在仓储取数之前返回，
 * 仓储用免构造实例（不建任何库连接，触达即 Error）。404/成功取数
 * 行为见 Integration 同名测试。
 */
final class LifecycleHttpTest extends TestCase
{
    private const PUBLIC_ID = '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed';

    private function requests(): LifecycleRepository
    {
        return Disconnected::make(LifecycleRepository::class);
    }

    public function testShowRequiresLogin(): void
    {
        $result = LifecycleHttp::show($this->requests(), null, self::PUBLIC_ID, 'req-1');

        self::assertSame(401, $result['status']);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $result['body']['code']);
        self::assertSame('req-1', $result['body']['request_id']);
    }

    public function testShowRejectsNonUuid(): void
    {
        foreach (['1', 'not-a-uuid', '1b9d6bcd-bbfd-4b2d-9b5d'] as $bad) {
            $result = LifecycleHttp::show($this->requests(), new LifecycleActor(9, true), $bad, 'req-2');
            self::assertSame(400, $result['status'], $bad);
            self::assertSame(ErrorCodeRegistry::VALIDATION_FAILED, $result['body']['code']);
            $fields = $result['body']['fields'] ?? null;
            self::assertInstanceOf(\stdClass::class, $fields);
            self::assertObjectHasProperty('request_id', $fields);
        }
    }

    public function testFailureEnvelope(): void
    {
        $result = LifecycleHttp::failure(404, ErrorCodeRegistry::LIFECYCLE_REQUEST_NOT_FOUND, '申请不存在或无权查看', 'req-9');

        self::assertSame(404, $result['status']);
        self::assertSame(ErrorCodeRegistry::LIFECYCLE_REQUEST_NOT_FOUND, $result['body']['code']);
        self::assertSame('申请不存在或无权查看', $result['body']['message']);
        self::assertSame('req-9', $result['body']['request_id']);
    }
}
