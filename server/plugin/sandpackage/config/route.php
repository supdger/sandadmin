<?php

use Webman\Route;

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

