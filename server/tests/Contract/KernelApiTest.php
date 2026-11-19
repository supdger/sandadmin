<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Build\ConflictCheck;
use Sandadmin\Contract\Build\ContractBuild;
use Sandadmin\Contract\Build\ContractDoc;
use Sandadmin\Contract\KernelApi\FragmentCheck;

/**
 * kernel_api 契约测试：插件片段的存在性、合法性、命名空间隔离与互冲突，
 * 外加运行时对齐（薄壳/路由表、Permission 注解码、x-handler 文件）。
 * 夹具在 tests/Fixtures/plugin/：sand-hello 带合法片段与配套代码/生成物，
 * sand-other 无片段。
 *
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class KernelApiTest extends TestCase
{
    private string $repoDir = '';

    protected function tearDown(): void
    {
        if ($this->repoDir !== '' && is_dir($this->repoDir)) {
            self::removeTree($this->repoDir);
            $this->repoDir = '';
        }
    }

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

    public function testInstalledPluginsAlignWithContract(): void
    {
        // 已装契约插件逐个过 FragmentCheck（含运行时对齐）；
        // 当前 server/plugin 无契约插件，循环为空，保留遍历让插件落地即接管
        $fragments = glob(ContractAssertions::serverRoot() . '/plugin/*/contract/openapi.yaml');
        if ($fragments === false) {
            $fragments = [];
        }
        $all = [];
        foreach ($fragments as $fragment) {
            $pluginDir = dirname(dirname($fragment));
            foreach (FragmentCheck::check($pluginDir, self::hostContract()) as $problem) {
                $all[] = $problem;
            }
        }
        self::assertSame([], $all, implode("\n", $all));
    }

    public function testBuiltArtifactsAlignWithFragment(): void
    {
        $pluginDir = $this->buildFixtureRepo();

        // 薄壳：require 生成表 + 关掉本插件默认路由，不含手写 Route::verb 注册
        $shell = file_get_contents($pluginDir . '/config/route.php');
        self::assertIsString($shell);
        self::assertStringContainsString('route.generated.php', $shell);
        self::assertStringContainsString("Route::disableDefaultRoute('sandhello')", $shell);
        self::assertDoesNotMatchRegularExpression(
            "/Route::(get|post|put|patch|delete|head|options|group)\\s*\\(/",
            $shell,
        );

        // 生成表 METHOD/path 集合 == 契约 paths×methods
        $doc = ContractDoc::parse($pluginDir . '/contract/openapi.yaml', 'sand-hello');
        $emitted = self::fileRoutes($pluginDir . '/config/route.generated.php');
        self::assertSame(self::docRoutes($doc), $emitted);

        // 注册表里本插件域的码 == 片段声明码集合
        $registry = file_get_contents($this->repoDir . '/server/src/Contract/Generated/PermissionRegistry.php');
        self::assertIsString($registry);
        $fragmentCodes = array_keys(ConflictCheck::collectPermissions(['' => $doc]));
        self::assertSame($fragmentCodes, self::registryCodes($registry, 'sand_hello:'));

        // 整包过 kernel_api：构建产物与片段、代码三面已对齐
        $problems = FragmentCheck::check($pluginDir, self::hostContract());
        self::assertSame([], $problems, implode("\n", $problems));
    }

    public function testExtraGeneratedRouteFails(): void
    {
        $pluginDir = $this->buildFixtureRepo();
        file_put_contents(
            $pluginDir . '/config/route.generated.php',
            "Route::get('/api/admin/v1/plugin/sand-hello/rogue', [\\plugin\\sandhello\\app\\controller\\NoteController::class, 'index']);\n",
            FILE_APPEND,
        );

        $problems = FragmentCheck::check($pluginDir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('rogue', implode("\n", $problems));
    }

    public function testUnannotatedPermissionFails(): void
    {
        $pluginDir = $this->buildFixtureRepo();
        file_put_contents($pluginDir . '/app/controller/NoteController.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace plugin\sandhello\app\controller;

            final class NoteController
            {
                public function index(): void
                {
                }

                public function show(): void
                {
                }
            }
            PHP);

        $problems = FragmentCheck::check($pluginDir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('sand_hello:page:read', implode("\n", $problems));
    }

    public function testHandwrittenRouteShellFails(): void
    {
        $pluginDir = $this->copyFixtureToRepo('sand-hello');
        file_put_contents($pluginDir . '/config/route.php', <<<'PHP'
            <?php

            use Webman\Route;

            Route::get('/app/sandhello/notes', [\plugin\sandhello\app\controller\NoteController::class, 'index']);
            PHP);

        $problems = FragmentCheck::check($pluginDir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('Route::get', implode("\n", $problems));
    }

    public function testMissingHandlerFileFails(): void
    {
        $pluginDir = $this->copyFixtureToRepo('sand-hello');
        unlink($pluginDir . '/app/controller/NoteController.php');

        $problems = FragmentCheck::check($pluginDir, self::hostContract());
        self::assertNotEmpty($problems);
        self::assertStringContainsString('NoteController.php', implode("\n", $problems));
    }

    /** 迷你仓构建：宿主契约 + sand-hello 夹具，跑出真实构建产物，返回插件目录 */
    private function buildFixtureRepo(): string
    {
        $pluginDir = $this->copyFixtureToRepo('sand-hello');
        $report = ContractBuild::collectPlugins($this->repoDir);
        self::assertSame('ok', $report['result'], implode("\n", $report['warnings']));

        return $pluginDir;
    }

    /** 复制夹具到新建的临时仓 server/plugin/，返回插件目录；repoDir 由 tearDown 清 */
    private function copyFixtureToRepo(string $app): string
    {
        $this->makeRepo();
        $pluginDir = $this->repoDir . '/server/plugin/' . $app;
        self::copyTree(self::fixtureDir($app), $pluginDir);

        return $pluginDir;
    }

    private function makeRepo(): void
    {
        if ($this->repoDir !== '') {
            return;
        }
        $dir = sys_get_temp_dir() . '/contract-kernel-' . getmypid() . '-' . bin2hex(random_bytes(4));
        foreach (['contract', 'server/plugin'] as $sub) {
            if (!is_dir($dir . '/' . $sub) && !mkdir($dir . '/' . $sub, 0755, true)) {
                self::fail('无法创建临时仓目录 ' . $sub);
            }
        }
        if (!copy(self::hostContract(), $dir . '/contract/openapi.yaml')) {
            self::fail('无法复制宿主契约到临时仓');
        }
        $this->repoDir = $dir;
    }

    private static function copyTree(string $from, string $to): void
    {
        if (!is_dir($to) && !mkdir($to, 0755, true)) {
            self::fail('无法创建目录 ' . $to);
        }
        $items = scandir($from);
        if ($items === false) {
            self::fail('无法读取目录 ' . $from);
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . '/' . $item;
            $dst = $to . '/' . $item;
            if (is_dir($src)) {
                self::copyTree($src, $dst);
            } elseif (!copy($src, $dst)) {
                self::fail('无法复制 ' . $src);
            }
        }
    }

    private static function removeTree(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * @param ParsedDoc $doc
     * @return list<string> "METHOD /path" 升序清单
     */
    private static function docRoutes(array $doc): array
    {
        $routes = [];
        foreach ($doc['operations'] as $op) {
            $routes[] = $op['method'] . ' ' . $op['path'];
        }
        sort($routes);

        return $routes;
    }

    /** @return list<string> "METHOD /path" 升序清单 */
    private static function fileRoutes(string $tableFile): array
    {
        $content = file_get_contents($tableFile);
        self::assertIsString($content);
        $routes = [];
        if (preg_match_all(
            "/Route::(get|post|put|patch|delete|head|options)\\('([^']+)'/",
            $content,
            $matches,
            PREG_SET_ORDER,
        ) !== false) {
            foreach ($matches as $match) {
                $routes[] = strtoupper($match[1]) . ' ' . $match[2];
            }
        }
        sort($routes);

        return $routes;
    }

    /**
     * 从生成的 PermissionRegistry 文本取 CODES 里指定域前缀的码。
     * @return list<string> 升序清单
     */
    private static function registryCodes(string $registry, string $prefix): array
    {
        $codes = [];
        if (preg_match('/public const CODES = \[([^\]]*)\]/', $registry, $match) === 1
            && preg_match_all("/'([^']+)'/", $match[1], $literals) !== false) {
            foreach ($literals[1] as $code) {
                if (str_starts_with($code, $prefix)) {
                    $codes[] = $code;
                }
            }
        }
        sort($codes);

        return $codes;
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
