#!/usr/bin/env php
<?php

declare(strict_types=1);

// 发布包 vendor 非运行文件剔除：清掉第三方包里不参与运行的测试套件、
// 文档、示例、CI 元信息，范围只限 server/vendor/<org>/<pkg> 包内。
//   php tools/strip-vendor-extra.php <导出根目录> [--verify|--check-classmap]
// 在 composer install 之后、dump-autoload --optimize 之前执行；--verify 只报
// 残留不删除，--check-classmap 校验类映射条目全部指向存在的文件。
// 逐包解析 composer/installed.json 的 prod autoload，被覆盖的候选豁免并列出。

/** 目录名单：basename 精确命中即整棵剔除（大小写不敏感）。 */
const STRIP_DIR_NAMES = [
    'test', 'tests',
    'doc', 'docs',
    'example', 'examples',
    'demo', 'demos',
    'benchmark', 'benchmarks',
    'adr',
    '.github', '.circleci',
];

/**
 * 文件名单：basename 通配（大小写不敏感）。具名文档只收无扩展名与 .txt
 * 形态（.md/.rst 已由扩展名样式覆盖），不写 readme* 这种会误伤
 * upgrade.ts、support-label.ts 等源码文件的开口前缀。
 */
const STRIP_FILE_PATTERNS = [
    '*.md', '*.markdown', '*.rst',
    'changelog', 'changelog.txt', 'changes', 'changes.txt',
    'history', 'history.txt', 'news', 'news.txt',
    'upgrade', 'upgrade.txt', 'upgrading', 'upgrading.txt',
    'readme', 'readme.txt', 'contributing', 'contributing.txt',
    'code_of_conduct', 'code_of_conduct.txt', 'security', 'security.txt',
    'authors', 'authors.txt', 'maintainers', 'maintainers.txt',
    'backers', 'backers.txt', 'sponsors', 'sponsors.txt',
    'roadmap', 'roadmap.txt',
    'phpunit.xml*', 'phpstan*.neon*', 'psalm*.xml*', 'phpcs.xml*',
    'infection*.json*', 'phpbench*.json*', '.php-cs-fixer*',
    '.travis.yml', '.appveyor.yml', '.gitlab-ci.yml', '.scrutinizer.yml',
    '.styleci.yml', 'codecov.yml', '.editorconfig',
    '.gitignore', '.gitattributes', '.gitmodules',
    'makefile', 'dockerfile*', 'docker-compose*.yml',
    'test.php', 'test_*.php', '*_test.php',
];

/** 命中剔除样式但保留：许可证文本与 NOTICE 是制品再分发的附带条件。 */
const KEEP_FILE_PATTERNS = [
    'license*', 'licence*', 'notice*', 'copying*', 'copyright*',
];

/** 归一化相对/绝对路径：去掉 ./、收尾 / 并消化 .. 段。 */
function normalizePath(string $path): string
{
    $segments = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return (str_starts_with($path, '/') ? '/' : '') . implode('/', $segments);
}

/** $path 与 $dir 相等或位于其子树内。 */
function pathInside(string $path, string $dir): bool
{
    return $path === $dir || str_starts_with($path . '/', $dir . '/');
}

/**
 * 逐包解析 installed.json 的 prod autoload 覆盖路径。
 * whole 表示存在指向包根的 psr-4/psr-0 映射（整包子树都可能被按类名装载）。
 *
 * @return list<array{name:string,root:string,whole:bool,dirs:list<string>,files:list<string>}>
 */
function packageCoverage(string $vendorDir): array
{
    $installed = $vendorDir . '/composer/installed.json';
    $data = json_decode((string) file_get_contents($installed), true);
    if (!is_array($data)) {
        throw new RuntimeException("无法解析 {$installed}");
    }
    $packages = $data['packages'] ?? $data;
    if (!is_array($packages)) {
        throw new RuntimeException("{$installed} 缺少 packages 清单");
    }
    $out = [];
    foreach ($packages as $package) {
        if (!is_array($package) || !isset($package['install-path'])) {
            continue;
        }
        $root = normalizePath($vendorDir . '/composer/' . $package['install-path']);
        $autoload = is_array($package['autoload'] ?? null) ? $package['autoload'] : [];
        $whole = false;
        $dirs = [];
        $files = [];
        foreach (['psr-4', 'psr-0'] as $kind) {
            foreach ((array) ($autoload[$kind] ?? []) as $targets) {
                foreach ((array) $targets as $target) {
                    $rel = normalizePath((string) $target);
                    if ($rel === '') {
                        $whole = true;
                        continue;
                    }
                    $dirs[] = $root . '/' . $rel;
                }
            }
        }
        foreach ((array) ($autoload['classmap'] ?? []) as $target) {
            $full = $root . '/' . normalizePath((string) $target);
            if (is_dir($full)) {
                $dirs[] = $full;
            } else {
                $files[] = $full;
            }
        }
        foreach ((array) ($autoload['files'] ?? []) as $target) {
            $files[] = $root . '/' . normalizePath((string) $target);
        }
        $out[] = [
            'name' => (string) ($package['name'] ?? basename($root)),
            'root' => $root,
            'whole' => $whole,
            'dirs' => array_values(array_unique($dirs)),
            'files' => array_values(array_unique($files)),
        ];
    }

    return $out;
}

/**
 * 目录候选是否被 prod autoload 覆盖：与任一映射目录、classmap/files 条目
 * 存在包含或被包含关系时，删除它可能摘掉可装载类，按豁免处理。
 *
 * @param array{whole:bool,dirs:list<string>,files:list<string>} $pkg
 */
function isDirCovered(string $dir, array $pkg): bool
{
    if ($pkg['whole']) {
        return true;
    }
    foreach (array_merge($pkg['dirs'], $pkg['files']) as $covered) {
        if (pathInside($dir, $covered) || pathInside($covered, $dir)) {
            return true;
        }
    }

    return false;
}

/**
 * 文件候选是否被 prod autoload 覆盖：被 classmap/files 条目直接引用；
 * .php 文件落在 psr 映射目录内时可被按类名装载，同样豁免。非 .php 文件
 * 不可能是 autoload 目标，不受 psr 目录 containment 影响。
 *
 * @param array{whole:bool,dirs:list<string>,files:list<string>} $pkg
 */
function isFileCovered(string $file, array $pkg): bool
{
    foreach ($pkg['files'] as $covered) {
        if ($file === $covered) {
            return true;
        }
    }
    if (!str_ends_with(strtolower($file), '.php')) {
        return false;
    }
    if ($pkg['whole']) {
        return true;
    }
    foreach ($pkg['dirs'] as $covered) {
        if (pathInside($file, $covered)) {
            return true;
        }
    }

    return false;
}

function isStripDir(string $name): bool
{
    return in_array(strtolower($name), STRIP_DIR_NAMES, true);
}

function isStripFile(string $name): bool
{
    foreach (KEEP_FILE_PATTERNS as $pattern) {
        if (fnmatch($pattern, $name, FNM_CASEFOLD)) {
            return false;
        }
    }
    foreach (STRIP_FILE_PATTERNS as $pattern) {
        if (fnmatch($pattern, $name, FNM_CASEFOLD)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{root:string,whole:bool,dirs:list<string>,files:list<string>} $pkg
 * @param list<string> $files
 * @param list<string> $dirs
 * @param list<string> $exempt
 */
function walkPackage(string $dir, array $pkg, array &$files, array &$dirs, array &$exempt): void
{
    $names = scandir($dir);
    if ($names === false) {
        return;
    }
    foreach ($names as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path)) {
            if (isStripDir($name)) {
                if (isDirCovered($path, $pkg)) {
                    $exempt[] = $path;
                } else {
                    $dirs[] = $path;
                }
                continue;
            }
            walkPackage($path, $pkg, $files, $dirs, $exempt);
            continue;
        }
        if (isStripFile($name)) {
            if (isFileCovered($path, $pkg)) {
                $exempt[] = $path;
            } else {
                $files[] = $path;
            }
        }
    }
}

/**
 * @return array{files:list<string>,dirs:list<string>,exempt:list<string>}
 */
function collectExtraPaths(string $vendorDir): array
{
    $files = [];
    $dirs = [];
    $exempt = [];
    foreach (packageCoverage($vendorDir) as $pkg) {
        if (!is_dir($pkg['root'])) {
            continue;
        }
        walkPackage($pkg['root'], $pkg, $files, $dirs, $exempt);
    }

    return ['files' => $files, 'dirs' => $dirs, 'exempt' => $exempt];
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

/** 校验优化后的类映射：所有条目必须指向存在的文件。 */
function checkClassmap(string $vendorDir): int
{
    $autoload = $vendorDir . '/autoload.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, "FAIL autoload.php 不存在：{$autoload}\n");
        return 1;
    }
    $loader = require $autoload;
    if (!$loader instanceof Composer\Autoload\ClassLoader) {
        fwrite(STDERR, "FAIL autoload.php 未返回 ClassLoader\n");
        return 1;
    }
    $missing = [];
    foreach ($loader->getClassMap() as $class => $path) {
        if (!is_file($path)) {
            $missing[] = $class . ' => ' . $path;
        }
    }
    if ($missing !== []) {
        fwrite(STDERR, "FAIL 类映射指向已删除文件：\n  " . implode("\n  ", $missing) . "\n");
        return 1;
    }
    echo 'PASS 类映射 ' . count($loader->getClassMap()) . " 条全部指向存在的文件\n";
    return 0;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    $root = realpath((string) ($_SERVER['argv'][1] ?? ''));
    $verifyOnly = in_array('--verify', $_SERVER['argv'], true);
    $checkOnly = in_array('--check-classmap', $_SERVER['argv'], true);
    if ($root === false || !is_dir($root)) {
        fwrite(STDERR, "用法：php tools/strip-vendor-extra.php <导出根目录> [--verify|--check-classmap]\n");
        exit(2);
    }
    $vendorDir = $root . '/server/vendor';
    if ($checkOnly) {
        exit(checkClassmap($vendorDir));
    }
    try {
        $found = collectExtraPaths($vendorDir);
    } catch (RuntimeException $error) {
        fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
        exit(1);
    }
    if ($verifyOnly) {
        $left = array_merge($found['files'], $found['dirs']);
        if ($left !== []) {
            fwrite(STDERR, "FAIL vendor 非运行文件残留：\n  " . implode("\n  ", $left) . "\n");
            exit(1);
        }
        echo 'PASS vendor 无非运行文件残留（豁免 ' . count($found['exempt']) . " 项）\n";
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
    foreach ($found['exempt'] as $exemptPath) {
        // 被 prod autoload 覆盖的候选保留不删，逐条登记供审计
        echo '  exempt ' . substr($exemptPath, strlen($vendorDir) + 1) . "\n";
    }
    echo 'PASS vendor 非运行文件已剔除（' . $removed . ' 项，豁免 ' . count($found['exempt']) . " 项）\n";
    exit(0);
}
