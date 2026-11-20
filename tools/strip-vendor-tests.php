#!/usr/bin/env php
<?php

declare(strict_types=1);

// 发布包第三方测试文件剔除：清除 vendor 与 plugin 载荷里第三方包携带的
// 测试文件——*.test.* / *.spec.* 文件、__tests__ 目录、前端载荷内的 tests/ 目录。
//   php tools/strip-vendor-tests.php <导出根目录> [--verify]
// 剔除在 composer install 之后执行（vendor 此时才存在）；--verify 只报残留不删除。
// PHP 包的 tests/ 目录不在剔除面：只清「前端载荷」（子树含 .ts/.tsx/.vue
// 或带 package.json）内部的同名目录，避免误删宿主不需要、但规则未授权动的内容。

/**
 * 收集要剔除的路径：测试文件清单与目录清单（按删除顺序：先文件后目录）。
 *
 * @return array{files: list<string>, dirs: list<string>}
 */
function collectVendorTestPaths(string $root): array
{
    $files = [];
    $dirs = [];
    foreach (['server/vendor', 'server/plugin'] as $area) {
        $areaRoot = $root . '/' . $area;
        if (!is_dir($areaRoot)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($areaRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $path = $item->getPathname();
            if ($item->isDir()) {
                $name = $item->getFilename();
                if ($name === '__tests__'
                    || ($name === 'tests' && insideFrontendPayload($path, packageRootPath($areaRoot, $area, $path)))
                ) {
                    $dirs[] = $path;
                }
                continue;
            }
            if ($item->isFile() && isVendorTestFile($item->getFilename())) {
                $files[] = $path;
            }
        }
    }

    return ['files' => $files, 'dirs' => $dirs];
}

/** 命中第三方测试文件命名：*.test.*、*.spec.*（不限扩展名）。 */
function isVendorTestFile(string $name): bool
{
    return str_contains($name, '.test.') || str_contains($name, '.spec.');
}

/**
 * 待删目录所属的包根：vendor 下是 <org>/<pkg> 两层，plugin 下是 <name> 一层；
 * 更深处的一律按所在包收敛，不越出单个包。
 */
function packageRootPath(string $areaRoot, string $area, string $path): string
{
    $rel = substr($path, strlen($areaRoot) + 1);
    $depth = $area === 'server/vendor' ? 2 : 1;
    $segments = explode('/', $rel);

    return $areaRoot . '/' . implode('/', array_slice($segments, 0, $depth));
}

/**
 * tests 目录是否处在前端载荷内：从父目录向上到包根为止，某层祖先的子树
 * （排除待删目录自身）含 .ts/.tsx/.vue 文件，或祖先目录带 package.json。
 */
function insideFrontendPayload(string $dir, string $packageRoot): bool
{
    for ($parent = dirname($dir); str_starts_with($parent . '/', $packageRoot . '/'); $parent = dirname($parent)) {
        if (is_file($parent . '/package.json') || hasFrontendSource($parent, $dir)) {
            return true;
        }
    }

    return false;
}

/** 目录子树内（排除 $exclude 子树）是否存在前端源码文件。 */
function hasFrontendSource(string $dir, string $exclude): bool
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $item): bool => !str_starts_with($item->getPathname() . '/', $exclude . '/'),
        ),
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo && $item->isFile()
            && preg_match('/\.(ts|tsx|vue)$/', $item->getFilename()) === 1
        ) {
            return true;
        }
    }

    return false;
}

function removeTree(string $dir): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    $root = realpath((string) ($_SERVER['argv'][1] ?? ''));
    $verifyOnly = in_array('--verify', $_SERVER['argv'], true);
    if ($root === false || !is_dir($root)) {
        fwrite(STDERR, "用法：php tools/strip-vendor-tests.php <导出根目录> [--verify]\n");
        exit(2);
    }
    $found = collectVendorTestPaths($root);
    if ($verifyOnly) {
        $left = array_merge($found['files'], $found['dirs']);
        if ($left !== []) {
            fwrite(STDERR, "FAIL 第三方测试文件残留：\n  " . implode("\n  ", $left) . "\n");
            exit(1);
        }
        echo "PASS vendor/plugin 无第三方测试文件残留\n";
        exit(0);
    }
    $removed = 0;
    foreach ($found['files'] as $file) {
        // 命中文件可能已被更早删除的父目录带走
        if (is_file($file) && unlink($file)) {
            $removed++;
        }
    }
    foreach ($found['dirs'] as $dir) {
        if (is_dir($dir)) {
            removeTree($dir);
            $removed++;
        }
    }
    echo 'PASS 第三方测试文件已剔除（' . $removed . " 项）\n";
    exit(0);
}
