<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Runtime;

use ArrayObject;
use Sandadmin\Domain\Runtime\ContextResidueException;
use Sandadmin\Domain\Runtime\ContextStoreInterface;
use Webman\Context;

/**
 * ContextStoreInterface 的 Webman 实现：底层是 Workerman\Coroutine\Context
 * （纤程/协程本地存储）。webman 在每个请求进入时 Context::reset，
 * 响应发出前 Context::destroy，因此写进去的键天然按请求隔离，
 * 请求收尾断言能在销毁前发现残留。本类只认 sandadmin. 前缀的键，
 * 不触碰 webman 自己占用的 Request::class、context.onDestroy 等槽位。
 */
final class ScopedContextStore implements ContextStoreInterface
{
    private const PREFIX = 'sandadmin.ctx.';

    public function get(string $key): mixed
    {
        return Context::get(self::PREFIX . $key);
    }

    public function set(string $key, mixed $value): void
    {
        Context::set(self::PREFIX . $key, $value);
    }

    public function has(string $key): bool
    {
        return Context::has(self::PREFIX . $key);
    }

    public function remove(string $key): void
    {
        $all = Context::get();
        if ($all instanceof ArrayObject) {
            $all->offsetUnset(self::PREFIX . $key);
        }
    }

    public function keys(): array
    {
        $all = Context::get();
        if (!$all instanceof ArrayObject) {
            return [];
        }
        $keys = [];
        foreach ($all->getIterator() as $name => $value) {
            if (!is_string($name) || !str_starts_with($name, self::PREFIX) || $value === null) {
                continue;
            }
            $keys[] = substr($name, strlen(self::PREFIX));
        }
        sort($keys);

        return $keys;
    }

    public function assertEmpty(): void
    {
        $rest = $this->keys();
        if ($rest !== []) {
            throw new ContextResidueException($rest);
        }
    }
}
