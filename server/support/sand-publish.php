<?php

declare(strict_types=1);

// 本入口不加载 .env：插件 Install::install 可能触库，最早处默认关闭 libpq GSSAPI 协商。
require_once __DIR__ . '/gssencmode.php';

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "sand:publish skipped: vendor/autoload.php not found, run composer install first\n");
    exit(0);
}
require $autoload;

$installed = json_decode((string) file_get_contents($root . '/vendor/composer/installed.json'), true);
$packages = is_array($installed['packages'] ?? null) ? $installed['packages'] : [];
$published = [];
$failed = [];

foreach ($packages as $package) {
    foreach (array_keys($package['autoload']['psr-4'] ?? []) as $namespace) {
        $class = rtrim($namespace, '\\') . '\\Install';
        if (!defined($class . '::WEBMAN_PLUGIN') || !constant($class . '::WEBMAN_PLUGIN') || !is_callable($class . '::install')) {
            continue;
        }
        try {
            $class::install();
            $published[] = $package['name'] ?? $class;
        } catch (Throwable $e) {
            $failed[] = ($package['name'] ?? $class) . ': ' . $e->getMessage();
        }
    }
}

foreach ($failed as $message) {
    fwrite(STDERR, "[sand:publish] failed: {$message}\n");
}
fwrite(STDOUT, '[sand:publish] ' . ($published ? 'published ' . implode(', ', array_unique($published)) : 'no webman plugins found') . "\n");
exit($failed === [] ? 0 : 1);
