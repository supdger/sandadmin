<?php
// 用法：php tests/security/scan-input-bans.php [php-root] [--no-frontend]（在 server/ 下），命中 FAIL 退出 1。
// 禁令：unserialize() 内建调用；持久化方法参数内直接取 $request->post/get/all；
// 前端新增行出现 v-html/innerHTML/dangerouslySetInnerHTML 且文件无消毒标记。
declare(strict_types=1);

const INPUT_BANS_PHP_SKIP = '#^(vendor|runtime|plugin|tests|node_modules)/#';
const INPUT_BANS_PERSIST_METHODS = ['save', 'insert', 'update', 'create', 'fill', 'forcefill'];
const INPUT_BANS_REQUEST_METHODS = ['post', 'get', 'all'];
const INPUT_BANS_HTML_PATTERN = '/v-html|innerhtml|dangerouslysetinnerhtml/i';
const INPUT_BANS_SANITIZE_PATTERN = '/dompurify|sanitize[-_]?html|xss|escapehtml/i';

/**
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 */
function banNext(array $tokens, int $from): int
{
    for ($i = $from + 1, $size = count($tokens); $i < $size; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $i;
    }
    return count($tokens);
}

/**
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 */
function banPrev(array $tokens, int $from): int
{
    for ($i = $from - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $i;
    }
    return -1;
}

/** @return array{rule:string,line:int,detail:string} */
function banHit(string $rule, int $line, string $detail): array
{
    return ['rule' => $rule, 'line' => $line, 'detail' => $detail];
}

/**
 * tokens[$i] 处的 ->/?-> 方法调用（方法名在 i+1）是不是 $request->post()/get()/all()：
 * 接收端最后一段名字（变量名去 $ 或属性名）须为 request（不区分大小写）。
 *
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 */
function banHasRequestArray(array $tokens, int $opIndex): bool
{
    $nameIndex = banNext($tokens, $opIndex);
    $name = $tokens[$nameIndex] ?? null;
    $callIndex = banNext($tokens, $nameIndex);
    if (!is_array($name) || $name[0] !== T_STRING
        || !in_array(strtolower($name[1]), INPUT_BANS_REQUEST_METHODS, true)
        || ($tokens[$callIndex] ?? null) !== '(') {
        return false;
    }
    // 向前找接收端末段：可能是 $request，也可能是 $this->request / $kernel->request->post() 的属性名 request
    $j = banPrev($tokens, $opIndex);
    $receiver = $tokens[$j] ?? null;
    if (is_array($receiver) && $receiver[0] === T_VARIABLE) {
        return strcasecmp(ltrim($receiver[1], '$'), 'request') === 0;
    }
    if (is_array($receiver) && $receiver[0] === T_STRING) {
        $beforeProp = $tokens[banPrev($tokens, $j)] ?? null;
        if (is_array($beforeProp) && in_array($beforeProp[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
            return strcasecmp($receiver[1], 'request') === 0;
        }
    }
    return false;
}

/**
 * 对一份 PHP 源码做 token 级扫描，返回命中列表。
 *
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 * @return list<array{rule:string,line:int,detail:string,file?:string}>
 */
function banScanPhpTokens(array $tokens): array
{
    $hits = [];
    foreach ($tokens as $i => $t) {
        if (!is_array($t) || $t[0] !== T_STRING) {
            continue;
        }
        [, $text, $line] = $t;
        $lower = strtolower($text);
        $prevIndex = banPrev($tokens, $i);
        $prev = $tokens[$prevIndex] ?? null;
        $isMember = is_array($prev) && in_array($prev[0],
            [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
        $nextIndex = banNext($tokens, $i);
        $isCall = ($tokens[$nextIndex] ?? null) === '(';

        if ($lower === 'unserialize' && $isCall && !$isMember
            && !(is_array($prev) && $prev[0] === T_NEW)) {
            $hits[] = banHit('反序列化调用', $line, 'unserialize() 只允许用于可信来源，宿主代码禁用');
            continue;
        }
        if (!$isMember || !$isCall || !in_array($lower, INPUT_BANS_PERSIST_METHODS, true)) {
            continue;
        }
        // 找到参数列表的配对右括号，只在列表内部查请求数组直取
        $depth = 0;
        $end = count($tokens);
        for ($j = $nextIndex, $size = count($tokens); $j < $size; $j++) {
            $tok = $tokens[$j];
            if ($tok === '(') {
                $depth++;
            } elseif ($tok === ')') {
                $depth--;
                if ($depth === 0) {
                    $end = $j;
                    break;
                }
            }
        }
        for ($j = $nextIndex + 1; $j < $end; $j++) {
            $tok = $tokens[$j];
            if (!is_array($tok) || !in_array($tok[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue;
            }
            if (banHasRequestArray($tokens, $j)) {
                $nameIndex = banNext($tokens, $j);
                $nameToken = $tokens[$nameIndex] ?? null;
                $methodName = is_array($nameToken) ? $nameToken[1] : '';
                $hits[] = banHit('请求数组直灌', $line, "{$text}() 参数内直接调用 ->{$methodName}() 取请求数组，须先逐字段校验");
                break;
            }
        }
    }
    return $hits;
}

/** 扫描一个 PHP 文件；$relPath 是相对扫描根的路径，用于输出。
 *
 * @return list<array{rule:string,line:int,detail:string,file?:string}>
 */
function banScanPhpFile(string $absPath, string $relPath): array
{
    $code = file_get_contents($absPath);
    if ($code === false) {
        fwrite(STDERR, "WARN\t读文件失败\t{$relPath}\n");
        return [];
    }
    /** @var array<int, array{0:int,1:string,2:int}|string> $tokens */
    $tokens = token_get_all($code);
    $hits = [];
    foreach (banScanPhpTokens($tokens) as $hit) {
        $hits[] = ['file' => $relPath] + $hit;
    }
    return $hits;
}

/** 收集 phpRoot 下的宿主 PHP 文件（相对路径 => 绝对路径），规则与 scan-dangerous-functions.php 一致。
 *
 * @return array<string, string>
 */
function banCollectPhp(string $phpRoot): array
{
    $files = [];
    if (!is_dir($phpRoot)) {
        return $files;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($phpRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($phpRoot) + 1);
        if (preg_match(INPUT_BANS_PHP_SKIP, $rel) || !preg_match('/\.php$|^webman$/', $rel)) {
            continue;
        }
        $files[$rel] = $file->getPathname();
    }
    ksort($files, SORT_STRING);
    return $files;
}

/** 路径是否属于前端禁令范围：web/src、sandadmin-artd/src 下全部文件，加两个 index.html。 */
function banIsFrontendPath(string $path): bool
{
    return str_starts_with($path, 'web/src/')
        || str_starts_with($path, 'sandadmin-artd/src/')
        || $path === 'web/index.html'
        || $path === 'sandadmin-artd/index.html';
}

/**
 * 解析 `git diff -U0` 文本，返回 新增行表：[path => [line=>true]]。
 *
 * @return array<string, array<int, true>>
 */
function banParseDiff(string $diff): array
{
    $added = [];
    $path = null;
    $number = 0;
    foreach (explode("\n", $diff) as $raw) {
        if (str_starts_with($raw, '+++ ')) {
            $path = trim(substr($raw, 4));
            $path = str_starts_with($path, 'b/') ? substr($path, 2) : $path;
        } elseif (str_starts_with($raw, '@@')) {
            $match = [];
            if (preg_match('/\+(\d+)/', $raw, $match) === 1) {
                $number = (int) $match[1];
            }
        } elseif (str_starts_with($raw, '+') && $path !== null && $path !== '/dev/null') {
            $added[$path][$number] = true;
            $number++;
        }
    }
    return $added;
}

function banGit(string $root, string $args): string
{
    $out = [];
    exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null', $out);
    return implode("\n", $out);
}

/**
 * 前端文件新增行检查。$addedLines 为 true 表示整文件都是新增（未跟踪文件），
 * 否则是 [行号=>true]。命中返回列表；文件含消毒标记时全部放行。
 *
 * @param array<int,true>|true $addedLines
 * @return list<array{rule:string,line:int,detail:string,file?:string}>
 */
function banScanHtmlFile(string $relPath, string $content, array|bool $addedLines): array
{
    $hits = [];
    $sanitized = preg_match(INPUT_BANS_SANITIZE_PATTERN, $content) === 1;
    $lineCount = substr_count($content, "\n") + 1;
    $candidates = $addedLines === true
        ? range(1, $lineCount)
        : array_keys($addedLines);
    $lines = explode("\n", $content);
    foreach ($candidates as $number) {
        $text = $lines[$number - 1] ?? null;
        if (!is_string($text) || !preg_match(INPUT_BANS_HTML_PATTERN, $text)) {
            continue;
        }
        if ($sanitized) {
            continue;
        }
        $hits[] = banHit('未消毒HTML渲染', $number, trim($text)) + ['file' => $relPath];
    }
    return $hits;
}

/**
 * 收集前端 diff 新增行：[path => [line=>true]|true]。
 * 未提交改动 + merge-base origin/main 与 HEAD 的分支差异 + 未跟踪文件。
 *
 * @return array{ok:bool,added:array<string,array<int,true>|true>}
 */
function banFrontendAdded(string $repoRoot): array
{
    $paths = 'web sandadmin-artd';
    $added = banParseDiff(banGit($repoRoot, "diff -U0 HEAD -- {$paths}"));
    $base = trim(banGit($repoRoot, 'merge-base origin/main HEAD'));
    if ($base === '') {
        return ['ok' => false, 'added' => $added];
    }
    foreach (banParseDiff(banGit($repoRoot, "diff -U0 {$base}...HEAD -- {$paths}")) as $path => $lines) {
        $added[$path] = ($added[$path] ?? []) + $lines;
    }
    foreach (explode("\n", banGit($repoRoot, "ls-files --others --exclude-standard -- {$paths}")) as $path) {
        $path = trim($path);
        if ($path !== '' && banIsFrontendPath($path)) {
            $added[$path] = true;
        }
    }
    return ['ok' => true, 'added' => $added];
}

/**
 * @param list<string> $argv
 */
function banMain(array $argv): int
{
    $serverRoot = dirname(__DIR__, 2);
    $repoRoot = dirname($serverRoot);
    $phpRoot = $serverRoot;
    $frontend = true;
    foreach ($argv as $arg) {
        if ($arg === '--no-frontend') {
            $frontend = false;
        } elseif (!str_starts_with($arg, '--')) {
            $phpRoot = str_starts_with($arg, '/') ? $arg : (getcwd() ?: '') . '/' . $arg;
        }
    }

    $fail = 0;
    foreach (banCollectPhp($phpRoot) as $rel => $abs) {
        foreach (banScanPhpFile($abs, $rel) as $hit) {
            $fail++;
            fwrite(STDERR, "FAIL\t{$hit['rule']}\t{$rel}:{$hit['line']}\t{$hit['detail']}\n");
        }
    }

    if ($frontend) {
        $result = banFrontendAdded($repoRoot);
        if (!$result['ok']) {
            fwrite(STDERR, "INFO\t未找到 origin/main 合并基线，跳过前端禁令扫描\n");
        } else {
            foreach ($result['added'] as $path => $lines) {
                if (!banIsFrontendPath($path)) {
                    continue;
                }
                $abs = $repoRoot . '/' . $path;
                if (!is_file($abs)) {
                    continue;
                }
                $content = file_get_contents($abs);
                if ($content === false) {
                    continue;
                }
                foreach (banScanHtmlFile($path, $content, $lines) as $hit) {
                    $fail++;
                    fwrite(STDERR, "FAIL\t{$hit['rule']}\t" . ($hit['file'] ?? $path) . ":{$hit['line']}\t{$hit['detail']}\n");
                }
            }
        }
    }

    if ($fail > 0) {
        fwrite(STDERR, "FAIL\t输入输出禁令命中 {$fail} 处\n");
        return 1;
    }
    echo "PASS\t输入输出三条禁令扫描通过" . PHP_EOL;
    return 0;
}

$argv = [];
foreach ((array) ($_SERVER['argv'] ?? []) as $value) {
    if (is_string($value)) {
        $argv[] = $value;
    }
}
if (realpath($argv[0] ?? '') === __FILE__) {
    exit(banMain(array_slice($argv, 1)));
}
