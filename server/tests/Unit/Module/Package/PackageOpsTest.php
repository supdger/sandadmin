<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Package\PackageGate;
use Sandadmin\Module\Package\PackageOps;
use Sandadmin\Tests\Support\Disconnected;

/**
 * 插件与系统升级网页入口的免库分支：overview、开关关闭的早退、
 * guard 的登录/超管/编译版三连拒绝、advance 的免库短路与非法编号。
 * 这些分支都在第一次查询前返回，PackageOps 用免构造实例（不建任何
 * 库连接，触达连接属性即以 Error 失败）。入队、目录、推进等落库
 * 行为见 Integration 同名测试。
 */
final class PackageOpsTest extends TestCase
{
    private OrgActor $super;
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (['APP_ENV', 'SAND_COMPILED', 'SAND_PACKAGE_OPS'] as $name) {
            $this->envBackup[$name] = getenv($name);
        }
        $this->super = new OrgActor(7, '超管', null, true, ['*']);
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    private function opsOn(): void
    {
        putenv('APP_ENV');
        putenv('SAND_COMPILED=0');
        putenv('SAND_PACKAGE_OPS=1');
    }

    private function opsOff(): void
    {
        putenv('APP_ENV');
        putenv('SAND_COMPILED');
        putenv('SAND_PACKAGE_OPS');
    }

    private function ops(): PackageOps
    {
        return Disconnected::make(PackageOps::class);
    }

    public function testOverviewShape(): void
    {
        $this->opsOn();
        $result = $this->ops()->overview('req-1');

        self::assertSame(200, $result['status']);
        self::assertSame([
            'compiled' => false,
            'package_ops_enabled' => true,
            'signature_required' => true,
        ], $result['body']['data']);
    }

    public function testMarketAndInstalledEmptyWhenOpsDisabled(): void
    {
        $this->opsOff();
        $market = $this->ops()->market('req-2');
        self::assertSame(200, $market['status']);
        self::assertSame(['items' => []], $market['body']['data']);

        $installed = $this->ops()->installed('req-2');
        self::assertSame(200, $installed['status']);
        self::assertSame(['items' => []], $installed['body']['data']);
    }

    public function testGuardRejectsGuestAndNonSuper(): void
    {
        $this->opsOn();
        $guest = new OrgActor(0, '游客', null, false, []);
        $denied = $this->ops()->install('flow', $guest, 'req-3');
        self::assertSame(401, $denied['status']);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $denied['body']['code']);

        $plain = new OrgActor(3, '普通管理员', null, false, ['*']);
        foreach ([
            fn (): array => $this->ops()->install('flow', $plain, 'req-3'),
            fn (): array => $this->ops()->upgrade('flow', $plain, 'req-3'),
            fn (): array => $this->ops()->uninstall('flow', true, $plain, 'req-3'),
            fn (): array => $this->ops()->upgradeAll($plain, 'req-3'),
            fn (): array => $this->ops()->upload('pack.json', '{}', $plain, 'req-3'),
        ] as $call) {
            $denied = $call();
            self::assertSame(403, $denied['status']);
            self::assertSame(ErrorCodeRegistry::PLUGIN_OPERATION_FORBIDDEN, $denied['body']['code']);
        }
    }

    public function testGuardDeniedWhenOpsDisabled(): void
    {
        $this->opsOff();
        $denied = $this->ops()->install('flow', $this->super, 'req-4');
        self::assertSame(403, $denied['status']);
        self::assertSame(ErrorCodeRegistry::PLUGIN_OPERATION_FORBIDDEN, $denied['body']['code']);
        self::assertSame(PackageGate::COMPILED_MESSAGE, $denied['body']['message']);

        $denied = $this->ops()->startUpdate($this->super, 'req-4');
        self::assertSame(403, $denied['status']);
        self::assertSame(ErrorCodeRegistry::SYSTEM_UPDATE_OPERATION_FORBIDDEN, $denied['body']['code']);
        self::assertSame(PackageGate::UPDATE_COMPILED_MESSAGE, $denied['body']['message']);
    }

    public function testAdvanceNoopsBeforeStorage(): void
    {
        $this->opsOff();
        // 开关关闭：连连接都不碰即返回（触达未初始化连接会 Error）
        $this->ops()->advance('1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed');

        $this->opsOn();
        // 非法编号正则拦截，同样不触库
        $this->ops()->advance('not-an-id');

        $this->addToAssertionCount(1);
    }
}
