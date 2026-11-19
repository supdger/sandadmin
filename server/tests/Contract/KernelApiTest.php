<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Build\ContractBuild;
use Sandadmin\Contract\KernelApi\FragmentCheck;

/**
 * kernel_api 契约测试：插件片段的存在性、合法性、命名空间隔离与互冲突。
 * 夹具在 tests/Fixtures/plugin/：sand-hello 带合法片段，sand-other 无片段。
 */
final class KernelApiTest extends TestCase
{
    private static function hostContract(): string
    {
        return ContractAssertions::repoRoot() . '/contract/openapi.yaml';
    }

    private static function fixtureDir(string $app): string
    {
        return self::serverFixtures() . '/' . $app;
    }

    private static function serverFixtures(): string
    {
        return ContractAssertions::serverRoot() . '/tests/Fixtures/plugin';
    }

    public function testValidFragmentPasses(): void
    {
        $problems = FragmentCheck::check(self::fixtureDir('sand-hello'), self::hostContract());
        self::assertSame([], $problems, implode("\n", $problems));
    }

    public function testMissingFragmentFailsClosed(): void
    {
        $problems = FragmentCheck::check(self::fixtureDir('sand-other'), self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('缺少契约片段', implode("\n", $problems));
    }

    public function testFragmentMayReuseHostCodes(): void
    {
        // sand-hello 片段引用了宿主已登记的 NOT_FOUND：有宿主契约时不算未登记
        $problems = FragmentCheck::check(self::fixtureDir('sand-hello'), self::hostContract());
        self::assertSame([], $problems, implode("\n", $problems));
    }

    public function testForeignDomainPermissionFails(): void
    {
        $dir = self::writeBadFragment(<<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            paths:
              /api/admin/v1/plugin/sand-bad/things:
                get:
                  operationId: sandBadThingsIndex
                  summary: x
                  x-handler: 'SandBad\Http\ThingController::index'
                  x-permission: system:user:index
                  responses: {'200': {description: ok}}
            YAML);
        $problems = FragmentCheck::check($dir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('sand_bad:', implode("\n", $problems));
    }

    public function testForeignNamespacePathFails(): void
    {
        $dir = self::writeBadFragment(<<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            paths:
              /api/admin/v1/users:
                get:
                  operationId: sandBadUsersIndex
                  summary: x
                  x-handler: 'SandBad\Http\ThingController::index'
                  x-permission: sand_bad:thing:index
                  responses: {'200': {description: ok}}
            YAML);
        $problems = FragmentCheck::check($dir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('/api/admin/v1/plugin/sand-bad/', implode("\n", $problems));
    }

    public function testToolWithoutPrefixFails(): void
    {
        $dir = self::writeBadFragment(<<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            x-mcp-tools:
              - name: other_tool
                tier: read
                title: x
                description: x
                permission: sand_bad:thing:index
                dev_only: true
                input: {type: object, properties: {}}
            paths: {}
            YAML);
        $problems = FragmentCheck::check($dir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('sand_bad_', implode("\n", $problems));
    }

    public function testUndeclaredErrorCodeFails(): void
    {
        $dir = self::writeBadFragment(<<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            paths:
              /api/admin/v1/plugin/sand-bad/things:
                get:
                  operationId: sandBadThingsIndex
                  summary: x
                  x-handler: 'SandBad\Http\ThingController::index'
                  x-permission: sand_bad:thing:index
                  x-error-codes: [NOT_A_REAL_CODE]
                  responses: {'200': {description: ok}}
            YAML);
        $problems = FragmentCheck::check($dir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('NOT_A_REAL_CODE', implode("\n", $problems));
    }

    public function testWriteTierOnPublicRouteFails(): void
    {
        $dir = self::writeBadFragment(<<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            paths:
              /api/admin/v1/plugin/sand-bad/things:
                post:
                  operationId: sandBadThingsStore
                  summary: x
                  x-handler: 'SandBad\Http\ThingController::store'
                  x-permission: public
                  x-mcp: {name: sand_bad_thing_store, tier: write}
                  responses: {'200': {description: ok}}
            YAML);
        $problems = FragmentCheck::check($dir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('write', implode("\n", $problems));
    }

    public function testNamedConnectionFragmentPasses(): void
    {
        // sand-hello 片段带合规 x-connections：四规则声明齐全即通过
        $problems = FragmentCheck::check(self::fixtureDir('sand-hello'), self::hostContract());
        self::assertSame([], $problems, implode("\n", $problems));
    }

    public function testPrivateLedgerConnectionFails(): void
    {
        // 命名连接账本想记到连接库之外（私有台账），违反「每连接一本同结构账本」
        $dir = self::writeBadFragment(<<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: private
                outbox: none
                audit_sink: none
            paths: {}
            YAML);
        $problems = FragmentCheck::check($dir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('per_connection', implode("\n", $problems));
    }

    public function testKernelApiDirMissingFragmentsFails(): void
    {
        // sand-other 无片段：kernel_api 口径逐包缺片段即失败
        $result = ContractBuild::checkKernelApi(self::serverFixtures(), self::hostContract());
        self::assertSame('failed', $result['result']);
        self::assertStringContainsString('sand-other', implode("\n", $result['problems']));
    }

    public function testKernelApiEmptyDirFails(): void
    {
        $dir = sys_get_temp_dir() . '/contract-empty-' . getmypid();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            self::fail('无法创建临时目录');
        }
        try {
            $result = ContractBuild::checkKernelApi($dir, self::hostContract());
            self::assertSame('failed', $result['result']);
            self::assertStringContainsString('契约片段', implode("\n", $result['problems']));
        } finally {
            rmdir($dir);
        }
    }

    public function testInstalledPluginsCollectClean(): void
    {
        // server/plugin 下现有片段（含 fixtures 不算）与宿主零冲突；
        // 无片段的 legacy 插件由构建 warning 覆盖，不在本断言范围
        $result = ContractAssertions::collectAllDocs();
        self::assertSame([], $result['problems'], implode("\n", $result['problems']));
        self::assertSame([], $result['conflicts'], implode("\n", $result['conflicts']));
    }

    /** 写一个临时插件目录（sand-bad），返回插件根；用毕自行清理 */
    private static function writeBadFragment(string $yaml): string
    {
        $dir = sys_get_temp_dir() . '/contract-bad-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '/sand-bad';
        if (!is_dir($dir . '/contract') && !mkdir($dir . '/contract', 0755, true)) {
            self::fail('无法创建临时片段目录');
        }
        file_put_contents($dir . '/contract/openapi.yaml', $yaml);
        self::assertFileExists($dir . '/contract/openapi.yaml');

        return $dir;
    }
}
