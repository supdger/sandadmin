<?php

declare(strict_types=1);

// behavior-test-gate: static-rule

$consumerRoot = sys_get_temp_dir() . '/sandadmin-composer-install-' . bin2hex(random_bytes(6));

function installContractFail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function base_path(string $path = ''): string
{
    global $consumerRoot;
    return $path === '' ? $consumerRoot : $consumerRoot . '/' . ltrim($path, '/');
}

function copy_dir(string $source, string $destination, bool $overwrite = false): void
{
    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        installContractFail("unable to create {$destination}");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $target = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                installContractFail("unable to create {$target}");
            }
            continue;
        }
        if (!$overwrite && is_file($target)) {
            continue;
        }
        if (!copy($item->getPathname(), $target)) {
            installContractFail("unable to copy {$target}");
        }
    }
}

function remove_dir(string $path): void
{
    if (!is_dir($path)) {
        @unlink($path);
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

mkdir($consumerRoot . '/support', 0777, true);
mkdir($consumerRoot . '/config', 0777, true);
mkdir($consumerRoot . '/config/plugin/webman/channel', 0777, true);
mkdir($consumerRoot . '/plugin/example-business', 0777, true);
mkdir($consumerRoot . '/storage/sandpackage', 0777, true);
file_put_contents($consumerRoot . '/.env', "APP_ENV=contract\n");
file_put_contents($consumerRoot . '/plugin/example-business/owned.txt', "business\n");
file_put_contents($consumerRoot . '/storage/sandpackage/owned.txt', "state\n");
file_put_contents(
    $consumerRoot . '/support/Request.php',
    "<?php\nnamespace support;\nclass Request extends \\\\Webman\\\\Http\\\\Request\n{\n}\n"
);
file_put_contents($consumerRoot . '/config/bootstrap.php', "<?php\nreturn [];\n");
file_put_contents($consumerRoot . '/config/route.php', "<?php\n");
file_put_contents($consumerRoot . '/config/process.php', "<?php\nreturn ['webman' => ['listen' => 'http://0.0.0.0:8787']];\n");
file_put_contents($consumerRoot . '/config/plugin/webman/channel/process.php', "<?php\nreturn ['server' => ['listen' => 'frame://0.0.0.0:2206']];\n");

require dirname(__DIR__, 2) . '/Install.php';

try {
    SandAdmin\Install::install();
    SandAdmin\Install::install(false);

    foreach ([
        'plugin/sandadmin/config/route.php',
        'plugin/sandadmin/db/sandadmin-pure.pgsql',
        'plugin/sandpackage/app/logic/InstallLogic.php',
    ] as $required) {
        if (!is_file($consumerRoot . '/' . $required)) {
            installContractFail("missing installed payload {$required}");
        }
    }

    $request = file_get_contents($consumerRoot . '/support/Request.php');
    if (substr_count((string) $request, 'public function more') !== 1) {
        installContractFail('Request::more must be installed exactly once');
    }
    if (substr_count((string) file_get_contents($consumerRoot . '/config/bootstrap.php'), 'Webman\\ThinkOrm\\ThinkOrm::class') !== 1) {
        installContractFail('ThinkORM bootstrap must be installed exactly once');
    }
    if (substr_count((string) file_get_contents($consumerRoot . '/config/route.php'), 'Route::disableDefaultRoute();') !== 1) {
        installContractFail('default route hardening must be installed exactly once');
    }
    if (!is_file($consumerRoot . '/config/database.php') || !is_file($consumerRoot . '/config/think-orm.php')
        || !is_file($consumerRoot . '/config/think-cache.php')) {
        installContractFail('missing PostgreSQL/ThinkORM/cache config templates');
    }
    if (!str_contains((string) file_get_contents($consumerRoot . '/config/process.php'), "env('SANDADMIN_SERVER_PORT', 8787)")
        || !str_contains((string) file_get_contents($consumerRoot . '/config/plugin/webman/channel/process.php'), "env('SANDADMIN_CHANNEL_PORT', 2206)")) {
        installContractFail('consumer ports are not environment-configurable');
    }
    if (file_get_contents($consumerRoot . '/.env') !== "APP_ENV=contract\n") {
        installContractFail('consumer .env was modified');
    }
    if (!is_file($consumerRoot . '/plugin/example-business/owned.txt')) {
        installContractFail('business plugin was modified');
    }
    if (!is_file($consumerRoot . '/storage/sandpackage/owned.txt')) {
        installContractFail('persistent SandPackage state was modified');
    }
    if (is_dir($consumerRoot . '/sandadmin-artd')) {
        installContractFail('Composer install copied the frontend');
    }

    file_put_contents(
        $consumerRoot . '/config/process.php',
        str_replace(
            "'http://0.0.0.0:' . env('SANDADMIN_SERVER_PORT', 8787)",
            "'http://127.0.0.1:9000'",
            (string) file_get_contents($consumerRoot . '/config/process.php'),
        ),
    );
    SandAdmin\Install::uninstall();
    if (str_contains((string) file_get_contents($consumerRoot . '/config/think-cache.php'), 'plugin\\sandadmin')) {
        installContractFail('uninstall retained the SandAdmin file-cache class reference');
    }
    if (str_contains((string) file_get_contents($consumerRoot . '/config/process.php'), 'SANDADMIN_SERVER_PORT')
        || str_contains((string) file_get_contents($consumerRoot . '/config/plugin/webman/channel/process.php'), 'SANDADMIN_CHANNEL_PORT')) {
        installContractFail('uninstall retained SandAdmin port configuration');
    }
    if (!str_contains((string) file_get_contents($consumerRoot . '/config/process.php'), 'http://127.0.0.1:9000')) {
        installContractFail('uninstall overwrote a consumer-managed HTTP listener');
    }
    if (!is_file($consumerRoot . '/plugin/example-business/owned.txt')) {
        installContractFail('uninstall removed a business plugin');
    }
    if (!is_file($consumerRoot . '/storage/sandpackage/owned.txt')) {
        installContractFail('uninstall removed persistent SandPackage state');
    }

    echo "PASS: Composer installer copies only owned backend payload and preserves consumer state\n";
} finally {
    remove_dir($consumerRoot);
}
