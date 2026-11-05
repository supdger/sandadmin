<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/job-cli-autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Sandadmin\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});
