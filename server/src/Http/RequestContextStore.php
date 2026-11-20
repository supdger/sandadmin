<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Domain\Runtime\ContextStoreInterface;
use Sandadmin\Infra\Runtime\ScopedContextStore;

/**
 * RequestContext 的类型化取放口：实际存储走 ContextStoreInterface
 * （请求作用域，由 webman 每请求 Context::reset/destroy 隔离），
 * 不再直接写 Request::$context。请求收尾由 RuntimeGuardMiddleware
 * 统一清键并断言无残留。
 */
final class RequestContextStore
{
    private const KEY = 'request_context';

    public function __construct(
        private readonly ContextStoreInterface $store = new ScopedContextStore(),
    ) {
    }

    public function set(RequestContext $context): void
    {
        $this->store->set(self::KEY, $context);
    }

    public function get(): ?RequestContext
    {
        $value = $this->store->get(self::KEY);

        return $value instanceof RequestContext ? $value : null;
    }

    public function clear(): void
    {
        $this->store->remove(self::KEY);
    }

    /** 请求收尾断言：除生命周期自有键外不允许有任何残留。 */
    public function assertResidueFree(): void
    {
        $this->store->assertEmpty();
    }
}
