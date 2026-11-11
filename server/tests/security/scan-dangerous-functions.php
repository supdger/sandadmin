<?php
// 用法：php tests/security/scan-dangerous-functions.php  （在 server/ 下）
// 扫描宿主自有 PHP（排除 vendor/runtime/plugin 运行副本），proc_open/exec/shell_exec/passthru/system/popen 仅允许白名单文件。
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allow = array_values(array_filter(array_map('trim', file(__DIR__ . '/dangerous-functions-allowlist.txt')), fn ($l) => $l !== '' && $l[0] !== '#'));
$skip = '#^(vendor|runtime|plugin|tests|node_modules)/#';
$bad = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $rel = substr($file->getPathname(), strlen($root) + 1);
    if (preg_match($skip, $rel) || !preg_match('/\.php$|^webman$/', $rel)) {
        continue;
    }
    $tokens = token_get_all((string) file_get_contents($file->getPathname()));
    foreach ($tokens as $i => $t) {
        if (is_array($t) && $t[0] === T_STRING && preg_match('/^(proc_open|shell_exec|passthru|exec|system|popen|pcntl_exec)$/i', $t[1])) {
            for ($j = $i - 1; $j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE; $j--);
            $prevSig = $tokens[$j] ?? null;
            if (is_array($prevSig) && in_array($prevSig[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue; // 方法调用/定义，不是内建函数
            }
            for ($k = $i + 1; isset($tokens[$k]) && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE; $k++);
            if (($tokens[$k] ?? null) !== '(') {
                continue;
            }
            if (!in_array($rel, $allow, true)) {
                $bad[] = "$rel:{$t[2]} {$t[1]}()";
            }
        } elseif ($t === '`') {
            if (!in_array($rel, $allow, true)) {
                $bad[] = "$rel: 反引号命令执行";
            }
        }
    }
}
if ($bad) {
    fwrite(STDERR, "FAIL 危险函数出现在白名单外：\n  " . implode("\n  ", array_unique($bad)) . "\n");
    exit(1);
}
echo "PASS 危险函数仅出现在白名单文件：" . implode(', ', $allow) . PHP_EOL;
