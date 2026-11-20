#!/usr/bin/env php
<?php

declare(strict_types=1);

// 生产包免登录入口断言：扫发布包目录树（含 vendor 与 plugin 载荷，
// check-dev-refs.php 的 DEV_REF_SKIP 不适用这里——vendor 正是要查的面），
// 任何 dev 登录链符号残留即判失败。
//   php tools/check-no-dev-login.php --dir <发布包根>
// 由 tools/build-release.sh 自检段调用；符号表复用 check-dev-refs.php。

require_once __DIR__ . '/check-dev-refs.php';

/**
 * 产物内允许出现 dev 符号的路径（相对包根，前缀匹配），理由逐条写明。
 */
const DEV_LOGIN_WHITELIST = [
    // 生产启动自检兜底：class_exists 判定标记类存在与否，按设计保留在制品中
    'server/app/bootstrap/ProductionDebugGuard.php',
    // 发版说明对开发链剔除口径的书面描述
    'RELEASE-NOTES-0.2.4.md',
    // 发布验收清单登记免登录入口断言本身，属门禁说明文字
    'RELEASE-CHECKLIST.md',
    // 前端说明文档对 dev 链规则的书面描述
    'web/README.md',
    // TypePHP 整改盘点报告点名开发专用类作保留项说明，纯文档
    'tools/typephp-盘点.md',
    // 构建配置负责把 mockServiceWorker.js 从产物剔除，属构建工具逻辑
    'web/vite.config.ts',
    // lint 配置的忽略项点名 mock worker 产物文件
    'web/eslint.config.js',
];

/** tools/ 目录段整体跳过：构建与核查脚本不是运行入口（与既有 vendor 剔除 grep 同口径）。 */
function isToolsPath(string $path): bool
{
    return $path === 'tools' || str_starts_with($path, 'tools/') || str_contains($path, '/tools/');
}

function isPayloadWhitelisted(string $path): bool
{
    if (str_ends_with($path, '.sand-core-payload-manifest.json')) {
        return true;
    }
    if (matchAnyDevPath(DEV_LOGIN_WHITELIST, $path)) {
        return true;
    }

    return false;
}

function isBinaryFile(string $file): bool
{
    $head = file_get_contents($file, false, null, 0, 8192);
    return $head === false || str_contains($head, "\0");
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    $pos = array_search('--dir', $_SERVER['argv'], true);
    $dir = $pos === false ? null : ($_SERVER['argv'][$pos + 1] ?? null);
    if ($pos === false || $dir === null || str_starts_with($dir, '--')) {
        fwrite(STDERR, "用法：php tools/check-no-dev-login.php --dir <发布包根>\n");
        exit(2);
    }
    $base = realpath($dir);
    if ($base === false || !is_dir($base)) {
        fwrite(STDERR, "FAIL --dir 不存在：{$dir}\n");
        exit(2);
    }

    $violations = [];
    $scanned = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($base) + 1);
        if (isPayloadWhitelisted($rel) || isToolsPath($rel)) {
            continue;
        }
        $full = $file->getPathname();
        if (isBinaryFile($full)) {
            continue;
        }
        $content = file_get_contents($full);
        if ($content === false) {
            continue;
        }
        $scanned++;
        foreach (explode("\n", $content) as $number => $line) {
            foreach (DEV_REF_PATTERNS as $pattern) {
                if (str_contains($line, $pattern)) {
                    $violations[] = $rel . ':' . ($number + 1) . ' ' . $pattern;
                }
            }
        }
    }

    if ($violations !== []) {
        fwrite(STDERR, "FAIL 生产包残留免登录/dev 链符号：\n");
        foreach ($violations as $violation) {
            fwrite(STDERR, '  ' . $violation . "\n");
        }
        exit(1);
    }
    echo "PASS 生产包无免登录/dev 链符号残留（{$scanned} 个文件）\n";
    exit(0);
}
