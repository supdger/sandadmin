<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

/** 测试用打包助手：在临时目录现场打插件 zip，不依赖构建链。 */
final class PackageKit
{
    /**
     * 把夹具插件目录整体打进 zip 的 plugin/<app>/ 子树，
     * 另在 zip 根写 manifest.json（缺省读夹具自身的清单）。
     *
     * @param array<string, string> $entries 追加条目：zip 内路径 => 文本内容
     */
    public static function fixtureZip(string $file, string $app, ?string $manifest = null, array $entries = []): void
    {
        if ($manifest === null) {
            $raw = file_get_contents(self::fixtureDir($app) . '/manifest.json');
            if (!is_string($raw)) {
                throw new \RuntimeException('无法读取夹具清单');
            }
            $manifest = $raw;
        }
        $entries['manifest.json'] = $manifest;

        $zip = self::openZip($file);
        self::addTree($zip, self::fixtureDir($app), 'plugin/' . $app);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    /** @param array<string, string> $entries zip 内路径 => 文本内容 */
    public static function makeZip(string $file, array $entries): void
    {
        $zip = self::openZip($file);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    public static function fixtureDir(string $app): string
    {
        return self::repoRoot() . '/server/tests/Fixtures/plugin/' . $app;
    }

    public static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @param array<string, mixed>|null $overrides 覆盖字段；null 用夹具原清单 */
    public static function fixtureManifest(string $app, ?array $overrides = null): string
    {
        $raw = file_get_contents(self::fixtureDir($app) . '/manifest.json');
        if (!is_string($raw)) {
            throw new \RuntimeException('无法读取夹具清单');
        }
        if ($overrides === null) {
            return $raw;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('夹具清单不是合法 JSON');
        }
        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new \RuntimeException('无法编码覆盖后的清单');
        }

        return $encoded;
    }

    private static function openZip(string $file): \ZipArchive
    {
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建测试 zip：' . $file);
        }

        return $zip;
    }

    private static function addTree(\ZipArchive $zip, string $dir, string $prefix): void
    {
        $items = scandir($dir);
        if ($items === false) {
            throw new \RuntimeException('无法读取目录 ' . $dir);
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::addTree($zip, $path, $prefix . '/' . $item);
            } elseif (!$zip->addFile($path, $prefix . '/' . $item)) {
                throw new \RuntimeException('无法加入 zip 条目 ' . $item);
            }
        }
    }
}
