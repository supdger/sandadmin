<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract;

/**
 * 契约源哈希：构建期写进产物头部、启动期校验复算。
 * 算法（与契约设计第四节一致）：
 *   1. 对每个输入源文件算 sha256(原始字节)；
 *   2. 按仓内相对路径排序拼成「<相对路径>\t<文件sha256>」行；
 *   3. 末尾加一行「contract-build\tv1」（生成器版本行）；
 *   4. source_hash = sha256(上面那段文本的 UTF-8 字节)。
 */
final class SourceHash
{
    public const GENERATOR_LINE = "contract-build\tv1";

    /**
     * @param array<string, string> $sources 相对路径 => 绝对路径
     */
    public static function compute(array $sources): string
    {
        ksort($sources);
        $lines = [];
        foreach ($sources as $relative => $absolute) {
            $bytes = @file_get_contents($absolute);
            if ($bytes === false) {
                throw new \RuntimeException('契约源文件不可读：' . $absolute);
            }
            $lines[] = $relative . "\t" . hash('sha256', $bytes);
        }
        $lines[] = self::GENERATOR_LINE;

        return hash('sha256', implode("\n", $lines) . "\n");
    }

    /**
     * @param list<string> $sourceLabels 人读的来源说明（如「contract/openapi.yaml + 插件片段 2 份」）
     * @return list<string> 产物头部注释行（不含行注释符）
     */
    public static function headerLines(array $sourceLabels, string $sourceHash): array
    {
        $lines = [
            '<contract-generated> 本文件由 tools/contract-build 生成，请勿手改；',
            '改了会在契约一致性门禁（tools/contract-build --check）被打回。',
        ];
        foreach ($sourceLabels as $label) {
            $lines[] = '源: ' . $label;
        }
        $lines[] = '源哈希: sha256:' . $sourceHash;
        $lines[] = '生成器: contract-build v1';

        return $lines;
    }

    /**
     * 从产物文件头部解析「源哈希: sha256:<hex>」；取前 20 行内的第一条命中。
     */
    public static function parseHeader(string $content): ?string
    {
        $head = implode("\n", array_slice(explode("\n", $content), 0, 20));
        if (preg_match('/源哈希:\s*sha256:([0-9a-f]{64})/', $head, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * 宿主契约 + server/plugin 下全部片段，按相对路径排序的哈希输入表。
     * @return array<string, string> 相对路径 => 绝对路径
     */
    public static function sourcesOf(string $repoRoot, string $serverRoot): array
    {
        $sources = ['contract/openapi.yaml' => $repoRoot . '/contract/openapi.yaml'];
        $fragmentPattern = $serverRoot . '/plugin/*/contract/openapi.yaml';
        $fragments = glob($fragmentPattern);
        if ($fragments === false) {
            $fragments = [];
        }
        sort($fragments);
        foreach ($fragments as $fragment) {
            if (!is_file($fragment)) {
                continue;
            }
            $relative = 'server/' . substr($fragment, strlen($serverRoot) + 1);
            $sources[$relative] = $fragment;
        }

        return $sources;
    }
}
