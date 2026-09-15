<?php

use Webman\Route;

Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'registerExisting']);
Route::post('/app/sandpackage/install/registerExisting', [plugin\sandpackage\app\controller\InstallController::class, 'registerExisting']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'discardCandidate']);
Route::post('/app/sandpackage/install/discardCandidate', [plugin\sandpackage\app\controller\InstallController::class, 'discardCandidate']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'verifyFailedUpgradeRecovery']);
Route::post('/app/sandpackage/install/verifyFailedUpgradeRecovery', [plugin\sandpackage\app\controller\InstallController::class, 'verifyFailedUpgradeRecovery']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'inspectFailedUpgradeRecovery']);
Route::post('/app/sandpackage/install/inspectFailedUpgradeRecovery', [plugin\sandpackage\app\controller\InstallController::class, 'inspectFailedUpgradeRecovery']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'restoreRuntimeFromBackup']);
Route::post('/app/sandpackage/install/restoreRuntimeFromBackup', [plugin\sandpackage\app\controller\InstallController::class, 'restoreRuntimeFromBackup']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'inspectInterruptedPreUpgradeBackup']);
Route::post('/app/sandpackage/install/inspectInterruptedPreUpgradeBackup', [plugin\sandpackage\app\controller\InstallController::class, 'inspectInterruptedPreUpgradeBackup']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'restoreInterruptedPreUpgradeBackup']);
Route::post('/app/sandpackage/install/restoreInterruptedPreUpgradeBackup', [plugin\sandpackage\app\controller\InstallController::class, 'restoreInterruptedPreUpgradeBackup']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'prepareFailedUpgradeReplacement']);
Route::post('/app/sandpackage/install/prepareFailedUpgradeReplacement', [plugin\sandpackage\app\controller\InstallController::class, 'prepareFailedUpgradeReplacement']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'replaceFailedUpgradeCandidate']);
Route::post('/app/sandpackage/install/replaceFailedUpgradeCandidate', [plugin\sandpackage\app\controller\InstallController::class, 'replaceFailedUpgradeCandidate']);
Route::disableDefaultRoute([plugin\sandpackage\app\controller\InstallController::class, 'retryFailedUpgrade']);
Route::post('/app/sandpackage/install/retryFailedUpgrade', [plugin\sandpackage\app\controller\InstallController::class, 'retryFailedUpgrade']);

Route::group('/tool/install', function () {
    // 商店代理接口（在线安装）
    Route::get('/online/appList', [plugin\sandpackage\app\controller\InstallController::class, 'appList']);
    Route::get('/online/storeCaptcha', [plugin\sandpackage\app\controller\InstallController::class, 'storeCaptcha']);
    Route::post('/online/storeLogin', [plugin\sandpackage\app\controller\InstallController::class, 'storeLogin']);
    Route::get('/online/storeUserInfo', [plugin\sandpackage\app\controller\InstallController::class, 'storeUserInfo']);
    Route::get('/online/storePurchasedApps', [plugin\sandpackage\app\controller\InstallController::class, 'storePurchasedApps']);
    Route::get('/online/storeAppVersions', [plugin\sandpackage\app\controller\InstallController::class, 'storeAppVersions']);
    Route::post('/online/storeDownloadApp', [plugin\sandpackage\app\controller\InstallController::class, 'storeDownloadApp']);
});
