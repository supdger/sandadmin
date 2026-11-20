<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Lifecycle;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Lifecycle\UnavailableLifecycleActorProvider;

/** 生产默认的身份提供方：统一鉴权接入前一律视为未登录。 */
final class UnavailableLifecycleActorProviderTest extends TestCase
{
    public function testActorAlwaysNull(): void
    {
        $provider = new UnavailableLifecycleActorProvider();

        self::assertNull($provider->actor(new \stdClass()));
    }
}
