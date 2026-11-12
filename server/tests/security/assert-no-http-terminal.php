<?php
// 安全 P0-F5（宿主前端）：管理端不得再请求 HTTP 在线终端或提供终端入口。
// 用法：php server/tests/security/assert-no-http-terminal.php（仓库根目录）
declare(strict_types=1);

$root = dirname(__DIR__, 3) . '/sandadmin-artd/src';
$fail = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file instanceof SplFileInfo || !preg_match('/\.(ts|vue|js)$/', $file->getFilename())) {
        continue;
    }
    $src = (string) file_get_contents($file->getPathname());
    $rel = substr($file->getPathname(), strlen($root) + 1);
    // 去掉注释后再查，避免说明文字误报
    $code = preg_replace('#/\*.*?\*/|(?<!:)//[^\n]*#s', '', $src) ?? $src;
    if (preg_match("#['\"`][^'\"`]*/index/terminal#", $code)) {
        $fail[] = "$rel: 引用 /index/terminal";
    }
    if (preg_match('/text\/event-stream/', $code) && str_contains($rel, 'sandpackage')) {
        $fail[] = "$rel: 终端 SSE 请求";
    }
    if (str_contains($code, '依赖安装终端')) {
        $fail[] = "$rel: 终端入口按钮";
    }
}
$storePath = "$root/views/plugin/sandpackage/store/terminal.ts";
// 0.2.25 起载荷整体移除终端 store；文件已删比「不发 fetch」更强，不存在即跳过该项检查
if (is_file($storePath)) {
    $store = (string) file_get_contents($storePath);
    if (preg_match('/\bfetch\s*\(/', $store)) {
        $fail[] = 'store/terminal.ts: 仍发起 fetch';
    }
}
if ($fail) {
    fwrite(STDERR, "FAIL\n  " . implode("\n  ", $fail) . "\n");
    exit(1);
}
echo "PASS 前端无 HTTP 终端请求与入口\n";
