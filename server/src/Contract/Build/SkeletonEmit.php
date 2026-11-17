<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\Build;

use Sandadmin\Contract\SourceHash;

/**
 * 契约测试骨架发射：确定性断言（路由已注册、权限已声明、错误码已登记）
 * 每个 tag 一份文件始终真跑；成功路径逐 operation 发 markTestIncomplete，
 * 不许假绿。骨架落在 server/tests/Contract/Generated/，由 contract 套件跑。
 *
 * @phpstan-import-type Operation from ContractDoc
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class SkeletonEmit
{
    /**
     * @param array<string, ParsedDoc> $docs
     * @param list<string> $sourceLabels
     * @return array<string, string> 文件名 => 文件内容（相对 Generated/ 目录）
     */
    public static function emitAll(array $docs, array $sourceLabels, string $sourceHash): array
    {
        $files = [];
        $hostOps = $docs['host']['operations'] ?? [];

        $byTag = [];
        foreach ($hostOps as $op) {
            $tag = $op['tags'][0] ?? 'misc';
            $byTag[$tag][] = $op;
        }
        ksort($byTag);

        foreach ($byTag as $tag => $ops) {
            $class = self::tagClass($tag);
            $files[$class . 'ContractTest.php'] = self::emitTagTest($class, $ops, $sourceLabels, $sourceHash);
        }
        $files['ContractSurfaceTest.php'] = self::emitSurfaceTest($hostOps, $docs, $sourceLabels, $sourceHash);

        return $files;
    }

    /**
     * @param list<Operation> $ops
     * @param list<string> $sourceLabels
     */
    private static function emitTagTest(string $class, array $ops, array $sourceLabels, string $sourceHash): string
    {
        $permissionCodes = [];
        $errorCodes = [];
        $rows = [];
        foreach ($ops as $op) {
            $rows[] = "            [" . var_export($op['operation_id'], true) . ', '
                . var_export($op['method'], true) . ', ' . var_export($op['path'], true) . '],';
            foreach ($op['permission_codes'] as $code) {
                $permissionCodes[$code] = true;
            }
            foreach ($op['error_codes'] as $code) {
                $errorCodes[$code] = true;
            }
        }
        $permLines = [];
        foreach (array_keys($permissionCodes) as $code) {
            $permLines[] = '            ' . var_export($code, true) . ',';
        }
        $errLines = [];
        foreach (array_keys($errorCodes) as $code) {
            $errLines[] = '            ' . var_export($code, true) . ',';
        }

        $lines = self::fileHead($sourceLabels, $sourceHash);
        $lines[] = 'namespace Sandadmin\Tests\Contract\Generated;';
        $lines[] = '';
        $lines[] = 'use PHPUnit\Framework\Attributes\DataProvider;';
        $lines[] = 'use PHPUnit\Framework\TestCase;';
        $lines[] = 'use Sandadmin\Contract\Generated\ErrorCodeRegistry;';
        $lines[] = 'use Sandadmin\Contract\Generated\PermissionRegistry;';
        $lines[] = 'use Sandadmin\Tests\Contract\ContractAssertions;';
        $lines[] = '';
        $lines[] = '/** 机械断言：路由注册、权限声明、错误码登记必跑；成功路径不假绿。 */';
        $lines[] = "final class {$class}ContractTest extends TestCase";
        $lines[] = '{';
        $lines[] = '    /** @return list<array{0: string, 1: string, 2: string}> */';
        $lines[] = '    public static function operationProvider(): array';
        $lines[] = '    {';
        $lines[] = '        return [';
        $lines = array_merge($lines, $rows);
        $lines[] = '        ];';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = "    #[DataProvider('operationProvider')]";
        $lines[] = '    public function testRoutesRegistered(string $operationId, string $method, string $path): void';
        $lines[] = '    {';
        $lines[] = '        ContractAssertions::assertRouteRegistered($method, $path, $operationId);';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function testPermissionsDeclared(): void';
        $lines[] = '    {';
        $lines[] = '        $codes = [';
        $lines = array_merge($lines, $permLines);
        $lines[] = '        ];';
        $lines[] = '        // array_diff 断言在空清单时也产生断言（防 risky）';
        $lines[] = '        self::assertSame([], array_diff($codes, PermissionRegistry::CODES),';
        $lines[] = '            \'存在未进注册表的权限码\');';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function testErrorCodesRegistered(): void';
        $lines[] = '    {';
        $lines[] = '        $codes = [';
        $lines = array_merge($lines, $errLines);
        $lines[] = '        ];';
        $lines[] = '        self::assertSame([], array_diff($codes, ErrorCodeRegistry::ALL),';
        $lines[] = '            \'存在未进注册表的错误码\');';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = "    #[DataProvider('operationProvider')]";
        $lines[] = '    public function testSuccessPath(string $operationId, string $method, string $path): void';
        $lines[] = '    {';
        $lines[] = '        self::markTestIncomplete("TODO(契约测试): {$operationId} {$method} {$path} 成功路径待补夹具");';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<Operation> $hostOps
     * @param array<string, ParsedDoc> $docs
     * @param list<string> $sourceLabels
     */
    private static function emitSurfaceTest(array $hostOps, array $docs, array $sourceLabels, string $sourceHash): string
    {
        $routeRows = [];
        foreach ($hostOps as $op) {
            $routeRows[] = '            ' . var_export($op['method'] . ' ' . $op['path'], true) . ',';
        }
        $toolRows = [];
        $tools = ConflictCheck::collectTools($docs);
        foreach ($tools as $tool) {
            $toolRows[] = '            ' . var_export($tool['name'], true) . ',';
        }

        $lines = self::fileHead($sourceLabels, $sourceHash);
        $lines[] = 'namespace Sandadmin\Tests\Contract\Generated;';
        $lines[] = '';
        $lines[] = 'use PHPUnit\Framework\TestCase;';
        $lines[] = 'use Sandadmin\Contract\Build\ContractBuild;';
        $lines[] = 'use Sandadmin\Contract\Generated\McpToolRegistry;';
        $lines[] = 'use Sandadmin\Tests\Contract\ContractAssertions;';
        $lines[] = '';
        $lines[] = '/** 整表断言：契约可解析、零冲突、路由表完整、哨兵段完好、工具已登记。 */';
        $lines[] = 'final class ContractSurfaceTest extends TestCase';
        $lines[] = '{';
        $lines[] = '    public function testContractParsesClean(): void';
        $lines[] = '    {';
        $lines[] = '        $doc = ContractAssertions::parseHostDoc();';
        $lines[] = '        self::assertSame([], $doc[\'problems\'], implode("\n", $doc[\'problems\']));';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function testNoConflicts(): void';
        $lines[] = '    {';
        $lines[] = '        $result = ContractAssertions::collectAllDocs();';
        $lines[] = '        self::assertSame([], $result[\'problems\'], implode("\n", $result[\'problems\']));';
        $lines[] = '        self::assertSame([], $result[\'conflicts\'], implode("\n", $result[\'conflicts\']));';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function testRouteTableComplete(): void';
        $lines[] = '    {';
        $lines[] = '        $expected = [';
        $lines = array_merge($lines, $routeRows);
        $lines[] = '        ];';
        $lines[] = '        $actual = ContractAssertions::listGeneratedRoutes();';
        $lines[] = '        foreach ($expected as $route) {';
        $lines[] = '            self::assertArrayHasKey($route, $actual, "路由 {$route} 不在 route.generated.php");';
        $lines[] = '        }';
        $lines[] = '        self::assertCount(count($expected), $actual, \'route.generated.php 路由数与契约不一致\');';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function testRouteShellIntact(): void';
        $lines[] = '    {';
        $lines[] = '        self::assertSame([], ContractBuild::assertRouteShell(ContractAssertions::serverRoot()));';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function testMcpToolsDeclared(): void';
        $lines[] = '    {';
        $lines[] = '        $names = [];';
        $lines[] = '        foreach (McpToolRegistry::specs() as $spec) {';
        $lines[] = '            $names[] = $spec[\'name\'];';
        $lines[] = '        }';
        $lines[] = '        sort($names);';
        $lines[] = '        $expected = [';
        $lines = array_merge($lines, $toolRows);
        $lines[] = '        ];';
        $lines[] = '        sort($expected);';
        $lines[] = '        self::assertSame($expected, $names);';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $sourceLabels
     * @return list<string>
     */
    private static function fileHead(array $sourceLabels, string $sourceHash): array
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', ''];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';

        return $lines;
    }

    private static function tagClass(string $tag): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $tag);
        $name = '';
        foreach ($parts === false ? [] : $parts as $part) {
            if ($part !== '') {
                $name .= ucfirst(strtolower($part));
            }
        }

        return $name === '' ? 'Misc' : $name;
    }
}
