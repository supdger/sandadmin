<?php
// 用法：php tests/security/scan-outbound-http.php  （在 server/ 下）
// 扫描 src/app/config/support 的出站原语（curl_*、GuzzleHttp\Client、SoapClient、
// 原始 socket、stream_context_create、http(s) 包装的文件函数）；豁免 src/Infra/Http/。
declare(strict_types=1);

$root = dirname(__DIR__, 2);

// 允许出现出站原语的路径前缀（相对 server/ 根），命中即整体跳过该文件
const ALLOWED_PREFIXES = ['src/Infra/Http/'];

// 直接调用即判违规的函数名（方法调用 -> / :: 前缀除外）
const BANNED_FUNCTIONS = [
    'curl_init', 'curl_exec', 'curl_setopt', 'curl_setopt_array', 'curl_multi_init',
    'curl_multi_exec', 'curl_multi_add_handle', 'curl_multi_remove_handle', 'curl_multi_select',
    'curl_share', 'curl_share_init', 'curl_share_setopt', 'curl_share_errno', 'curl_share_strerror',
    'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server',
    'stream_socket_pair', 'stream_socket_accept', 'stream_socket_sendto', 'stream_socket_recvfrom',
    'stream_context_create',
];

// 仅当实参里出现 http(s) 字面量包装器时才判违规的文件函数
const URL_FILE_FUNCTIONS = ['file_get_contents', 'file_put_contents', 'file', 'fopen', 'readfile', 'copy', 'rename'];

$dirs = ['src', 'app', 'config', 'support'];
$bad = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($root) + 1);
    $top = explode('/', $rel)[0];
    if (!in_array($top, $dirs, true)) {
        continue;
    }
    foreach (ALLOWED_PREFIXES as $prefix) {
        if (str_starts_with($rel, $prefix)) {
            continue 2;
        }
    }
    $tokens = token_get_all((string) file_get_contents($file->getPathname()));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t)) {
            continue;
        }
        // GuzzleHttp\Client / ClientInterface：T_NAME_QUALIFIED 或全限定名整体一个 token；
        // Psr7 等库类不含这两个名字，不受影响
        if (in_array($t[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
            && preg_match('/^\\\\?GuzzleHttp\\\\Client(Interface)?$/', $t[1]) === 1) {
            $bad[] = "$rel:{$t[2]} {$t[1]}";
            continue;
        }
        if ($t[0] !== T_STRING) {
            continue;
        }
        $name = strtolower($t[1]);
        $isBanned = in_array($name, BANNED_FUNCTIONS, true) || $name === 'soapclient';
        $isUrlFile = in_array($name, URL_FILE_FUNCTIONS, true);
        if (!$isBanned && !$isUrlFile) {
            continue;
        }
        // 方法/静态方法调用与函数定义不算裸函数调用
        for ($j = $i - 1; $j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE; $j--);
        $prev = $tokens[$j] ?? null;
        if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION], true)) {
            continue;
        }
        if ($isBanned) {
            // 函数调用才算：下一个有效 token 必须是 (
            for ($k = $i + 1; $k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE; $k++);
            if (($tokens[$k] ?? null) === '(') {
                $bad[] = "$rel:{$t[2]} {$t[1]}()";
            }
            continue;
        }
        // 文件函数：扫到配对 ) 为止，出现 http:// 或 https:// 字面量实参才算
        for ($k = $i + 1; $k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE; $k++);
        if (($tokens[$k] ?? null) !== '(') {
            continue;
        }
        $depth = 0;
        for ($offset = $k; $offset < $count; $offset++) {
            $tk = $tokens[$offset];
            if ($tk === '(') {
                $depth++;
            } elseif ($tk === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif (is_array($tk) && $tk[0] === T_CONSTANT_ENCAPSED_STRING
                && preg_match('/^([\'"])(https?):\\/\\//i', $tk[1]) === 1) {
                $bad[] = "$rel:{$t[2]} {$t[1]}() 携带 http(s) 包装器实参";
                break;
            }
        }
    }
}

if ($bad) {
    fwrite(STDERR, "FAIL 出站原语出现在豁免目录外：\n  " . implode("\n  ", array_unique($bad)) . "\n");
    exit(1);
}
echo 'PASS 出站原语仅出现在豁免目录：' . implode(', ', ALLOWED_PREFIXES) . PHP_EOL;
exit(0);
