<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Contract;

use PHPUnit\Framework\Assert;
use Sandadmin\Contract\Build\ConflictCheck;
use Sandadmin\Contract\Build\ContractBuild;
use Sandadmin\Contract\Build\ContractDoc;

/**
 * 契约测试共用手写助手：解析契约、抽取生成路由表、单条注册断言。
 * 生成的骨架（tests/Contract/Generated/）只引用这里，不在生成物里堆逻辑。
 *
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class ContractAssertions
{
    public static function serverRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return ParsedDoc */
    public static function parseHostDoc(): array
    {
        return ContractDoc::parse(self::repoRoot() . '/contract/openapi.yaml', 'host');
    }

    /**
     * 重放构建期收集：宿主 + server/plugin 下全部片段，算 problems/conflicts。
     * @return array{problems: list<string>, conflicts: list<string>}
     */
    public static function collectAllDocs(): array
    {
        $docs = ['host' => self::parseHostDoc()];
        /** @var array<string, ParsedDoc> $docs */
        $problems = $docs['host']['problems'];
        $stems = [];

        $fragments = glob(self::serverRoot() . '/plugin/*/contract/openapi.yaml');
        if ($fragments === false) {
            $fragments = [];
        }
        sort($fragments);
        foreach ($fragments as $fragment) {
            $app = basename(dirname(dirname($fragment)));
            $doc = ContractDoc::parse($fragment, $app);
            $docs[$app] = $doc;
            $stems[$app] = ContractBuild::pluginStem($app);
            foreach ($doc['problems'] as $problem) {
                $problems[] = $problem;
            }
        }

        return [
            'problems' => $problems,
            'conflicts' => ConflictCheck::check($docs, $stems),
        ];
    }

    /**
     * 从 route.generated.php 抽取 Route::verb('path') 事实表。
     * @return array<string, true> "METHOD /path" => true
     */
    public static function listGeneratedRoutes(): array
    {
        $file = self::serverRoot() . '/config/route.generated.php';
        Assert::assertFileExists($file, 'route.generated.php 未生成，先跑 php tools/contract-build');
        $content = file_get_contents($file);
        Assert::assertNotFalse($content);

        $routes = [];
        if (preg_match_all("/Route::(get|post|put|patch|delete|head|options)\\('([^']+)'/", $content, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $routes[strtoupper($match[1]) . ' ' . $match[2]] = true;
            }
        }

        return $routes;
    }

    public static function assertRouteRegistered(string $method, string $path, string $operationId): void
    {
        $routes = self::listGeneratedRoutes();
        Assert::assertArrayHasKey(
            strtoupper($method) . ' ' . $path,
            $routes,
            "契约 {$operationId}（{$method} {$path}）不在 route.generated.php",
        );
    }
}
