#!/usr/bin/env php
<?php

declare(strict_types=1);

// TypePHP 禁用写法机械检查（ADR-23 / php-aot-coding）。
// 用法：php tools/typephp_check.php [--all] [--allow F]
//   默认 diff 模式：只查「git 未提交改动 + 当前分支相对 main 的差异 + 未跟踪文件」里新增的行；
//   --all 全量扫描 server/src、server/app、server/config、server/support（插件目录不算宿主自有代码）。
// 白名单 tools/typephp-allow.txt：一行一条「文件相对路径[:行号|*]」，# 后为原因注释；
// 文件不存在时视为无豁免、全量报错。命中 FAIL 退出码非 0。

const TYPEPHP_SCAN_DIRS = ['server/src', 'server/app', 'server/config', 'server/support'];
const TYPEPHP_SKIP_PREFIX = 'server/config/plugin/';

const TYPEPHP_MAGIC_METHODS = [
    '__get', '__set', '__call', '__callstatic', '__isset', '__unset',
    '__sleep', '__wakeup', '__clone', '__debuginfo', '__serialize', '__unserialize',
];
const TYPEPHP_FAIL_FUNCTIONS = ['create_function', 'extract'];
const TYPEPHP_WARN_FUNCTIONS = ['compact'];
const TYPEPHP_LOAD_TOKENS = [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE];

/**
 * 单条命中。lines 记全部相关行：diff 模式下任一相关行是新增行即算命中。
 *
 * @return array{level:string,rule:string,line:int,lines:list<int>,name:string,why:string}
 */
function typephpHit(string $level, string $rule, array $lines, string $name, string $why): array
{
    return ['level' => $level, 'rule' => $rule, 'line' => $lines[0], 'lines' => $lines, 'name' => $name, 'why' => $why];
}

/** 跳过空白与注释，返回下一个有效 token 下标；越界返回 count($tokens)。 */
function typephpNext(array $tokens, int $from): int
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

/** 跳过空白与注释，返回上一个有效 token 下标；越界返回 -1。 */
function typephpPrev(array $tokens, int $from): int
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

function typephpToken(array $tokens, int $i): array|string|null
{
    return $tokens[$i] ?? null;
}

function typephpIsToken(array|string|null $token, int $id, ?string $text = null): bool
{
    return is_array($token) && $token[0] === $id && ($text === null || strcasecmp($token[1], $text) === 0);
}

/** 该 token 是否处在双引号/heredoc 字符串插值内部（前缀 token 是字符串片段）。 */
function typephpInString(array $tokens, int $i): bool
{
    $prev = typephpToken($tokens, $i - 1);
    return $prev === '"'
        || (is_array($prev) && in_array($prev[0], [T_ENCAPSED_AND_WHITESPACE, T_START_HEREDOC, T_CURLY_OPEN], true));
}

/**
 * 对一份 PHP 源码做 token 级扫描，返回命中列表（未套白名单、未套 diff 过滤）。
 *
 * @return list<array{level:string,rule:string,line:int,lines:list<int>,name:string,why:string}>
 */
function typephpScanTokens(array $tokens): array
{
    $hits = [];
    foreach ($tokens as $i => $t) {
        if (!is_array($t)) {
            $inner = typephpToken($tokens, typephpNext($tokens, $i));
            if ($t === '$' && !typephpInString($tokens, $i) && is_array($inner) && $inner[0] === T_VARIABLE) {
                $hits[] = typephpHit('FAIL', '可变变量', [$inner[2]], '$$' . $inner[1], '$$ 形式的变量变量，TypePHP 不可编译');
            } elseif ($t === '{') {
                $op = typephpPrev($tokens, $i);
                $opToken = typephpToken($tokens, $op);
                if (is_array($opToken) && in_array($opToken[0],
                    [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                    $hits[] = typephpHit('FAIL', '可变调用', [$opToken[2]], $opToken[1] . '{expr}', '成员名是表达式，TypePHP 不可静态确定');
                }
            }
            continue;
        }
        [$id, $text, $line] = $t;
        if ($id === T_FUNCTION) {
            $j = typephpNext($tokens, $i);
            if (typephpToken($tokens, $j) === '&') {
                $j = typephpNext($tokens, $j);
            }
            $name = typephpToken($tokens, $j);
            if (is_array($name) && $name[0] === T_STRING
                && in_array(strtolower($name[1]), TYPEPHP_MAGIC_METHODS, true)) {
                $hits[] = typephpHit('FAIL', '魔术方法', [$name[2], $line], $name[1], '魔术方法定义，TypePHP 不可静态确定');
            }
            continue;
        }
        if ($id === T_DOLLAR_OPEN_CURLY_BRACES && !typephpInString($tokens, $i)) {
            $hits[] = typephpHit('FAIL', '可变变量', [$line], '${expr}', '变量变量，TypePHP 不可编译');
            continue;
        }
        if ($id === T_EVAL) {
            $hits[] = typephpHit('FAIL', '动态执行', [$line], 'eval()', 'eval 执行字符串代码，TypePHP 不可编译');
            continue;
        }
        if ($id === T_STRING) {
            $lower = strtolower($text);
            $isCallName = typephpToken($tokens, typephpNext($tokens, $i)) === '(';
            $prev = typephpToken($tokens, typephpPrev($tokens, $i));
            $isMember = is_array($prev) && in_array($prev[0],
                [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_FN], true);
            if ($isCallName && !$isMember && in_array($lower, TYPEPHP_FAIL_FUNCTIONS, true)) {
                $hits[] = typephpHit('FAIL', '危险函数', [$line], $text . '()', 'TypePHP 禁用写法，换成显式赋值');
            } elseif ($isCallName && !$isMember && in_array($lower, TYPEPHP_WARN_FUNCTIONS, true)) {
                $hits[] = typephpHit('WARN', '反向造变量', [$line], $text . '()', 'compact() 反向造变量，建议显式数组');
            }
            if (($lower === 'bindto' || $lower === 'bind') && is_array($prev)
                && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                $hits[] = typephpHit('WARN', '闭包重绑定', [$line], $text . '()', '闭包作用域重绑定，TypePHP 不可编译');
            }
            if ($lower === 'bind' && is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                $receiver = typephpToken($tokens, typephpPrev($tokens, typephpPrev($tokens, $i)));
                if (typephpIsToken($receiver, T_STRING, 'Closure')) {
                    $hits[] = typephpHit('WARN', '闭包重绑定', [$line], 'Closure::bind()', '闭包作用域重绑定，TypePHP 不可编译');
                }
            }
            continue;
        }
        if ($id === T_VARIABLE) {
            $prev = typephpToken($tokens, typephpPrev($tokens, $i));
            $next = typephpToken($tokens, typephpNext($tokens, $i));
            if ($prev === '$') {
                continue; // 属 $$ 变量变量的内层，已由可变变量规则记过
            }
            $isMember = is_array($prev) && in_array($prev[0],
                [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
            if ($next === '(' && !$isMember) {
                $label = typephpIsToken($prev, T_NEW) ? "new {$text}()" : "{$text}()";
                $hits[] = typephpHit('FAIL', '可变调用', [$line], $label, '变量函数/类名调用，TypePHP 不可静态确定');
            }
            continue;
        }
        if ($id === T_OBJECT_OPERATOR || $id === T_NULLSAFE_OBJECT_OPERATOR) {
            $next = typephpToken($tokens, typephpNext($tokens, $i));
            if (typephpIsToken($next, T_VARIABLE)) {
                $hits[] = typephpHit('FAIL', '可变调用', [$line], "->{$next[1]}", '成员名是变量，TypePHP 不可静态确定');
            }
            continue;
        }
        if ($id === T_DOUBLE_COLON) {
            $j = typephpNext($tokens, $i);
            $next = typephpToken($tokens, $j);
            if (typephpIsToken($next, T_VARIABLE)
                && typephpToken($tokens, typephpNext($tokens, $j)) === '(') {
                // ::$m() 是按变量取方法名；无括号的是静态属性字面访问（self::$x），不算动态调用。
                $hits[] = typephpHit('FAIL', '可变调用', [$line], "::{$next[1]}()", '静态方法名是变量，TypePHP 不可静态确定');
            }
            continue;
        }
        if (in_array($id, TYPEPHP_LOAD_TOKENS, true)) {
            $next = typephpToken($tokens, typephpNext($tokens, $i));
            if (typephpIsToken($next, T_VARIABLE) || $next === '$') {
                $hits[] = typephpHit('FAIL', '动态加载', [$line], $text, 'include/require 变量路径，TypePHP 不可静态确定');
            }
            continue;
        }
    }
    return $hits;
}

/**
 * 扫描一个文件；$relPath 是仓库相对路径，用于白名单与输出。
 *
 * @param array{files:array<string,true>,lines:array<string,array<int,true>>} $allow
 * @return list<array{level:string,rule:string,line:int,lines:list<int>,name:string,why:string,exempt:bool}>
 */
function typephpScanFile(string $absPath, string $relPath, array $allow): array
{
    $code = file_get_contents($absPath);
    if ($code === false) {
        fwrite(STDERR, "WARN\t读文件失败\t{$relPath}\n");
        return [];
    }
    $fileFree = isset($allow['files'][$relPath]);
    $hits = [];
    foreach (typephpScanTokens(token_get_all($code)) as $hit) {
        $exempt = $fileFree;
        if (!$exempt) {
            foreach ($hit['lines'] as $line) {
                if (isset($allow['lines'][$relPath][$line])) {
                    $exempt = true;
                    break;
                }
            }
        }
        $hit['exempt'] = $exempt;
        $hits[] = $hit;
    }
    return $hits;
}

/**
 * 读白名单文件，返回 ['files'=>[path=>true], 'lines'=>[path=>[line=>true]]]。
 * 不带行号的条目整文件豁免；:N 只豁免该行，:* 同整文件。
 *
 * @return array{files:array<string,true>,lines:array<string,array<int,true>>}
 */
function typephpReadAllow(string $path): array
{
    $allow = ['files' => [], 'lines' => []];
    $raw = @file($path, FILE_IGNORE_NEW_LINES);
    if ($raw === false) {
        fwrite(STDERR, "INFO\t白名单缺失\t{$path}\t按无豁免全量报错\n");
        return $allow;
    }
    foreach ($raw as $line) {
        $entry = trim(explode('#', $line, 2)[0]);
        if ($entry === '') {
            continue;
        }
        if (preg_match('/^(.+?):(\*|\d+)$/', $entry, $match) === 1) {
            if ($match[2] === '*') {
                $allow['files'][$match[1]] = true;
            } else {
                $allow['lines'][$match[1]][(int) $match[2]] = true;
            }
        } else {
            $allow['files'][$entry] = true;
        }
    }
    return $allow;
}

/**
 * 解析 `git diff -U0` 文本，返回 新增行表：[path => [line=>true]]。
 *
 * @return array<string, array<int, true>>
 */
function typephpParseDiff(string $diff): array
{
    $added = [];
    $path = null;
    $number = 0;
    foreach (explode("\n", $diff) as $raw) {
        if (str_starts_with($raw, '+++ ')) {
            $path = substr(trim(substr($raw, 4)), 0);
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

/**
 * 收集 diff 模式的新增行表：未提交改动 + 相对 main 的分支差异 + 未跟踪文件（整文件算新增）。
 *
 * @return array<string,array<int,true>|true>
 */
function typephpAddedLines(string $root): array
{
    $dirs = implode(' ', array_map('escapeshellarg', TYPEPHP_SCAN_DIRS));
    $added = typephpParseDiff(typephpGit($root, "diff -U0 HEAD -- {$dirs}"));
    $base = trim(typephpGit($root, 'rev-parse --verify main'))
        ?: trim(typephpGit($root, 'rev-parse --verify origin/main'));
    if ($base !== '') {
        foreach (typephpParseDiff(typephpGit($root, "diff -U0 {$base}...HEAD -- {$dirs}")) as $path => $lines) {
            $added[$path] = ($added[$path] ?? []) + $lines;
        }
    } else {
        fwrite(STDERR, "INFO\t无 main 引用\tdiff 只算未提交改动\n");
    }
    foreach (explode("\n", typephpGit($root, "ls-files --others --exclude-standard -- {$dirs}")) as $path) {
        $path = trim($path);
        if ($path !== '' && str_ends_with($path, '.php') && !str_starts_with($path, TYPEPHP_SKIP_PREFIX)) {
            $added[$path] = true;
        }
    }
    return $added;
}

function typephpGit(string $root, string $args): string
{
    $out = [];
    exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null', $out);
    return implode("\n", $out);
}

/**
 * 收集要扫的仓库相对文件路径。--all 走目录遍历；diff 模式只取有新增行的文件。
 *
 * @param array<string,array<int,true>|true>|null $added null 表示全量
 * @return list<string>
 */
function typephpCollect(string $root, ?array $added): array
{
    if ($added !== null) {
        return array_values(array_filter(array_keys($added),
            static fn (string $p): bool => str_ends_with($p, '.php')
                && is_file($root . '/' . $p)
                && !str_starts_with($p, TYPEPHP_SKIP_PREFIX)));
    }
    $files = [];
    foreach (TYPEPHP_SCAN_DIRS as $dir) {
        $base = $root . '/' . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $rel = substr($file->getPathname(), strlen($root) + 1);
            if (!str_starts_with($rel, TYPEPHP_SKIP_PREFIX)) {
                $files[] = $rel;
            }
        }
    }
    sort($files, SORT_STRING);
    return $files;
}

/** @param list<string> $argv */
function typephpOption(array $argv, string $name): ?string
{
    foreach ($argv as $index => $arg) {
        if ($arg === $name && isset($argv[$index + 1])) {
            return $argv[$index + 1];
        }
    }
    return null;
}

/** @param list<string> $argv */
function typephpMain(array $argv): int
{
    $root = dirname(__DIR__);
    $all = in_array('--all', $argv, true);
    $allow = typephpReadAllow(typephpOption($argv, '--allow') ?? $root . '/tools/typephp-allow.txt');
    $added = $all ? null : typephpAddedLines($root);

    $fail = 0;
    $warn = 0;
    $exempt = 0;
    foreach (typephpCollect($root, $added) as $rel) {
        foreach (typephpScanFile($root . '/' . $rel, $rel, $allow) as $hit) {
            if ($added !== null) {
                $lines = $added[$rel];
                $onAdded = $lines === true
                    || (is_array($lines) && array_intersect($hit['lines'], array_keys($lines)) !== []);
                if (!$onAdded) {
                    continue;
                }
            }
            if ($hit['exempt']) {
                $exempt++;
                continue;
            }
            if ($hit['level'] === 'FAIL') {
                $fail++;
            } else {
                $warn++;
            }
            printf("%s\t%s\t%s:%d\t%s\t%s\n", $hit['level'], $hit['rule'], $rel, $hit['line'], $hit['name'], $hit['why']);
        }
    }
    printf("计数\t模式=%s\tFAIL=%d\tWARN=%d\t豁免=%d\n", $all ? 'all' : 'diff', $fail, $warn, $exempt);
    return $fail > 0 ? 1 : 0;
}

if (realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    exit(typephpMain(array_slice($_SERVER['argv'], 1)));
}
