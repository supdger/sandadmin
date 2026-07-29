<?php

declare(strict_types=1);

/**
 * Reapplies this project's PostgreSQL adaptation after Composer installs the
 * upstream SaiAdmin plugin into server/plugin/saiadmin.
 */

$root = dirname(__DIR__);
$source = $root . '/overrides/saiadmin';
$target = $root . '/plugin/saiadmin';

if (!is_dir($target)) {
    fwrite(STDERR, "SaiAdmin plugin was not installed at {$target}.\n");
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

$converter = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/convert-saiadmin-sql.php');
passthru($converter, $status);
exit($status);
