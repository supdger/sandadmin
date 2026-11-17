#!/usr/bin/env php
<?php

declare(strict_types=1);

// 对发布产物里的 supdger/sand-core 剔除开发免登录链（/core/dev-login*）。
//   php tools/strip-sand-core.php <导出根目录>
// 清单套两处：vendor 安装树（原样）与 plugin/sandadmin 载荷（去前缀复剔）。
// 优先 vendor 自带清单，旧版没有则回落本仓镜像 dev-only-paths.sand-core.txt；
// 旧版源文件无哨兵，strip 无块可剔时由 stripCoreLegacy 按锚点等效剔除。
// 载荷清单哈希记的是发布源，剔除后卸载会把这几文件当宿主改动保留——已接受的取舍。

require_once __DIR__ . '/dev-manifest.php';
require_once __DIR__ . '/strip-dev.php';

/** 载荷副本用的清单：去掉 server/plugin/sandadmin/ 前缀，写到临时文件。 */
function deprefixedManifest(string $path, string $prefix): string
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("无法读取清单 {$path}");
    }
    $out = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            $out[] = $line;
            continue;
        }
        // 前缀之外的条目不属于载荷目录，直接丢弃而不是复剔到错误位置
        if (str_starts_with($trimmed, 'rm ' . $prefix) || str_starts_with($trimmed, 'strip ' . $prefix)) {
            $out[] = preg_replace('#^(rm|strip)\s+' . preg_quote($prefix, '#') . '#', '$1 ', $trimmed, 1);
        }
    }
    $copy = tempnam(sys_get_temp_dir(), 'sand-core-manifest-');
    if ($copy === false || file_put_contents($copy, implode("\n", $out) . "\n") === false) {
        throw new RuntimeException('无法创建临时清单文件');
    }

    return $copy;
}

/** 删除含子串的行；文件不存在或无命中返回 0。 */
function dropLinesContaining(string $file, string $needle): int
{
    if (!is_file($file)) {
        return 0;
    }
    $lines = explode("\n", (string) file_get_contents($file));
    $kept = [];
    $dropped = 0;
    foreach ($lines as $line) {
        if (str_contains($line, $needle)) {
            $dropped++;
            continue;
        }
        $kept[] = $line;
    }
    if ($dropped === 0) {
        return 0;
    }
    file_put_contents($file, implode("\n", $kept));

    return $dropped;
}

/** 删除 $anchor 所在方法连同紧邻的 docblock；无该方法返回 0。 */
function dropMethodBlock(string $file, string $anchor): int
{
    if (!is_file($file)) {
        return 0;
    }
    $lines = explode("\n", (string) file_get_contents($file));
    $start = -1;
    foreach ($lines as $i => $line) {
        if (str_contains($line, $anchor)) {
            $start = $i;
            break;
        }
    }
    if ($start < 0) {
        return 0;
    }
    $begin = $start;
    while ($begin > 0) {
        $prev = trim($lines[$begin - 1]);
        if ($prev === '*/') {
            $docStart = $begin - 1;
            while ($docStart >= 0 && !str_contains($lines[$docStart], '/**')) {
                $docStart--;
            }
            if ($docStart < 0) {
                throw new RuntimeException("{$file}: {$anchor} 上方的 docblock 找不到 /**");
            }
            $begin = $docStart;
            continue;
        }
        if (str_starts_with($prev, '//')) {
            $begin--;
            continue;
        }
        break;
    }
    // 类成员方法体的闭合是 4 空格缩进的 }；方法内部块缩进更深不会被误收
    $end = -1;
    for ($i = $start; $i < count($lines); $i++) {
        if ($lines[$i] === '    }') {
            $end = $i;
            break;
        }
    }
    if ($end < 0) {
        throw new RuntimeException("{$file}: {$anchor} 起始的方法找不到闭合");
    }
    array_splice($lines, $begin, $end - $begin + 1);
    file_put_contents($file, implode("\n", $lines));

    return $end - $begin + 1;
}

/** 删除 $anchor 起的配置数组块（含紧邻注释行），到首个 ], 闭合。 */
function dropConfigBlock(string $file, string $anchor): int
{
    if (!is_file($file)) {
        return 0;
    }
    $lines = explode("\n", (string) file_get_contents($file));
    $start = -1;
    foreach ($lines as $i => $line) {
        if (str_contains($line, $anchor)) {
            $start = $i;
            break;
        }
    }
    if ($start < 0) {
        return 0;
    }
    $begin = $start;
    while ($begin > 0 && str_starts_with(trim($lines[$begin - 1]), '//')) {
        $begin--;
    }
    $end = -1;
    for ($i = $start; $i < count($lines); $i++) {
        if (trim($lines[$i]) === '],') {
            $end = $i;
            break;
        }
    }
    if ($end < 0) {
        throw new RuntimeException("{$file}: {$anchor} 起始的数组块找不到 ],");
    }
    array_splice($lines, $begin, $end - $begin + 1);
    file_put_contents($file, implode("\n", $lines));

    return $end - $begin + 1;
}

/**
 * 旧版无哨兵的等效剔除，锚点对应清单三个 strip 文件；
 * 已带哨兵并被清单剔除的树里锚点缺席，各步自然返回 0。
 *
 * @return list<string>
 */
function stripCoreLegacy(string $pluginDir): array
{
    $done = [];
    $count = dropLinesContaining($pluginDir . '/config/route.php', 'dev-login');
    if ($count > 0) {
        $done[] = "config/route.php 剔除 {$count} 行";
    }
    $count = dropConfigBlock($pluginDir . '/config/sandadmin.php', "'dev_login'");
    if ($count > 0) {
        $done[] = "config/sandadmin.php 剔除 {$count} 行";
    }
    $logic = $pluginDir . '/app/logic/system/SystemUserLogic.php';
    $count = dropMethodBlock($logic, 'function devLogin(');
    $count += dropLinesContaining($logic, 'DevLoginGuard');
    if ($count > 0) {
        $done[] = "app/logic/system/SystemUserLogic.php 剔除 {$count} 行";
    }

    return $done;
}

/**
 * 树内 dev-login 符号残留；跳过顶层 tools/（上游剔除与门禁脚本自带符号）
 * 与载荷清单 .sand-core-payload-manifest.json（记录被剔除文件的登记名）。
 *
 * @return list<string> 残留的相对路径
 */
function residualDevRefs(string $dir): array
{
    $left = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($dir) + 1);
        if ($rel === '.sand-core-payload-manifest.json' || str_starts_with($rel, 'tools/')) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if ($content !== false && preg_match('/dev[-_]?login|DevLogin/i', $content) === 1) {
            $left[] = $rel;
        }
    }

    return $left;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    $root = realpath((string) ($_SERVER['argv'][1] ?? ''));
    if ($root === false || !is_dir($root)) {
        fwrite(STDERR, "用法：php tools/strip-sand-core.php <导出根目录>\n");
        exit(2);
    }
    $vendorDir = $root . '/server/vendor/supdger/sand-core';
    $payloadDir = $root . '/server/plugin/sandadmin';
    try {
        if (!is_dir($vendorDir)) {
            throw new RuntimeException("vendor sand-core 不存在：{$vendorDir}");
        }
        $manifestPath = $vendorDir . '/tools/dev-only-paths.txt';
        $source = 'vendor 自带';
        if (!is_file($manifestPath)) {
            $manifestPath = dirname(__DIR__) . '/tools/dev-only-paths.sand-core.txt';
            $source = '本仓镜像';
        }
        echo "  清单来源：{$source}\n";
        $manifest = parseDevManifest($manifestPath);

        // --reapply 容忍旧版条目漂移：rm 照删，strip 在无哨兵时无块可剔
        $done = applyDevManifest($vendorDir, $manifest, true);
        foreach ($done as $line) {
            echo '  vendor ' . $line . "\n";
        }

        if (is_dir($payloadDir)) {
            $copy = deprefixedManifest($manifestPath, 'server/plugin/sandadmin/');
            $done = applyDevManifest($payloadDir, parseDevManifest($copy), true);
            unlink($copy);
            foreach ($done as $line) {
                echo '  payload ' . $line . "\n";
            }
        }

        foreach ([$vendorDir . '/server/plugin/sandadmin', $payloadDir] as $pluginDir) {
            if (!is_dir($pluginDir)) {
                continue;
            }
            $label = $pluginDir === $payloadDir ? 'payload' : 'vendor';
            foreach (stripCoreLegacy($pluginDir) as $line) {
                echo "  legacy {$label} {$line}\n";
            }
        }

        foreach ([$vendorDir, $payloadDir] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $left = residualDevRefs($dir);
            if ($left !== []) {
                fwrite(STDERR, "FAIL {$dir} 仍有 dev-login 符号残留：\n");
                foreach ($left as $rel) {
                    fwrite(STDERR, '  ' . $rel . "\n");
                }
                exit(1);
            }
        }
        echo "PASS sand-core dev-login 链已剔除\n";
        exit(0);
    } catch (RuntimeException $error) {
        fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
        exit(1);
    }
}
