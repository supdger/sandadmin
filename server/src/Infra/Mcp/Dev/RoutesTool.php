<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;

/**
 * 开发工具：路由表 + 契约文件只读检查。
 * 路由由调用方注入的读取闭包提供（本类不直接碰 Webman 静态态，便于单测）；
 * 契约文件按白名单路径读，超限即截断，只进不出。
 */
final class RoutesTool implements ToolRunner
{
    /** @var \Closure(): list<array{methods: list<string>, path: string, callback: string}> */
    private readonly \Closure $routeReader;

    /**
     * @param callable(): list<array{methods: list<string>, path: string, callback: string}> $routeReader
     * @param list<string>                                                                 $contractPaths 允许读出的契约文件绝对路径白名单
     */
    public function __construct(
        callable $routeReader,
        private readonly array $contractPaths,
        private readonly int $maxFileBytes = 262144,
    ) {
        $this->routeReader = $routeReader(...);
    }

    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments, ?CallContext $context = null): ToolOutcome
    {
        return ToolOutcome::ok([
            'routes' => ($this->routeReader)(),
            'contract' => $this->contractFiles(),
        ]);
    }

    /** @return list<array{path: string, exists: bool, content?: string, truncated?: bool}> */
    private function contractFiles(): array
    {
        $files = [];
        foreach ($this->contractPaths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                $files[] = ['path' => $path, 'exists' => false];
                continue;
            }
            $size = filesize($path);
            $content = file_get_contents($path, false, null, 0, max(1, $this->maxFileBytes) + 1);
            if ($content === false) {
                $files[] = ['path' => $path, 'exists' => false];
                continue;
            }
            $item = ['path' => $path, 'exists' => true, 'content' => mb_substr($content, 0, $this->maxFileBytes)];
            if (is_int($size) && $size > $this->maxFileBytes) {
                $item['truncated'] = true;
            }
            $files[] = $item;
        }

        return $files;
    }
}
