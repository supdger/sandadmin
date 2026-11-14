<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\LoginLog;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Sandadmin\Module\AuditLog\OperateLogWriter;
use Sandadmin\Module\LoginLog\LoginLogWriter;
use Webman\Event\BootStrap;

/**
 * user.login 监听器接线：插件自带的监听器组装旧表形状，必须不注册，
 * 宿主 LoginLogWriter 是唯一登录日志写入方；user.operateLog 同样被
 * 宿主 OperateLogWriter 接管（插件监听器写的旧表 1.x 不存在）。
 * 用真实 event 配置文件复现框架的合并结果，再走 getEvents 断言。
 */
final class LoginEventWiringTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetCollected();
    }

    protected function tearDown(): void
    {
        $this->resetCollected();
    }

    public function testUserLoginKeepsOnlyHostWriter(): void
    {
        $callbacks = self::callbacksFor('user.login');

        self::assertCount(1, $callbacks);
        self::assertInstanceOf(LoginLogWriter::class, $callbacks[0][0]);
        self::assertSame('record', $callbacks[0][1]);
    }

    public function testOperateLogKeepsHostWriter(): void
    {
        $callbacks = self::callbacksFor('user.operateLog');

        self::assertCount(1, $callbacks);
        self::assertInstanceOf(OperateLogWriter::class, $callbacks[0][0]);
        self::assertSame('record', $callbacks[0][1]);
    }

    /**
     * 复现 App::loadAllConfig + Config::load 对 event 键的合并：
     * 宿主 config/plugin 先于 plugin/<name>/config 加载，后者按键合并覆盖，
     * 再交给框架的监听器扫描。插件目录不存在时只剩标记节，行为一致。
     * @return list<array<array-key, mixed>>
     */
    private static function callbacksFor(string $name): array
    {
        $server = dirname(__DIR__, 4);
        $tree = [
            'event' => self::configArray($server . '/config/event.php'),
            'plugin' => [
                'sandadmin' => [
                    'event' => array_replace_recursive(
                        self::configArray($server . '/config/plugin/sandadmin/event.php'),
                        self::configArray($server . '/plugin/sandadmin/config/event.php'),
                    ),
                ],
            ],
        ];
        $method = new ReflectionMethod(BootStrap::class, 'getEvents');
        $method->setAccessible(true);
        $method->invoke(null, [$tree]);

        $property = new ReflectionProperty(BootStrap::class, 'events');
        $property->setAccessible(true);
        $collected = $property->getValue();
        if (!is_array($collected)) {
            self::fail('BootStrap::$events 应为数组');
        }
        $group = $collected[$name] ?? [];
        if (!is_array($group)) {
            self::fail("$name 的监听器分组应为数组");
        }
        $callbacks = [];
        foreach ($group as $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $callback) {
                if (is_array($callback)) {
                    $callbacks[] = $callback;
                }
            }
        }

        return $callbacks;
    }

    /** @return array<array-key, mixed> */
    private static function configArray(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $config = include $path;
        if (!is_array($config)) {
            self::fail("$path 应返回数组");
        }

        return $config;
    }

    /** BootStrap::$events 是静态收集表，用例间要清干净。 */
    private function resetCollected(): void
    {
        $property = new ReflectionProperty(BootStrap::class, 'events');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }
}
