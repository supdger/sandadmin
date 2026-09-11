<?php

declare(strict_types=1);

/**
 * PostgreSQL client options declared in .env must reach getenv() before a
 * worker opens its first database connection.
 *
 * The local macOS validation host uses the PostgreSQL socket directory /tmp.
 * Roll back to DB_HOST = 127.0.0.1 if that socket is unavailable.
 */

$repositoryRoot = dirname(__DIR__, 3);
$assertionCount = 0;

function environmentBootstrapAssert(bool $condition, string $message): void
{
    global $assertionCount;

    $assertionCount++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$bootstrap = file_get_contents($repositoryRoot . '/server/support/bootstrap.php');
$dotenv = file_get_contents($repositoryRoot . '/server/vendor/vlucas/phpdotenv/src/Dotenv.php');
$environment = file_get_contents($repositoryRoot . '/server/.env');

environmentBootstrapAssert($bootstrap !== false, 'Unable to read the Webman bootstrap source');
environmentBootstrapAssert($dotenv !== false, 'Unable to read the dotenv implementation source');
environmentBootstrapAssert($environment !== false, 'Unable to read the host environment file');
environmentBootstrapAssert(str_contains($bootstrap, "file_exists(base_path(false) . '/.env')"), 'Webman bootstrap must load the host environment file');
environmentBootstrapAssert(str_contains($bootstrap, 'Dotenv::createUnsafeMutable(base_path(false))->load()'), 'Webman bootstrap must export environment variables for native clients');
environmentBootstrapAssert(str_contains($dotenv, '->addAdapter(PutenvAdapter::class)'), 'Unsafe dotenv loading must register the getenv adapter');
environmentBootstrapAssert(preg_match_all('/^DB_HOST\\s*=\\s*\\/tmp\\s*$/m', $environment) === 1, 'The validation host must use its PostgreSQL socket directory exactly once');
environmentBootstrapAssert(preg_match_all('/^PGGSSENCMODE\\s*=/mi', $environment) === 0, 'The validation host must not disable PostgreSQL GSS encryption globally');

fwrite(STDOUT, "EnvironmentBootstrapContractTest.php passed ({$assertionCount} assertions)\n");
