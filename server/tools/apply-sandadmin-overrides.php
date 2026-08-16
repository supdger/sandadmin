<?php

declare(strict_types=1);

/**
 * Reapplies this project's PostgreSQL adaptation after Composer installs the
 * upstream SandAdmin plugin into server/plugin/sandadmin.
 */

$root = dirname(__DIR__);
$source = $root . '/overrides/sandadmin';
$target = $root . '/plugin/sandadmin';

if (!is_dir($target)) {
    fwrite(STDERR, "SandAdmin plugin was not installed at {$target}.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY,
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $relative = substr($file->getPathname(), strlen($source) + 1);
    $destination = $target . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create {$directory}");
    }
    if (!copy($file->getPathname(), $destination)) {
        throw new RuntimeException("Unable to copy {$relative}");
    }
}

// SandAdmin ships PostgreSQL bootstrap SQL directly. No MySQL source is
// converted during an override refresh.
exit(0);
