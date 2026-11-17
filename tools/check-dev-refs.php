#!/usr/bin/env php
<?php

declare(strict_types=1);

// 开发专用符号引用门禁。
//   php tools/check-dev-refs.php            按 git ls-files 口径扫仓库源码
//   php tools/check-dev-refs.php --dir DIR  改扫文件系统目录（构建产物复检用，
//                                         DIR 需与仓库同构，即含 server/ web/ 等）
// 规则：清单 rm 条目整棵跳过；strip 条目按哨兵剔除后的内容再扫；
// 白名单文件跳过；其余文件出现 dev-only 符号即失败。

require_once __DIR__ . '/dev-manifest.php';

/** dev-only 符号表：类名、常量、路由路径、环境变量、前端标识。 */
const DEV_REF_PATTERNS = [
    'SAND_DEV_BUILD',
    'SAND_DEV_LOGIN',
    'SAND_MCP_DEV_TOKEN',
    'SANDADMIN_DEV_LOGIN',
    '.sand-dev-build',
    'DevLoginGate',
    'DevLoginController',
    'DevPrincipal',
    'DevTaskActorProvider',
    'DevAnnouncementActorProvider',
    'DevLifecycleActorProvider',
    'DevTokenVerifier',
    'DevBuild',
    'DevCatalog',
    'Infra\\Mcp\\Dev',
    'Infra/Mcp/Dev',
    'McpController',
    'McpGate',
    'WindowRateLimiter',
    'LogAuditSink',
    'dev-login',
    '/api/admin/mcp',
    'fetchDevLogin',
    'devLogin',
    'DevLoginAvailability',
    'devSurface',
    'VITE_SAND_DEV_LOGIN',
    'VITE_ENABLE_MOCKS',
    'VITE_MOCK_VIEWER_PASSWORD',
    'VITE_MOCK_GUEST_PASSWORD',
    '@/mocks',
    'src/mocks/',
    'mockServiceWorker',
    'SAND-DEV-BEGIN',
    'SAND-DEV-END',
];

/**
 * 显式豁免：这些文件允许出现 dev 符号，理由逐条写在注释里。
 * 路径语法与清单相同（精确文件或目录前缀）。
 */
const DEV_REF_WHITELIST = [
    // 生产启动自检兜底：class_exists 判定标记类存在与否，按设计保留在制品中
    'server/app/bootstrap/ProductionDebugGuard.php',
    // .gitattributes 注释需点名标记文件说明剔除口径归属
    '.gitattributes',
    // skill 与说明文档里对 dev 链规则的书面描述，不进入发布包或无代码语义
    '.agents',
    'CHANGELOG.md',
    'RELEASE-NOTES-0.2.4.md',
    'web/README.md',
    // TypePHP 整改盘点报告，逐条点名开发专用类作保留项说明，纯文档
    'tools/typephp-盘点.md',
    // 构建插件负责把 mockServiceWorker.js 从产物剔除，属构建工具逻辑
    'web/vite.config.ts',
    // lint 配置的忽略项点名 mock worker 产物文件
    'web/eslint.config.js',
];

/** 不扫的第三方/运行目录：vendor 与 Composer 安装到位的插件源码不是本仓源码。 */
const DEV_REF_SKIP = [
    'server/vendor',
    'server/plugin',
    'server/runtime',
    'node_modules',
];

/** @return list<string> 命中说明，形如 "<pattern>@<行号>" */
function findDevRefs(string $content): array
{
    $hits = [];
    $lines = explode("\n", $content);
    foreach ($lines as $number => $line) {
        foreach (DEV_REF_PATTERNS as $pattern) {
            if (str_contains($line, $pattern)) {
                $hits[] = $pattern . '@' . ($number + 1);
            }
        }
    }

    return $hits;
}

/**
 * 按清单与白名单口径检查一批文件。
 *
 * @param list<string>         $files     相对 root 的文件路径
 * @param array{rm:list<string>,strip:list<string>} $manifest
 * @param callable(string):?string $read  读文件内容，不可读返回 null
 * @param bool $requireSentinels strip 文件缺哨兵块是否算违规；
 *        源码口径为 true，已剔除过的产物复检为 false
 * @return list<string> 违规说明，形如 "<path>:<行号> <pattern>"
 */
function scanDevRefs(array $files, array $manifest, callable $read, bool $requireSentinels = true): array
{
    $violations = [];
    foreach ($files as $path) {
        if (matchAnyDevPath($manifest['rm'], $path)
            || matchAnyDevPath(DEV_REF_WHITELIST, $path)
            || matchAnyDevPath(DEV_REF_SKIP, $path)) {
            continue;
        }
        $content = $read($path);
        if ($content === null) {
            continue;
        }
        if (matchAnyDevPath($manifest['strip'], $path)) {
            try {
                $content = stripDevBlocks($content, $path, $requireSentinels);
            } catch (RuntimeException $error) {
                $violations[] = $path . ': ' . $error->getMessage();
                continue;
            }
        }
        foreach (findDevRefs($content) as $hit) {
            $violations[] = $path . ':' . $hit;
        }
    }

    return $violations;
}

/**
 * route.php 结构断言：哨兵必须成对；哨兵段之外只允许
 * Route::disableDefaultRoute()、SPA 回退 Route::fallback()（不是接口，不进契约）
 * 与对 route.generated.php 的 require——
 * 常驻路由全走契约生成表，杜绝有人在哨兵外回写手挂路由。
 * 原属 contract-build 构建器，挪回本门禁：dev 哨兵语义的归属地在这里，
 * 构建器不再感知哨兵（本脚本自身在 rm 清单里，token 命中不算违规）。
 * @return list<string> 违规说明
 */
function checkRouteShell(string $base): array
{
    $file = $base . '/server/config/route.php';
    if (!is_file($file)) {
        return [];
    }
    $content = file_get_contents($file);
    if ($content === false) {
        return ['server/config/route.php: 不可读'];
    }
    $violations = [];
    $begin = substr_count($content, '// SAND-DEV-BEGIN');
    $end = substr_count($content, '// SAND-DEV-END');
    if ($begin !== $end) {
        $violations[] = "server/config/route.php: 哨兵不成对（{$begin} vs {$end}）";
    }
    $stripped = preg_replace('/\/\/ SAND-DEV-BEGIN.*?\/\/ SAND-DEV-END/s', '', $content);
    $stripped = $stripped === null ? $content : $stripped;
    if (preg_match_all('/Route::([a-zA-Z]+)\s*\(/', $stripped, $matches) === false) {
        $matches = [[]];
    }
    foreach (($matches[1] ?? []) as $verb) {
        if (!in_array($verb, ['disableDefaultRoute', 'fallback'], true)) {
            $violations[] = "server/config/route.php: 哨兵段外存在手写 Route::{$verb} 调用，路由必须走 route.generated.php";
        }
    }
    if (!str_contains($stripped, 'route.generated.php')) {
        $violations[] = 'server/config/route.php: 没有 require route.generated.php';
    }

    return $violations;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    $root = dirname(__DIR__);
    $pos = array_search('--dir', $_SERVER['argv'], true);
    $dir = $pos === false ? null : ($_SERVER['argv'][$pos + 1] ?? null);
    if ($pos !== false && ($dir === null || str_starts_with($dir, '--'))) {
        fwrite(STDERR, "用法：php tools/check-dev-refs.php [--dir <目录>]\n");
        exit(2);
    }

    $files = [];
    $base = $root;
    if ($dir !== null) {
        $base = realpath($dir);
        if ($base === false || !is_dir($base)) {
            fwrite(STDERR, "FAIL --dir 不存在：{$dir}\n");
            exit(2);
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = substr($file->getPathname(), strlen($base) + 1);
            }
        }
    } else {
        $listed = [];
        exec('git -C ' . escapeshellarg($root) . ' ls-files', $listed, $code);
        if ($code !== 0) {
            fwrite(STDERR, "FAIL git ls-files 执行失败\n");
            exit(2);
        }
        $files = $listed;
    }
    sort($files);

    $manifest = parseDevManifest($root . '/tools/dev-only-paths.txt');
    $violations = scanDevRefs(
        $files,
        $manifest,
        requireSentinels: $dir === null,
        read: static function (string $path) use ($base): ?string {
            $full = $base . '/' . $path;
            if (!is_file($full)) {
                return null;
            }
            $content = file_get_contents($full);

            return $content === false ? null : $content;
        },
    );

    foreach (checkRouteShell($base) as $routeViolation) {
        $violations[] = $routeViolation;
    }

    if ($violations !== []) {
        fwrite(STDERR, "FAIL 发现未登记的 dev-only 引用：\n");
        foreach ($violations as $violation) {
            fwrite(STDERR, '  ' . $violation . "\n");
        }
        exit(1);
    }
    echo 'PASS 未发现未登记的 dev-only 引用（' . count($files) . " 个文件）\n";
    exit(0);
}
