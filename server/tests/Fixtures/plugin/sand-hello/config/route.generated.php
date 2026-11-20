<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源: server/plugin/sand-hello/contract/openapi.yaml
// 源哈希: sha256:c9bcacd3e31e2b0fba8e8fdeda7f46fd8d97cc7a872ff47704df0012f331c57c
// 生成器: contract-build v1

use plugin\sandhello\app\controller\NoteController;
use Webman\Route;

// Config::loadFromDir 会按 *.php 误包本表（只排除基名 route.php）：
// 那时无 FastRoute collector，直接注册会致命。装载标记只由
// config/route.php 在 Route::load 回调内定义，缺席即返回空数组、
// 无害并入配置树。
if (!defined('SAND_CONTRACT_ROUTE_LOAD')) {
    return [];
}

Route::get('/api/admin/v1/plugin/sand-hello/notes', [NoteController::class, 'index']);
Route::get('/api/admin/v1/plugin/sand-hello/notes/{note_id}', [NoteController::class, 'show']);
