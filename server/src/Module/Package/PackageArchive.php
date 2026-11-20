<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;

/**
 * 发行 zip 的只读检查与解包。完整性只靠 sha256，不做验签。
 * 检查全在内存里读目录项，不 extractTo：条目逐个过白名单后
 * 才写到目标目录，zip slip 与禁带内容到不了文件系统。
 */
final class PackageArchive
{
    /** 包内任何位置都不许出现的段名或文件名。 */
    private const BANNED_SEGMENTS = ['vendor', 'node_modules'];
    private const BANNED_FILES = ['install.sql', 'update.sql', 'uninstall.sql'];

    /**
     * migrations 判定口径（迁移执行器未实现前的过渡闸）：zip 任一条目路径含
     * migrations 目录段即拒（不限 payload 子树，口径零歧义），manifest.json
     * 显式声明非空 migrations 字段同样拒（空数组视为未声明）。都在内存目录项
     * 扫描阶段命中，早于 extract 零副作用；命中时 error_code 固定为
     * PLUGIN_MIGRATIONS_FORBIDDEN，其余问题为 null。
     *
     * @return array{manifest: ?PluginPackageManifest, payload: ?string, problems: list<string>, error_code: ?string}
     *         payload 是 zip 内插件目录前缀（plugin/<app>/ 或 server/）
     */
    public static function inspect(string $zipFile, ?string $expectedSha256): array
    {
        $empty = ['manifest' => null, 'payload' => null, 'problems' => [], 'error_code' => null];
        if (!is_file($zipFile)) {
            $empty['problems'][] = '安装包不存在：' . $zipFile;

            return $empty;
        }
        $problems = [];
        if ($expectedSha256 !== null) {
            $digest = hash_file('sha256', $zipFile);
            if (!is_string($digest) || !hash_equals(strtolower($expectedSha256), $digest)) {
                // 只说明完整性校验没过，不声称验签
                $problems[] = '安装包 sha256 校验未过';
            }
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::RDONLY) !== true) {
            return ['manifest' => null, 'payload' => null, 'problems' => array_merge($problems, ['安装包不是可读取的 zip 文件']), 'error_code' => null];
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || $name === '') {
                continue;
            }
            $names[] = str_replace('\\', '/', $name);
        }

        $errorCode = null;
        foreach ($names as $name) {
            $issue = self::entryProblem($name);
            if ($issue !== null) {
                $problems[] = $issue;
                if (self::isMigrationsEntry($name)) {
                    $errorCode = ErrorCodeRegistry::PLUGIN_MIGRATIONS_FORBIDDEN;
                }
            }
        }
        if ($problems !== []) {
            $zip->close();

            return ['manifest' => null, 'payload' => null, 'problems' => $problems, 'error_code' => $errorCode];
        }

        $rawManifest = $zip->getFromName('manifest.json');
        if (!is_string($rawManifest)) {
            $zip->close();

            return ['manifest' => null, 'payload' => null, 'problems' => ['zip 根缺少 manifest.json'], 'error_code' => null];
        }
        $parsed = PluginPackageManifest::parse($rawManifest);
        if ($parsed['manifest'] === null) {
            $zip->close();

            return ['manifest' => null, 'payload' => null, 'problems' => $parsed['problems'], 'error_code' => null];
        }
        $manifest = $parsed['manifest'];

        // manifest 显式声明 migrations 也是迁移内容：parse 不消费未知字段，这里单独看一眼
        $decodedManifest = json_decode($rawManifest, true);
        if (is_array($decodedManifest) && !empty($decodedManifest['migrations'])) {
            $problems[] = 'manifest.json 声明了 migrations 字段，迁移执行器未实现，暂不支持';
            $errorCode = ErrorCodeRegistry::PLUGIN_MIGRATIONS_FORBIDDEN;
        }

        $hasPluginTree = false;
        $hasServerTree = false;
        foreach ($names as $name) {
            if (str_starts_with($name, 'plugin/' . $manifest->app . '/')) {
                $hasPluginTree = true;
            }
            if (str_starts_with($name, 'server/')) {
                $hasServerTree = true;
            }
        }
        $payload = null;
        if ($hasPluginTree && $hasServerTree) {
            $problems[] = '安装包同时携带 plugin/' . $manifest->app . '/ 与 server/ 两种载荷布局，无法判定';
        } elseif ($hasPluginTree) {
            $payload = 'plugin/' . $manifest->app . '/';
        } elseif ($hasServerTree) {
            $payload = 'server/';
        } else {
            $problems[] = '安装包缺少插件载荷：既无 plugin/' . $manifest->app . '/ 也无 server/ 子树';
        }
        $zip->close();

        if ($problems !== []) {
            return ['manifest' => null, 'payload' => null, 'problems' => $problems, 'error_code' => $errorCode];
        }

        return ['manifest' => $manifest, 'payload' => $payload, 'problems' => [], 'error_code' => null];
    }

    /**
     * 把 payload 子树解到 $targetDir（相对路径逐个过白名单），
     * 并把 zip 根 manifest.json 写进 $targetDir/manifest.json。
     * 只在 inspect 通过后调用；IO 失败抛 RuntimeException。
     */
    public static function extract(
        string $zipFile,
        string $payloadPrefix,
        string $targetDir,
        PluginPackageManifest $manifest,
    ): void {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('无法打开安装包：' . $zipFile);
        }
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $zip->close();
            throw new \RuntimeException('无法创建暂存目录：' . $targetDir);
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name)) {
                    continue;
                }
                $name = str_replace('\\', '/', $name);
                if (!str_starts_with($name, $payloadPrefix)) {
                    continue;
                }
                if (self::entryProblem($name) !== null) {
                    throw new \RuntimeException('安装包含非法条目：' . $name);
                }
                $relative = substr($name, strlen($payloadPrefix));
                if ($relative === '') {
                    continue;
                }
                $dest = $targetDir . '/' . $relative;
                if (str_ends_with($name, '/')) {
                    if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
                        throw new \RuntimeException('无法创建目录：' . $dest);
                    }
                    continue;
                }
                self::writeEntry($zip, $name, $dest);
            }
            $rawManifest = $zip->getFromName('manifest.json');
            if (!is_string($rawManifest)) {
                throw new \RuntimeException('zip 根缺少 manifest.json');
            }
            // 根清单覆盖 payload 内同名文件，落位后的声明以 zip 根为准
            if (file_put_contents($targetDir . '/manifest.json', $rawManifest) === false) {
                throw new \RuntimeException('无法写入 manifest.json 到 ' . $targetDir);
            }
        } finally {
            $zip->close();
        }
    }

    /** 条目路径是否踩红线；返回问题描述或 null。 */
    private static function entryProblem(string $name): ?string
    {
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name) === 1) {
            return 'zip 条目是绝对路径：' . $name;
        }
        $segments = explode('/', $name);
        $isDirectory = str_ends_with($name, '/');
        $base = $isDirectory ? ($segments[count($segments) - 2] ?? '') : $segments[count($segments) - 1];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return 'zip 条目含 .. 段：' . $name;
            }
            if (in_array($segment, self::BANNED_SEGMENTS, true)) {
                return 'zip 条目含禁带目录 ' . $segment . '/：' . $name;
            }
            // migrations 段单独成条：拒因不是「禁带文件」而是「执行器未实现」，
            // inspect 据此回填 error_code，文案要让安装方看懂
            if ($segment === 'migrations') {
                return 'zip 条目含 migrations 迁移目录，迁移执行器未实现，暂不支持：' . $name;
            }
        }
        if (in_array($base, self::BANNED_FILES, true)) {
            return 'zip 条目含禁带迁移脚本：' . $name;
        }
        if (preg_match('/^\.env(\..*)?$/', $base) === 1) {
            return 'zip 条目含环境配置：' . $name;
        }
        if (str_ends_with($base, '.phar') || str_ends_with($base, '.so')) {
            return 'zip 条目含编译产物：' . $name;
        }

        return null;
    }

    /** 条目路径是否含 migrations 目录段（目录项自身也算）。 */
    private static function isMigrationsEntry(string $name): bool
    {
        foreach (explode('/', $name) as $segment) {
            if ($segment === 'migrations') {
                return true;
            }
        }

        return false;
    }

    private static function writeEntry(\ZipArchive $zip, string $name, string $dest): void
    {
        $parent = dirname($dest);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new \RuntimeException('无法创建目录：' . $parent);
        }
        $source = $zip->getStream($name);
        if ($source === false) {
            throw new \RuntimeException('无法读取 zip 条目：' . $name);
        }
        $target = fopen($dest, 'wb');
        if ($target === false) {
            fclose($source);
            throw new \RuntimeException('无法写入文件：' . $dest);
        }
        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);
    }
}
