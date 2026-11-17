#!/usr/bin/env php
<?php

declare(strict_types=1);

// 把两张上游常用弱密码表合并去重成一张命中表，输出到
// server/resource/security/weak-passwords.txt。构建期工具，可重复运行、产出幂等。
// 来源与许可见 server/resource/security/README.md；上游文件按 commit 固定，防止漂移。

const SECLISTS_COMMIT = '6b19c2f5088c45b5dadb4d8918d2ab4f7efb03a9';
const SECLISTS_BASE = 'https://raw.githubusercontent.com/danielmiessler/SecLists/';
const NCSC_SOURCE = 'Passwords/Common-Credentials/100k-most-used-passwords-NCSC.txt';
const CHINESE_SOURCE = 'Passwords/Common-Credentials/Language-Specific/Chinese-common-password-list-top-100000.txt';

// 中文表按源文件频率序只取前 20000 条。
const CHINESE_LIMIT = 20000;

const OUT_FILE = 'server/resource/security/weak-passwords.txt';

/** 源文件里长得像手机号（11 位）的条目不入表。 */
const MOBILE_PATTERN = '/^1[3-9]\d{9}$/';

/** 源文件里长得像身份证号（15 位或 18 位含校验位）的条目不入表。 */
const ID_CARD_PATTERN = '/^(\d{15}|\d{17}[\dXx])$/';

/** 源文件里长得像 QQ 号（5 到 11 位纯数字）的条目不入表。 */
const QQ_PATTERN = '/^\d{5,11}$/';

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
    $srcDir = option($argv, '--src-dir');
    $out = option($argv, '--out') ?? repoRoot() . DIRECTORY_SEPARATOR . OUT_FILE;

    $ncscRaw = sourceText(NCSC_SOURCE, $srcDir);
    $chineseRaw = sourceText(CHINESE_SOURCE, $srcDir);

    $ncscSet = entries($ncscRaw, null);
    $chineseSet = entries($chineseRaw, CHINESE_LIMIT);

    $merged = $ncscSet + $chineseSet;
    $words = array_keys($merged);
    sort($words, SORT_STRING);

    $text = implode("\n", $words) . "\n";
    $dir = dirname($out);
    if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
        fwrite(STDERR, "创建目录失败：{$dir}\n");
        return 1;
    }
    if (file_put_contents($out, $text) === false) {
        fwrite(STDERR, "写入失败：{$out}\n");
        return 1;
    }

    $sha = hash_file('sha256', $out);
    printf("NCSC 表保留条目：%d\n", count($ncscSet));
    printf("中文表保留条目：%d（源取前 %d 条）\n", count($chineseSet), CHINESE_LIMIT);
    printf("合并后条目：%d\n", count($words));
    printf("输出：%s（%d 字节）\n", $out, strlen($text));
    printf("sha256：%s\n", is_string($sha) ? $sha : '');
    return 0;
}

function repoRoot(): string
{
    return dirname(__DIR__);
}

/** @param list<string> $argv */
function option(array $argv, string $name): ?string
{
    foreach ($argv as $index => $arg) {
        if ($arg === $name && isset($argv[$index + 1])) {
            return $argv[$index + 1];
        }
    }
    return null;
}

/**
 * 取一张源表内容：--src-dir 指定本地目录时读本地副本，否则按 pin 住的 commit 下载。
 */
function sourceText(string $relative, ?string $srcDir): string
{
    if ($srcDir !== null) {
        $path = rtrim($srcDir, '/\\') . DIRECTORY_SEPARATOR . basename($relative);
        $text = file_get_contents($path);
        if ($text === false) {
            fwrite(STDERR, "读源文件失败：{$path}\n");
            exit(1);
        }
        return $text;
    }
    $url = SECLISTS_BASE . SECLISTS_COMMIT . '/' . $relative;
    $context = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'sandadmin-weakpw-build']]);
    $text = file_get_contents($url, false, $context);
    if ($text === false) {
        fwrite(STDERR, "下载源文件失败：{$url}\n");
        exit(1);
    }
    return $text;
}

/**
 * 源表内容 → 小写归一的命中集合（键即条目）。
 * $headLimit 非空时只取源文件前 N 行再清洗。
 *
 * @return array<string, true>
 */
function entries(string $raw, ?int $headLimit): array
{
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    if ($lines === false) {
        return [];
    }
    if ($headLimit !== null) {
        $lines = array_slice($lines, 0, $headLimit);
    }
    $set = [];
    foreach ($lines as $line) {
        $word = trim($line);
        if (!accepts($word)) {
            continue;
        }
        $set[mb_strtolower($word)] = true;
    }
    return $set;
}

/**
 * 单条源记录是否保留：去空行、注释行、含空白字符的行，
 * 以及像手机号、身份证号、邮箱、QQ 号的条目。
 */
function accepts(string $word): bool
{
    if ($word === '' || $word[0] === '#' || $word[0] === ';') {
        return false;
    }
    if (preg_match('/\s/', $word) === 1) {
        return false;
    }
    if (str_contains($word, '@')) {
        return false;
    }
    if (preg_match(MOBILE_PATTERN, $word) === 1) {
        return false;
    }
    if (preg_match(ID_CARD_PATTERN, $word) === 1) {
        return false;
    }
    if (preg_match(QQ_PATTERN, $word) === 1) {
        return false;
    }
    return true;
}

exit(main($argv));
