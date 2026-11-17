<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract;

/**
 * 启动期产物完整性校验（fail-closed）：
 * 复算当前契约源哈希，与每个产物头部记录的源哈希比对；
 * 改过契约没重跑构建、装了带契约的插件没重新收集，都会在这里拒启。
 * 只比头部哈希，不重新生成——启动路径不做写操作。
 */
final class GeneratedIntegrity
{
    /**
     * @param list<string>|null $artifactFiles 覆盖校验清单（测试用）；默认六类 PHP 产物
     * @throws \RuntimeException 不一致即抛，调用方负责拒启
     */
    public static function assert(string $serverRoot, ?array $artifactFiles = null): void
    {
        $repoRoot = dirname($serverRoot);
        $contractFile = $repoRoot . '/contract/openapi.yaml';
        $routeTable = $serverRoot . '/config/route.generated.php';

        if (!is_file($routeTable)) {
            if (is_file($contractFile)) {
                throw new \RuntimeException(
                    'server/config/route.generated.php 缺失，请先运行 php tools/contract-build',
                );
            }
            // 契约面整体缺席（极简部署形态）：没有可比对的源，放行；
            // route.php 自身缺 require 时由路由加载报错
            return;
        }
        if (!is_file($contractFile)) {
            throw new \RuntimeException(
                'contract/openapi.yaml 缺失，无法校验生成物一致性，拒绝启动',
            );
        }

        $expected = SourceHash::compute(SourceHash::sourcesOf($repoRoot, $serverRoot));

        $files = $artifactFiles ?? [
            $serverRoot . '/config/route.generated.php',
            $serverRoot . '/config/plugin-routes.generated.json',
            $serverRoot . '/src/Contract/Generated/PermissionRegistry.php',
            $serverRoot . '/src/Contract/Generated/McpToolRegistry.php',
            $serverRoot . '/src/Contract/Generated/ErrorCodeRegistry.php',
        ];
        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new \RuntimeException("契约产物缺失：{$file}，请运行 php tools/contract-build");
            }
            $content = file_get_contents($file);
            $hash = $content === false ? null : SourceHash::parseHeader($content);
            if ($hash === null) {
                throw new \RuntimeException("契约产物缺少源哈希头：{$file}");
            }
            if ($hash !== $expected) {
                throw new \RuntimeException(
                    "契约产物与源不一致：{$file}（产物头 sha256:{$hash}，当前源 sha256:{$expected}）。"
                    . '改了契约或装了带契约的插件后必须重跑 php tools/contract-build',
                );
            }
        }

        // 清单是纯数据 JSON：装载点不允许 require 变量路径（TypePHP 门禁）
        $listFile = $serverRoot . '/config/plugin-routes.generated.json';
        $manifestRaw = file_get_contents($listFile);
        $manifest = $manifestRaw === false ? null : json_decode($manifestRaw, true);
        $entries = is_array($manifest) ? ($manifest['routes'] ?? null) : null;
        if (!is_array($entries)) {
            throw new \RuntimeException('plugin-routes.generated.json 的 routes 不是 list 结构');
        }
        foreach ($entries as $entry) {
            if (!is_string($entry) || !is_file($serverRoot . '/' . $entry)) {
                throw new \RuntimeException(
                    '插件路由入口缺失：' . (is_string($entry) ? $entry : gettype($entry))
                    . '，请运行 php tools/contract-build 重新收集',
                );
            }
        }
    }
}
