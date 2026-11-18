<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Package;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Http\BizKernel;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Module\Package\PackageOps;

/**
 * 插件生命周期收尾时的契约收集：冲突则申请 failed 并回到原状态，
 * 合法片段则照常落地元数据与产物。仓库用临时目录拼装（宿主契约 +
 * 夹具片段），不碰真实 server/plugin。需要 SAND_TEST_DB_URL。
 */
final class PackageContractLifecycleTest extends TestCase
{
    private const BAD_FRAGMENT = <<<'YAML'
        openapi: 3.1.0
        info: {title: bad, version: "0.1"}
        x-error-catalog:
          NOT_FOUND: {http_status: 404, summary: 重复声明宿主已登记的码}
        paths:
          /api/admin/v1/plugin/sand-bad/things:
            get:
              operationId: sandBadThingsIndex
              summary: 坏例子
              x-handler: 'SandBad\Http\ThingController::index'
              x-permission: sand_bad:thing:index
              x-error-codes: [NOT_FOUND]
              responses: {'200': {description: ok}}
        YAML;

    private static Connection $conn;
    private static BizKernel $kernel;
    private OrgActor $actor;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::$kernel = BizKernel::fromConnection(self::$conn);
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::wipe();
            self::$conn->close();
        }
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->actor = new OrgActor(0, '契约测试号', null, true, ['*']);
    }

    public function testContractConflictFailsInstallAndRollsBack(): void
    {
        $repo = $this->makeRepo();
        try {
            $this->writeFragment($repo, 'sand-bad', self::BAD_FRAGMENT);
            $this->withPackageEnv('development', '0', '1', function () use ($repo): void {
                $owner = $this->ownerActor('13700088001', 'pkgconflict', '契约冲突人');
                $ops = new PackageOps(self::$conn, $repo);
                $accepted = $ops->install('flow', $owner, 'req-pkg-conflict');
                $this->assertSame(200, $accepted['status']);
                $requestId = $this->requestId($accepted);
                $this->advanceToEnd($ops, $requestId);

                $row = self::$conn->fetchAssociative(
                    'SELECT state, stage, rolled_back, error_code, error_message'
                    . ' FROM sand_system_lifecycle_request WHERE public_id = ?',
                    [$requestId],
                );
                $this->assertIsArray($row);
                $this->assertSame('failed', $row['state']);
                $this->assertSame('契约冲突', $row['stage']);
                $this->assertSame('PLUGIN_CONTRACT_CONFLICT', $row['error_code']);
                $message = OrgSql::str($row['error_message'] ?? '');
                $this->assertStringContainsString('sand-bad', $message);
                $this->assertStringContainsString('NOT_FOUND', $message);
                $rolledBack = $row['rolled_back'];
                $this->assertTrue($rolledBack === true || $rolledBack === 't' || $rolledBack === '1');
                // apply 没跑过：插件元数据一行都没有，临时仓也没落任何产物
                $this->assertSame(0, OrgSql::int(self::$conn->fetchOne(
                    "SELECT COUNT(*) FROM sand_system_plugin WHERE code = 'flow'",
                )));
                $this->assertFileDoesNotExist($repo . '/server/config/route.generated.php');
                $this->assertFileDoesNotExist($repo . '/server/config/plugin-routes.generated.json');
                $this->assertFileDoesNotExist($repo . '/server/plugin/sand-bad/config/route.generated.php');
            });
        } finally {
            self::removeTree($repo);
        }
    }

    public function testCleanFragmentInstallSucceedsAndWritesPluginRoutes(): void
    {
        $repo = $this->makeRepo();
        try {
            self::copyTree(self::fixtureDir('sand-hello'), $repo . '/server/plugin/sand-hello');
            $this->withPackageEnv('development', '0', '1', function () use ($repo): void {
                $owner = $this->ownerActor('13700088002', 'pkgcontract', '契约通过人');
                $ops = new PackageOps(self::$conn, $repo);
                $accepted = $ops->install('flow', $owner, 'req-pkg-contract-ok');
                $this->assertSame(200, $accepted['status']);
                $requestId = $this->requestId($accepted);
                $this->advanceToEnd($ops, $requestId);

                $this->assertSame('succeeded', self::$conn->fetchOne(
                    'SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?',
                    [$requestId],
                ));
                $this->assertSame('installed', self::$conn->fetchOne(
                    "SELECT state FROM sand_system_plugin WHERE code = 'flow'",
                ));
                $list = file_get_contents($repo . '/server/config/plugin-routes.generated.json');
                $this->assertIsString($list);
                $this->assertStringContainsString('plugin/sand-hello/config/route.php', $list);
                $this->assertFileExists($repo . '/server/plugin/sand-hello/config/route.generated.php');
            });
        } finally {
            self::removeTree($repo);
        }
    }

    /** @param array{status: int, body: array<string, mixed>} $response */
    private function requestId(array $response): string
    {
        $data = $response['body']['data'] ?? null;
        $this->assertIsArray($data);
        $id = $data['request_id'] ?? '';
        $this->assertIsString($id);
        $this->assertNotSame('', $id);

        return $id;
    }

    private function advanceToEnd(PackageOps $ops, string $requestId): void
    {
        for ($step = 0; $step < 10; $step++) {
            $ops->advance($requestId);
            $state = self::$conn->fetchOne(
                'SELECT state FROM sand_system_lifecycle_request WHERE public_id = ?',
                [$requestId],
            );
            if ($state === 'succeeded' || $state === 'failed') {
                return;
            }
        }
        $this->fail('生命周期没有走到终态');
    }

    private function ownerActor(string $phone, string $username, string $nickname): OrgActor
    {
        $dept = self::$kernel->depts->create(['name' => '契约测' . $username], $this->actor, 'req-' . $username . '-d', '127.0.0.1', null);
        $role = self::$kernel->roles->create(['name' => '契约测' . $username], $this->actor, 'req-' . $username . '-r', '127.0.0.1', null);
        $user = self::$kernel->users->create([
            'nickname' => $nickname,
            'phone' => $phone,
            'username' => $username,
            'password' => 'temp-pass-1',
            'dept_id' => $dept['id'],
            'role_ids' => [$role['id']],
        ], $this->actor, 'req-' . $username . '-u', '127.0.0.1', null);

        return new OrgActor(OrgSql::int($user['id']), $nickname, null, true, ['*']);
    }

    private function withPackageEnv(string $appEnv, string $compiled, string $ops, callable $work): void
    {
        $saved = [
            'APP_ENV' => getenv('APP_ENV'),
            'SAND_COMPILED' => getenv('SAND_COMPILED'),
            'SAND_PACKAGE_OPS' => getenv('SAND_PACKAGE_OPS'),
        ];
        putenv('APP_ENV=' . $appEnv);
        putenv('SAND_COMPILED=' . $compiled);
        putenv('SAND_PACKAGE_OPS=' . $ops);
        try {
            $work();
        } finally {
            foreach ($saved as $name => $value) {
                if (is_string($value) && $value !== '') {
                    putenv($name . '=' . $value);
                } else {
                    putenv($name);
                }
            }
        }
    }

    private function makeRepo(): string
    {
        $dir = sys_get_temp_dir() . '/contract-life-' . getmypid() . '-' . bin2hex(random_bytes(4));
        foreach (['contract', 'server/plugin'] as $sub) {
            if (!is_dir($dir . '/' . $sub) && !mkdir($dir . '/' . $sub, 0755, true)) {
                self::fail('无法创建临时仓目录 ' . $sub);
            }
        }
        if (!copy(self::repoRoot() . '/contract/openapi.yaml', $dir . '/contract/openapi.yaml')) {
            self::fail('无法复制宿主契约到临时仓');
        }

        return $dir;
    }

    private function writeFragment(string $repo, string $app, string $yaml): void
    {
        $dir = $repo . '/server/plugin/' . $app . '/contract';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            self::fail('无法创建插件片段目录');
        }
        file_put_contents($dir . '/openapi.yaml', $yaml);
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    private static function fixtureDir(string $app): string
    {
        return self::repoRoot() . '/server/tests/Fixtures/plugin/' . $app;
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

    private static function wipe(): void
    {
        self::$conn->executeStatement(
            "DELETE FROM sand_system_lifecycle_request WHERE requested_by IN"
            . " (SELECT id FROM sand_system_user WHERE phone LIKE '13700088%')",
        );
        self::$conn->executeStatement("DELETE FROM sand_system_plugin WHERE code = 'flow'");
        self::$conn->executeStatement("DELETE FROM sand_system_audit_log WHERE actor_name LIKE '契约%'");
        self::$conn->executeStatement("DELETE FROM sand_system_user WHERE phone LIKE '13700088%'");
        self::$conn->executeStatement("DELETE FROM sand_system_role WHERE name LIKE '契约测%'");
        self::$conn->executeStatement("DELETE FROM sand_system_dept WHERE name LIKE '契约测%'");
    }
}
