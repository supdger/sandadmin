#!/usr/bin/env php
<?php

declare(strict_types=1);

// 路由权限静态门禁（HR4-a / M-07）：契约声明、生成路由表与 handler 权限判定三方对齐。
//   php tools/check-route-permission.php
// 判定：契约 operation 必须有合法 x-permission，生成路由必须对回契约，
// handler 可达代码须存在对应权限判定（authenticated/super/权限码/public 分档）；
// 生产路由表与插件路由入口不得出现 dev 路由，route.php 哨兵段必须包在
// DevBuild::enabled() 内。退出码：0 通过，1 有违规，2 用法/环境错误。

require __DIR__ . '/../server/vendor/autoload.php';
require_once __DIR__ . '/dev-manifest.php';
// 复用 check-dev-refs.php 的符号表与 route.php 结构断言；其 CLI 主流程有
// argv[0] 自检，被 require 时不会执行。
require_once __DIR__ . '/check-dev-refs.php';

use Sandadmin\Contract\Build\ContractDoc;

const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

/** 宿主 dev 路径字面量：任何生产路由文件出现即违规。 */
const HOST_DEV_PATHS = [
    '/api/auth/dev-login',
    '/api/admin/v1/auth/dev-login',
    '/api/admin/mcp',
    '/api/dev/',
];

/** 插件路由文件里视为 dev 登记的路径特征。 */
const PLUGIN_DEV_PATH_PATTERN = '/dev-login|\/dev\/|dev_|_dev|\/api\/admin\/mcp/i';

/**
 * 权限码类（single/any_of/all_of）要求 handler 可达代码里出现授权判定。
 * 口径是「存在判定调用」而非「判定正确」：can/requirePermission/permit 走权限码，
 * visibleTo/assertDownloadable/guard 覆盖属主与装配层判定。
 */
const PERMISSION_EVIDENCE = [
    '/->can\s*\(/',
    '/\bcanAny\s*\(/',
    '/requirePermission\s*\(/',
    '/\bpermit\s*\(/',
    '/\bvisible(?:To)?\s*\(/',
    '/assertDownloadable\s*\(/',
    '/assertPermission\w*\s*\(/',
    '/assertOwner\w*\s*\(/',
    '/Access::allowed\s*\(/',
    '/->guard\s*\(/',
    '/\bdenied\s*\(/',
    '/\bisSuper\b/',
    '/\bviewAll\b/',
];

/** authenticated 只要求登录态判定存在。 */
const IDENTITY_EVIDENCE = [
    '/principal\s*->\s*resolve/',
    '/->resolve\s*\(\s*\$request/',
    '/->actor\s*\(\s*\$request/',
    '/::unauthenticated\s*\(/',
    '/\bUNAUTHENTICATED\b/',
    '/requireActor\s*\(/',
    '/requireAuth\w*\s*\(/',
    '/CheckLogin\b/',
    '/\$actor\s*===\s*null/',
];

/** super = 超管判定，或落到权限码判定。 */
const SUPER_EVIDENCE = [
    '/\bisSuper\b/',
    '/assertSuper\w*\s*\(/',
    '/SUPER_ADMIN\b/',
];

/** public 路由不挂会话鉴权，必须自带等价门禁证据（回环/令牌/显式拒绝）。 */
const PUBLIC_GUARD_EVIDENCE = [
    '/Response\(404/',
    '/Response\(403/',
    '/UpdateChallenge/',
    '/DevBuild::enabled/',
    '/getRemoteIp\s*\(/',
    '/isLoopback/',
    '/FORWARD/',
    '/TOKEN/',
    '/Challenge/',
    '/challenge/',
    '/deny\s*\(/',
    '/forbidden/i',
    '/unauthorized/i',
];

$root = dirname(__DIR__);
$serverDir = $root . '/server';
$violations = [];

// ── 1. 契约 operations：x-permission 合法性 + handler 权限判定证据 ──

$doc = ContractDoc::parse($root . '/contract/openapi.yaml', 'host');
foreach ($doc['problems'] as $problem) {
    $violations[] = "contract: {$problem}";
}

/** @var array<string, array<string, mixed>> $ops method+空格+path => operation */
$ops = [];
foreach ($doc['operations'] as $op) {
    /** @var array<string, mixed> $op */
    $ops[strtolower((string) $op['method']) . ' ' . (string) $op['path']] = $op;
}

$classIndex = buildClassIndex($serverDir . '/src', $serverDir . '/app');
$routeFiles = [
    'server/config/route.generated.php' => $serverDir . '/config/route.generated.php',
];
foreach (glob($serverDir . '/plugin/*/config/route.generated.php') ?: [] as $generated) {
    $routeFiles['server/' . substr($generated, strlen($serverDir . '/'))] = $generated;
}

// 生成路由表 → 契约 operation 对账
$generatedRoutes = [];
foreach ($routeFiles as $rel => $file) {
    $content = is_file($file) ? (string) file_get_contents($file) : '';
    if ($content === '') {
        $violations[] = "{$rel}: 生成路由文件缺失或为空";
        continue;
    }
    foreach (findDevRefs($content) as $hit) {
        $violations[] = "{$rel}: dev-only 符号出现在生产路由表 {$hit}";
    }
    foreach (HOST_DEV_PATHS as $devPath) {
        if (str_contains($content, $devPath)) {
            $violations[] = "{$rel}: 出现宿主 dev 路径 {$devPath}";
        }
    }
    $fileUses = parseUseLines($content);
    $fileNs = '';
    if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $nm) === 1) {
        $fileNs = $nm[1];
    }
    if (preg_match_all(
        '/Route::(' . implode('|', ROUTE_METHODS) . ")\s*\(\s*'([^']+)'\s*,\s*\[([^\]]+)\]/",
        $content,
        $matches,
        PREG_SET_ORDER,
    ) === false) {
        $matches = [];
    }
    foreach ($matches as $match) {
        $verb = strtolower($match[1]);
        $path = $match[2];
        $handlerRef = $match[3];
        $className = '';
        $methodName = '';
        if (preg_match('/([A-Za-z_\\\\][\w\\\\]*)::class/', $handlerRef, $cm) === 1) {
            $className = resolveWrittenName($cm[1], $fileNs, $fileUses, $classIndex);
        }
        if (preg_match("/'(\\w+)'\\s*$/", $handlerRef, $mm) === 1) {
            $methodName = $mm[1];
        }
        $key = $verb . ' ' . $path;
        $generatedRoutes[$key] = ['class' => $className, 'method' => $methodName, 'file' => $rel];
        $op = $ops[$key] ?? null;
        if ($op === null) {
            $violations[] = "{$rel}: {$verb} {$path} 在契约中没有对应 operation";
            continue;
        }
        $expected = ltrim((string) $op['handler'], '\\');
        $routeHandler = $className === '' ? '' : $className . '::' . $methodName;
        if ($expected !== '' && $routeHandler !== '' && strcasecmp($expected, $routeHandler) !== 0) {
            $violations[] = "{$rel}: {$verb} {$path} handler 不一致：契约 {$expected}，路由 {$routeHandler}";
        }
    }
}

// 契约 operation → x-permission 与 handler 证据
$opCount = 0;
foreach ($doc['operations'] as $op) {
    /** @var array<string, mixed> $op */
    $opCount++;
    $label = strtolower((string) $op['method']) . ' ' . (string) $op['path']
        . '（' . (string) ($op['operation_id'] ?? '') . '）';
    $kind = (string) ($op['permission_kind'] ?? '');
    $handler = (string) ($op['handler'] ?? '');
    if ($kind === '' || $kind === 'invalid') {
        $violations[] = "契约 {$label}: x-permission 缺失或形状非法";
        continue;
    }
    if ($handler === '' || preg_match('/^[A-Za-z_\\\\][\w\\\\]*::\w+$/', $handler) !== 1) {
        $violations[] = "契约 {$label}: x-handler 缺失或形状非法（{$handler}）";
        continue;
    }
    [$className, $methodName] = explode('::', $handler, 2);
    $className = ltrim($className, '\\');
    $key = strtolower((string) $op['method']) . ' ' . (string) $op['path'];
    if (!isset($generatedRoutes[$key])) {
        $violations[] = "契约 {$label}: 生成路由表中找不到对应 Route::{$op['method']} {$op['path']}";
    }

    $evidence = collectHandlerEvidence($classIndex, $className, $methodName);
    if ($evidence === null) {
        $violations[] = "契约 {$label}: handler {$handler} 源文件或方法不存在，无法核对权限判定";
        continue;
    }
    $patterns = match ($kind) {
        'public' => PUBLIC_GUARD_EVIDENCE,
        'authenticated' => IDENTITY_EVIDENCE,
        'super' => array_merge(PERMISSION_EVIDENCE, SUPER_EVIDENCE),
        default => PERMISSION_EVIDENCE,
    };
    $kindLabel = match ($kind) {
        'public' => 'public（须自带门禁证据）',
        'authenticated' => 'authenticated（须登录态判定）',
        'super' => 'super（须超管判定）',
        default => "权限码 {$kind}（须授权判定）",
    };
    $hit = false;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $evidence) === 1) {
            $hit = true;
            break;
        }
    }
    if (!$hit) {
        $violations[] = "契约 {$label}: x-permission={$kindLabel}，但 handler {$handler} 可达代码中未见对应判定调用";
    }
}

// ── 2. route.php 哨兵段：dev 路由必须整体位于 DevBuild::enabled() 内 ──

$routePhp = $serverDir . '/config/route.php';
if (is_file($routePhp)) {
    $content = (string) file_get_contents($routePhp);
    if (preg_match_all('/\/\/ SAND-DEV-BEGIN(.*?)\/\/ SAND-DEV-END/s', $content, $segments) !== false) {
        foreach ($segments[1] as $index => $segment) {
            if (preg_match_all(
                '/Route::(?:' . implode('|', ROUTE_METHODS) . ')\s*\(/',
                (string) $segment,
                $routeCalls,
                PREG_OFFSET_CAPTURE,
            ) === 0 || $routeCalls[0] === []) {
                continue;
            }
            // DevBuild::enabled() 通常写作 if (DevBuild::enabled()) {，条件右括号与块括号相邻
            if (preg_match('/DevBuild::enabled\s*\([^)]*\)/', (string) $segment, $gate, PREG_OFFSET_CAPTURE) !== 1) {
                $violations[] = 'server/config/route.php: 哨兵段含 Route:: 但没有 DevBuild::enabled() 包裹';
                continue;
            }
            $bracePos = strpos((string) $segment, '{', (int) $gate[0][1] + strlen((string) $gate[0][0]));
            if ($bracePos === false) {
                $violations[] = 'server/config/route.php: DevBuild::enabled() 后找不到代码块';
                continue;
            }
            $blockStart = $bracePos + 1;
            $blockEnd = matchBraceEnd((string) $segment, $bracePos);
            if ($blockEnd === null) {
                $violations[] = 'server/config/route.php: DevBuild::enabled() 代码块括号不配对';
                continue;
            }
            foreach ($routeCalls[0] as $call) {
                $pos = (int) $call[1];
                if ($pos < $blockStart || $pos > $blockEnd) {
                    $violations[] = 'server/config/route.php: 哨兵段内 Route:: 位于 DevBuild::enabled() 块外';
                    break;
                }
            }
        }
    }
}
foreach (checkRouteShell($root) as $routeViolation) {
    $violations[] = $routeViolation;
}

// ── 3. 插件路由入口：plugin-routes.generated.json 登记一致 + dev 路径口径 ──

$pluginManifest = parseDevManifest($root . '/tools/dev-only-paths.sand-core.txt');
$routesJson = $serverDir . '/config/plugin-routes.generated.json';
if (!is_file($routesJson)) {
    $violations[] = 'server/config/plugin-routes.generated.json 缺失';
} else {
    $decoded = json_decode((string) file_get_contents($routesJson), true);
    $pluginRoutes = is_array($decoded) && is_array($decoded['routes'] ?? null) ? $decoded['routes'] : [];
    foreach ($pluginRoutes as $rel) {
        if (!is_string($rel)) {
            continue;
        }
        $file = $serverDir . '/' . $rel;
        if (!is_file($file)) {
            $violations[] = "插件路由清单登记的文件不存在：{$rel}";
            continue;
        }
        $content = (string) file_get_contents($file);
        foreach (HOST_DEV_PATHS as $devPath) {
            if (str_contains($content, $devPath)) {
                $violations[] = "{$rel}: 插件路由注册了宿主 dev 路径 {$devPath}";
            }
        }
        // 文件级特征：dev 路径字面量或 dev 类引用都算 dev 路由面
        $hasDevRoute = preg_match(PLUGIN_DEV_PATH_PATTERN, $content) === 1
            || findDevRefs($content) !== [];
        // 清单条目带 server/ 前缀（相对仓库根），rel 是相对 server/ 的
        $manifestPath = 'server/' . $rel;
        if ($hasDevRoute && !matchAnyDevPath($pluginManifest['rm'], $manifestPath)
            && !matchAnyDevPath($pluginManifest['strip'], $manifestPath)) {
            $violations[] = "{$rel}: 含 dev 路由但未登记 dev-only-paths.sand-core.txt 剔除清单";
        }
    }
}

// ── 输出 ──

echo '路由权限门禁：契约 operation ' . $opCount . ' 条，生成路由 ' . count($generatedRoutes) . " 条\n";
if ($violations === []) {
    echo "全部路由已声明 x-permission 且 handler 存在对应判定，无 dev 路由泄漏\n";
    exit(0);
}
foreach ($violations as $violation) {
    echo 'FAIL ' . $violation . "\n";
}
echo '共 ' . count($violations) . " 处违规\n";
exit(1);

// ── PHP 源码索引与可达调用分析 ──

/**
 * 扫描 src/app 下 PHP 文件建立「类 → 方法体」索引。
 *
 * @return array{classes: array<string, array{file: string, methods: array<string, array{body: string, return: ?string, params: array<string, string>}>}>}
 */
function buildClassIndex(string ...$dirs): array
{
    $classes = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            foreach (indexPhpFile($file->getPathname()) as $fqcn => $info) {
                $classes[$fqcn] = $info;
            }
        }
    }
    ksort($classes);

    return ['classes' => $classes];
}

/**
 * 解析单个 PHP 文件：命名空间、use 导入、类（含接口/trait/enum）与方法体。
 * 方法体按 token_get_all 括号配对截取，字符串/注释里的括号不干扰。
 *
 * @return array<string, array{file: string, methods: array<string, array{body: string, return: ?string, params: array<string, string>}>}>
 */
function indexPhpFile(string $path): array
{
    $code = file_get_contents($path);
    if ($code === false) {
        return [];
    }
    $tokens = token_get_all($code);
    $count = count($tokens);
    $offsets = [];
    $pos = 0;
    foreach ($tokens as $index => $token) {
        $offsets[$index] = $pos;
        $pos += strlen(is_array($token) ? $token[1] : $token);
    }

    $namespace = '';
    $uses = [];
    $result = [];

    // 第一遍：namespace 与顶层 use（类体之前）
    $depth = 0;
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;
        if ($text === '{') {
            $depth++;
            continue;
        }
        if ($text === '}') {
            $depth--;
            continue;
        }
        if ($depth !== 0) {
            continue;
        }
        if ($id === T_NAMESPACE) {
            $name = '';
            for ($j = $i + 1; $j < $count; $j++) {
                $t = $tokens[$j];
                if ($t === ';' || (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
                    if ($t === ';') {
                        break;
                    }
                    continue;
                }
                if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $name .= $t[1];
                }
            }
            $namespace = $name;
            continue;
        }
        if ($id === T_USE) {
            // 闭包 use 形如 use (...)，import 形如 use A\B [as C];
            $j = $i + 1;
            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if ($j < $count && $tokens[$j] === '(') {
                continue;
            }
            // use function / use const 是符号导入不是类导入
            if ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_FUNCTION, T_CONST], true)) {
                continue;
            }
            $statement = '';
            for (; $j < $count; $j++) {
                $t = $tokens[$j];
                if ($t === ';') {
                    break;
                }
                if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR, T_AS, T_WHITESPACE], true)) {
                    $statement .= $t[1];
                }
            }
            if (preg_match('/^\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?$/i', trim($statement), $um) === 1) {
                $fqcn = ltrim($um[1], '\\');
                $alias = isset($um[2]) && $um[2] !== ''
                    ? strtolower($um[2])
                    : strtolower(substr($fqcn, (int) strrpos('\\' . $fqcn, '\\')));
                $uses[$alias] = $fqcn;
            }
            continue;
        }
        if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, defined('T_ENUM') ? T_ENUM : -1], true)) {
            break;
        }
    }

    $fileCtx = ['ns' => $namespace, 'uses' => $uses];

    // 第二遍：类与方法
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        $id = is_array($token) ? $token[0] : null;
        if (!is_array($token) || !in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, defined('T_ENUM') ? T_ENUM : -1], true)) {
            continue;
        }
        // 跳过 ::class、->class、new class（匿名类）
        $prev = $i - 1;
        while ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_WHITESPACE, T_COMMENT], true)) {
            $prev--;
        }
        if ($prev >= 0 && is_array($tokens[$prev])
            && in_array($tokens[$prev][0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NEW], true)) {
            continue;
        }
        // 类名
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            continue; // 匿名类
        }
        $shortName = $tokens[$j][1];
        $fqcn = $namespace === '' ? $shortName : $namespace . '\\' . $shortName;

        // 找类体 {
        $bodyOpen = null;
        for ($k = $j + 1; $k < $count; $k++) {
            if ($tokens[$k] === '{') {
                $bodyOpen = $k;
                break;
            }
            if ($tokens[$k] === ';' || $tokens[$k] === '}') {
                break;
            }
        }
        if ($bodyOpen === null) {
            continue;
        }
        $bodyClose = matchTokenBrace($tokens, $bodyOpen);
        if ($bodyClose === null) {
            continue;
        }

        // 类体内的方法
        $methods = [];
        for ($k = $bodyOpen + 1; $k < $bodyClose; $k++) {
            $t = $tokens[$k];
            if (!is_array($t) || $t[0] !== T_FUNCTION) {
                continue;
            }
            // 方法名（跳过 & 返回引用）
            $namePos = $k + 1;
            while ($namePos < $bodyClose && is_array($tokens[$namePos]) && in_array($tokens[$namePos][0], [T_WHITESPACE, T_COMMENT], true)) {
                $namePos++;
            }
            if ($namePos >= $bodyClose || !is_array($tokens[$namePos]) || $tokens[$namePos][0] !== T_STRING) {
                continue; // 闭包
            }
            $methodName = $tokens[$namePos][1];
            // 形参区间
            $parenPos = $namePos + 1;
            while ($parenPos < $bodyClose && is_array($tokens[$parenPos]) && $tokens[$parenPos][0] === T_WHITESPACE) {
                $parenPos++;
            }
            if ($parenPos >= $bodyClose || $tokens[$parenPos] !== '(') {
                continue;
            }
            $paramsClose = matchTokenBrace($tokens, $parenPos, '(', ')');
            if ($paramsClose === null) {
                continue;
            }
            $paramsText = '';
            for ($textPos = $parenPos + 1; $textPos < $paramsClose; $textPos++) {
                $pt = $tokens[$textPos];
                if (is_array($pt) && in_array($pt[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $paramsText .= is_array($pt) ? $pt[1] : $pt;
            }
            // 返回类型：) 与方法体 { 之间的签名文本
            $bodyStart = null;
            for ($scanPos = $parenPos + 1; $scanPos < $bodyClose; $scanPos++) {
                if ($tokens[$scanPos] === '{') {
                    $bodyStart = $scanPos;
                    break;
                }
                if ($tokens[$scanPos] === ';') {
                    break;
                }
            }
            if ($bodyStart !== null) {
                $sig = '';
                for ($sigPos = $parenPos + 1; $sigPos < $bodyStart; $sigPos++) {
                    $qt = $tokens[$sigPos];
                    if (is_array($qt) && in_array($qt[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $sig .= is_array($qt) ? $qt[1] : $qt;
                }
                $sig = ltrim($sig, ':');
                $returnType = $sig === '' ? null : $sig;
                $bodyEnd = matchTokenBrace($tokens, $bodyStart);
                if ($bodyEnd === null) {
                    continue;
                }
                $bodyText = '';
                for ($textPos = $bodyStart; $textPos <= $bodyEnd; $textPos++) {
                    $bt = $tokens[$textPos];
                    $bodyText .= is_array($bt) ? $bt[1] : $bt;
                }
                $params = [];
                if (preg_match_all('/([?\\\\\w|&]+?)\s*\.\.\.\s*\$(\w+)|([?\\\\\w|&]+?)\s*\$(\w+)/', $paramsText, $pm, PREG_SET_ORDER) !== false) {
                    foreach ($pm as $pair) {
                        $type = trim($pair[1] !== '' ? $pair[1] : ($pair[3] ?? ''));
                        $var = $pair[2] !== '' ? $pair[2] : ($pair[4] ?? '');
                        if ($var !== '' && $type !== '') {
                            $params[$var] = $type;
                        }
                    }
                }
                $methods[$methodName] = ['body' => $bodyText, 'return' => $returnType, 'params' => $params];
                $k = $bodyEnd; // 跳过方法体，避免把闭包当方法
            }
        }
        if ($methods !== []) {
            $result[$fqcn] = ['file' => $path, 'methods' => $methods];
        }
        $i = $bodyClose; // 跳过类体
    }

    return $result;
}

/**
 * token 序列括号配对：从 openPos 的 '{'（或 '('）找配对的 '}'（或 ')'）。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
 */
function matchTokenBrace(array $tokens, int $openPos, string $open = '{', string $close = '}'): ?int
{
    $depth = 0;
    $count = count($tokens);
    for ($i = $openPos; $i < $count; $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;
        $isOpen = $text === $open
            || ($open === '{' && (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)));
        $isClose = $text === $close;
        if ($isOpen) {
            $depth++;
        } elseif ($isClose) {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}

/** 源码行式 use 解析（生成路由文件用）：alias => fqcn。 */
function parseUseLines(string $content): array
{
    $uses = [];
    if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $content, $matches, PREG_SET_ORDER) === false) {
        return $uses;
    }
    foreach ($matches as $match) {
        $fqcn = ltrim($match[1], '\\');
        $alias = isset($match[2]) && $match[2] !== ''
            ? strtolower($match[2])
            : strtolower(substr($fqcn, (int) strrpos('\\' . $fqcn, '\\')));
        $uses[$alias] = $fqcn;
    }

    return $uses;
}

/**
 * 把源码里写的类名（短名/限定名/全限定名）解析成索引里的 FQCN；
 * 解析不到索引内的类返回原样候选（调用方再查索引判定）。
 *
 * @param array<string, string> $uses
 * @param array{classes: array<string, mixed>} $index
 */
function resolveWrittenName(string $name, string $ns, array $uses, array $index): string
{
    $name = trim($name);
    $absolute = str_starts_with($name, '\\');
    $name = ltrim($name, '\\');
    if ($name === '') {
        return '';
    }
    $first = strtok($name, '\\');
    $first = $first === false ? $name : $first;
    $rest = substr($name, strlen($first));
    $alias = strtolower($first);
    if (isset($uses[$alias])) {
        $candidate = $uses[$alias] . $rest;
    } elseif ($absolute || str_contains($name, '\\')) {
        $candidate = $absolute ? $name : ($ns === '' ? $name : $ns . '\\' . $name);
    } else {
        $candidate = $ns === '' ? $name : $ns . '\\' . $name;
        if (!isset($index['classes'][$candidate]) && isset($index['classes'][$name])) {
            $candidate = $name;
        }
    }

    return $candidate;
}

/**
 * 类型串解析成索引内类名：剥离可空/联合/括号，self/static/$this 归当前类。
 *
 * @param array<string, string> $uses
 * @param array{classes: array<string, mixed>} $index
 */
function resolveClassType(string $type, string $ns, array $uses, array $index, string $currentClass): ?string
{
    $type = trim($type);
    if ($type === '') {
        return null;
    }
    // phpstan 形状注解与联合类型只取第一个像类名的候选
    foreach (preg_split('/[|&(),\s]+/', $type) ?: [] as $part) {
        $part = trim($part, " \t\n\r\0\x0B?[]");
        if ($part === '' ) {
            continue;
        }
        $lower = strtolower($part);
        if (in_array($lower, ['self', 'static', '$this'], true)) {
            return $currentClass;
        }
        if (in_array(strtolower($part), ['array', 'callable', 'closure', 'iterable', 'string', 'int', 'float', 'bool', 'void', 'mixed', 'object', 'null', 'false', 'true', 'never', 'resource'], true)) {
            continue;
        }
        if (!preg_match('/^[?\\\\]*[A-Za-z_\\\\]/', $part)) {
            continue;
        }
        $candidate = resolveWrittenName($part, $ns, $uses, $index);
        if (isset($index['classes'][$candidate])) {
            return $candidate;
        }
    }

    return null;
}

/**
 * 收集 handler 方法的可达调用闭包（$this->/self::/ClassName::/$var->/链式 ->），
 * 返回所有可达方法体拼接文本；handler 或方法不存在返回 null。
 *
 * @param array{classes: array<string, array{file: string, methods: array<string, array{body: string, return: ?string, params: array<string, string>}>}>} $index
 */
function collectHandlerEvidence(array $index, string $className, string $methodName): ?string
{
    if (!isset($index['classes'][$className]['methods'][$methodName])) {
        return null;
    }
    $classes = $index['classes'];
    // 每个类记录自己文件的 ns/uses（索引构建时随文件解析）
    $contexts = classFileContexts($classes);

    $queue = [[$className, $methodName]];
    $seen = [];
    $texts = [];
    $guard = 0;
    while ($queue !== [] && $guard++ < 400) {
        [$class, $method] = array_shift($queue);
        $key = $class . '::' . $method;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $info = $classes[$class]['methods'][$method] ?? null;
        if ($info === null) {
            continue;
        }
        $texts[] = $info['body'];
        $ctx = $contexts[$class] ?? ['ns' => '', 'uses' => []];
        $targets = scanBodyCalls(
            $info['body'],
            $class,
            $ctx['ns'],
            $ctx['uses'],
            $info['params'],
            $classes,
            $contexts,
        );
        foreach ($targets as [$tc, $tm]) {
            if (isset($classes[$tc]['methods'][$tm]) && !isset($seen[$tc . '::' . $tm])) {
                $queue[] = [$tc, $tm];
            }
        }
    }

    return implode("\n", $texts);
}

/**
 * 类 → 所在文件 ns/uses 上下文。indexPhpFile 已按文件解析，
 * 这里按文件路径聚合。
 *
 * @param array<string, array{file: string, methods: mixed}> $classes
 *
 * @return array<string, array{ns: string, uses: array<string, string>}>
 */
function classFileContexts(array $classes): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $byFile = [];
    foreach ($classes as $fqcn => $info) {
        $byFile[$info['file']][] = $fqcn;
    }
    foreach ($byFile as $file => $fqns) {
        $code = (string) file_get_contents($file);
        $ns = '';
        $uses = [];
        if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $code, $nm) === 1) {
            $ns = $nm[1];
        }
        if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $code, $um, PREG_SET_ORDER) !== false) {
            foreach ($um as $useMatch) {
                $fqcnUse = ltrim($useMatch[1], '\\');
                $alias = isset($useMatch[2]) && $useMatch[2] !== ''
                    ? strtolower($useMatch[2])
                    : strtolower(substr($fqcnUse, (int) strrpos('\\' . $fqcnUse, '\\')));
                $uses[$alias] = $fqcnUse;
            }
        }
        foreach ($fqns as $fqcn) {
            $cache[$fqcn] = ['ns' => $ns, 'uses' => $uses];
        }
    }

    return $cache;
}

/**
 * 在方法体文本里找可解析的调用目标。
 * 覆盖：$this->m(、self::/static::m(、X::m(、$var->m(（var 来自 new/工厂/闭包形参/方法返回类型）、
 * 以及 `...)->m(` 链式调用（返回类型可解析时）。
 *
 * @param array<string, string> $params 方法形参 var => 类型串
 * @param array<string, array{file: string, methods: array<string, array{body: string, return: ?string, params: array<string, string>}>}> $classes
 * @param array<string, array{ns: string, uses: array<string, string>}> $contexts
 *
 * @return list<array{0: string, 1: string}>
 */
function scanBodyCalls(
    string $body,
    string $currentClass,
    string $ns,
    array $uses,
    array $params,
    array $classes,
    array $contexts,
): array {
    $targets = [];
    $varTypes = [];

    // 形参类型（含闭包/匿名函数形参）
    foreach ($params as $var => $type) {
        $resolved = resolveClassType($type, $ns, $uses, ['classes' => $classes], $currentClass);
        if ($resolved !== null) {
            $varTypes[$var] = $resolved;
        }
    }
    if (preg_match_all('/(?:fn|function)\s*\(([^()]*)\)/', $body, $closures, PREG_SET_ORDER) !== false) {
        foreach ($closures as $closure) {
            if (preg_match_all('/([?\\\\\w|&]+?)\s*\.\.\.\s*\$(\w+)|([?\\\\\w|&]+?)\s*\$(\w+)/', $closure[1], $pm, PREG_SET_ORDER) !== false) {
                foreach ($pm as $pair) {
                    $type = trim($pair[1] !== '' ? $pair[1] : ($pair[3] ?? ''));
                    $var = $pair[2] !== '' ? $pair[2] : ($pair[4] ?? '');
                    if ($var !== '' && $type !== '') {
                        $resolved = resolveClassType($type, $ns, $uses, ['classes' => $classes], $currentClass);
                        if ($resolved !== null) {
                            $varTypes[$var] = $resolved;
                        }
                    }
                }
            }
        }
    }

    // 变量类型绑定：new X / X::m() / self::m() / $this->m()
    if (preg_match_all('/\$(\w+)\s*=\s*new\s+([?\\\\\w|&]+)/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $resolved = resolveClassType($match[2], $ns, $uses, ['classes' => $classes], $currentClass);
            if ($resolved !== null) {
                $varTypes[$match[1]] = $resolved;
            }
        }
    }
    if (preg_match_all('/\$(\w+)\s*=\s*([\w\\\\]+)::(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $staticClass = resolveWrittenName($match[2], $ns, $uses, ['classes' => $classes]);
            $returnClass = methodReturnClass($classes, $contexts, $staticClass, $match[3], $ns, $uses);
            $varTypes[$match[1]] = $returnClass ?? $staticClass;
        }
    }
    if (preg_match_all('/\$(\w+)\s*=\s*(?:self|static)::(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $returnClass = methodReturnClass($classes, $contexts, $currentClass, $match[2], $ns, $uses);
            if ($returnClass !== null) {
                $varTypes[$match[1]] = $returnClass;
            }
        }
    }
    if (preg_match_all('/\$(\w+)\s*=\s*\$this->(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $returnClass = methodReturnClass($classes, $contexts, $currentClass, $match[2], $ns, $uses);
            if ($returnClass !== null) {
                $varTypes[$match[1]] = $returnClass;
            }
        }
    }

    $push = function (string $class, string $method) use (&$targets, $classes): void {
        if ($class !== '' && isset($classes[$class]['methods'][$method])) {
            $targets[$class . '::' . $method] = [$class, $method];
        }
    };

    // $this->m( 与链式 $this->m(...)->n(
    if (preg_match_all('/\$this->(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $push($currentClass, $match[1]);
        }
    }
    // self::/static::/parent:: 与 ClassName:: 静态调用
    if (preg_match_all('/(?<![\w$\\\\])(?:self|static|parent)::(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $push($currentClass, $match[1]);
        }
    }
    if (preg_match_all('/(?<![\w$\\\\:])([\w\\\\]+)::(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            if (in_array(strtolower($match[1]), ['self', 'static', 'parent'], true)) {
                continue;
            }
            $staticClass = resolveWrittenName($match[1], $ns, $uses, ['classes' => $classes]);
            if (isset($classes[$staticClass])) {
                $push($staticClass, $match[2]);
            }
        }
    }
    // $var->m( / $var?->m(
    if (preg_match_all('/\$([A-Za-z_]\w*)\s*(?:->|\?->)(\w+)\s*\(/', $body, $mm, PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            if ($match[1] === 'this') {
                $push($currentClass, $match[2]);
                continue;
            }
            $varClass = $varTypes[$match[1]] ?? null;
            if ($varClass !== null && isset($classes[$varClass])) {
                $push($varClass, $match[2]);
            }
        }
    }
    // 链式 )->m(：前一个调用的返回类型可解析时跟进
    if (preg_match_all('/\)\s*(?:->|\?->)(\w+)\s*\(/', $body, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
        foreach ($mm as $match) {
            $method = $match[1][0];
            $pos = $match[0][1];
            $callee = resolveCallBeforeParen($body, $pos, $currentClass, $ns, $uses, $varTypes, $classes, $contexts, 0);
            if ($callee !== null) {
                [$calleeClass, $calleeMethod] = $callee;
                $returnClass = methodReturnClass($classes, $contexts, $calleeClass, $calleeMethod, $ns, $uses);
                if ($returnClass !== null && isset($classes[$returnClass])) {
                    $push($returnClass, $method);
                }
            }
        }
    }

    return array_values($targets);
}

/**
 * 定位 `)->m(` 中 `)` 对应的 `(` 之前的被调方，返回 [class, method]。
 * 只解一层：被调方本身是 `X->m(`、`X::m(`、`$this->m(`、`$var->m(`、`name(`（不可解返回 null）。
 *
 * @param array<string, string> $varTypes
 * @param array<string, array{file: string, methods: array<string, array{body: string, return: ?string, params: array<string, string>}>}> $classes
 * @param array<string, array{ns: string, uses: array<string, string>}> $contexts
 *
 * @return array{0: string, 1: string}|null
 */
function resolveCallBeforeParen(
    string $body,
    int $closeParenPos,
    string $currentClass,
    string $ns,
    array $uses,
    array $varTypes,
    array $classes,
    array $contexts,
    int $depth,
): ?array {
    if ($depth > 3) {
        return null;
    }
    // 从 closeParenPos 向左找配对 (
    $depthParen = 0;
    $openPos = null;
    for ($i = $closeParenPos; $i >= 0; $i--) {
        $ch = $body[$i];
        if ($ch === ')') {
            $depthParen++;
        } elseif ($ch === '(') {
            $depthParen--;
            if ($depthParen === 0) {
                $openPos = $i;
                break;
            }
        }
    }
    if ($openPos === null) {
        return null;
    }
    // `(` 之前应是方法名
    $before = substr($body, 0, $openPos);
    if (preg_match('/(\w+)\s*$/', $before, $nm) !== 1) {
        return null;
    }
    $calleeMethod = $nm[1];
    $receiver = substr($before, 0, strlen($before) - strlen($nm[0]));
    // :: 静态
    if (preg_match('/([\w\\\\]+)::\s*$/', $receiver, $cm) === 1) {
        $staticClass = resolveWrittenName($cm[1], $ns, $uses, ['classes' => $classes]);
        if (in_array(strtolower($cm[1]), ['self', 'static', 'parent'], true)) {
            return [$currentClass, $calleeMethod];
        }
        return isset($classes[$staticClass]) ? [$staticClass, $calleeMethod] : null;
    }
    // -> 实例
    if (preg_match('/(->|\?->)\s*$/', $receiver) === 1) {
        // 去掉末尾 -> / ?->
        $head = (string) preg_replace('/(->|\?->)\s*$/', '', $receiver);
        if (preg_match('/\$this\s*$/', $head) === 1) {
            return [$currentClass, $calleeMethod];
        }
        if (preg_match('/\$(\w+)\s*$/', $head, $vm) === 1) {
            $varClass = $varTypes[$vm[1]] ?? null;
            return $varClass !== null && isset($classes[$varClass]) ? [$varClass, $calleeMethod] : null;
        }
        // `)` 结尾：再上一层链式（如 $this->a()->b()->c() 中 b()->c 的被调方是 a() 的返回）
        if (substr(rtrim($head), -1) === ')') {
            $inner = resolveCallBeforeParen($body, (int) strrpos($head, ')'), $currentClass, $ns, $uses, $varTypes, $classes, $contexts, $depth + 1);
            if ($inner !== null) {
                [$ic, $im] = $inner;
                $iret = methodReturnClass($classes, $contexts, $ic, $im, $ns, $uses);
                return $iret !== null && isset($classes[$iret]) ? [$iret, $calleeMethod] : null;
            }
        }
        return null;
    }

    return null;
}

/**
 * 方法返回类型 → 索引内类名；无声明时从 `return new X` / `return X::` / `return $this` 推断。
 *
 * @param array<string, array{file: string, methods: array<string, array{body: string, return: ?string, params: array<string, string>}>}> $classes
 * @param array<string, array{ns: string, uses: array<string, string>}> $contexts
 */
function methodReturnClass(
    array $classes,
    array $contexts,
    string $class,
    string $method,
    string $ns,
    array $uses,
): ?string {
    $info = $classes[$class]['methods'][$method] ?? null;
    if ($info === null) {
        return null;
    }
    $ctx = $contexts[$class] ?? ['ns' => $ns, 'uses' => $uses];
    if (is_string($info['return']) && $info['return'] !== '') {
        $resolved = resolveClassType($info['return'], $ctx['ns'], $ctx['uses'], ['classes' => $classes], $class);
        if ($resolved !== null) {
            return $resolved;
        }
    }
    $body = $info['body'];
    if (preg_match('/return\s+new\s+([\w\\\\]+)/', $body, $returnMatch) === 1) {
        $candidate = resolveWrittenName($returnMatch[1], $ctx['ns'], $ctx['uses'], ['classes' => $classes]);
        if (isset($classes[$candidate])) {
            return $candidate;
        }
    }
    if (preg_match('/return\s+([\w\\\\]+)::/', $body, $returnMatch) === 1) {
        $candidate = resolveWrittenName($returnMatch[1], $ctx['ns'], $ctx['uses'], ['classes' => $classes]);
        if (isset($classes[$candidate])) {
            return $candidate;
        }
    }
    if (preg_match('/return\s+\$this\b/', $body) === 1) {
        return $class;
    }

    return null;
}

/**
 * 从源码文本定位 `{` 的配对 `}` 位置（route.php 哨兵块用，文本级近似）。
 */
function matchBraceEnd(string $text, int $openPos): ?int
{
    $depth = 0;
    $len = strlen($text);
    for ($i = $openPos; $i < $len; $i++) {
        $ch = $text[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}
