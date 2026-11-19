<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

/**
 * 不走构造器的实例：给「校验/门禁发生在依赖被触碰之前」的分支当内存假实现。
 * Unit 口径不建任何库连接；仓储/服务是 final 且构造参数是 Connection，
 * 没法注入数组版，这类实例的依赖属性保持未初始化——用例一旦真的触达
 * 依赖会以 Error 失败，等于顺带断言「该分支确实不触库」。
 */
final class Disconnected
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function make(string $class): object
    {
        $instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        if (!$instance instanceof $class) {
            throw new \LogicException('无法为 ' . $class . ' 建立免构造实例');
        }

        return $instance;
    }
}
