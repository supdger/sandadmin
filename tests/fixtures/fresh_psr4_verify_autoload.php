<?php

declare(strict_types=1);

/**
 * Runs in a process with no recovery class previously included. The host
 * Composer loader is intentionally used unchanged except for the staging
 * prefix being prepended, which models the production host's PSR-4 mapping.
 */
$stageRoot = getenv('SANDPACKAGE_STAGE_ROOT');
$hostAutoload = getenv('SANDPACKAGE_HOST_AUTOLOAD');
if (!is_string($stageRoot) || $stageRoot === '' || !is_string($hostAutoload) || !is_file($hostAutoload)) {
    throw new RuntimeException('fresh autoload fixture requires staging root and host autoload');
}

$loader = require $hostAutoload;
if (!is_object($loader) || !method_exists($loader, 'addPsr4')) {
    throw new RuntimeException('host Composer PSR-4 loader is unavailable');
}
$loader->addPsr4('plugin\\sandpackage\\', $stageRoot . '/server/plugin/sandpackage', true);

// Route files are normally loaded after Webman's collector is initialized.
// An empty collector makes this fresh CLI process equivalent for registration
// without loading host routes, services, or application runtime state.
\Webman\Route::load([]);
require $stageRoot . '/server/plugin/sandpackage/config/route.php';

$classes = [
    'plugin\\sandpackage\\app\\controller\\InstallController',
    'plugin\\sandpackage\\app\\logic\\InstallLogic',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradeIdentityBinding',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradeRecoveryVerifier',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradePackageIdentity',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradeRecoveryInspector',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradeRecoveryCoordinator',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradeRecoveryFileTransaction',
    'plugin\\sandpackage\\app\\logic\\FailedUpgradeRecoveryAudit',
    'plugin\\sandpackage\\command\\Recover',
];
foreach ($classes as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('PSR-4 did not load ' . $class);
    }
}

$bindingClass = 'plugin\\sandpackage\\app\\logic\\FailedUpgradeIdentityBinding';
$verifierClass = 'plugin\\sandpackage\\app\\logic\\FailedUpgradeRecoveryVerifier';
$binding = new $bindingClass(
    str_repeat('a', 64),
    'neutral-fixture-package-20260908120000-abcdef123456',
    str_repeat('b', 64),
    str_repeat('c', 64),
    str_repeat('d', 64),
    str_repeat('e', 64),
    str_repeat('f', 64),
    str_repeat('1', 64),
    str_repeat('2', 64),
);
$result = (new $verifierClass())->verify('{}', new stdClass(), $binding);
if (($result['status'] ?? null) !== 'FAILED_UPGRADE_RECOVERY_BLOCKED') {
    throw new RuntimeException('fresh verifier invocation did not fail closed');
}

echo "fresh PSR-4 route/controller/verify fixture passed\n";
