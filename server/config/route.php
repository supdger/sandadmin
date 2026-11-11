<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\controller\FileController;
use Sandadmin\Http\TaskController;
use Webman\Route;

// Public controllers must opt in explicitly; installed plugins register their
// own routes through Webman's plugin configuration.
Route::disableDefaultRoute();

// 1.x 契约前缀 /api/admin/v1。/api/** 的默认鉴权由统一中间件收口（B2），
// 这里不做 class_exists 条件挂载；文件可见性在 FileHttp/FileService 内判定。
Route::post('/api/admin/v1/files', [FileController::class, 'store']);
Route::get('/api/admin/v1/files', [FileController::class, 'index']);
Route::get('/api/admin/v1/files/{file_id}', [FileController::class, 'show']);
Route::delete('/api/admin/v1/files/{file_id}', [FileController::class, 'destroy']);
Route::get('/api/admin/v1/files/{file_id}/download', [FileController::class, 'download']);
Route::post('/api/admin/v1/files/cleanup', [FileController::class, 'cleanup']);

// 这里不做 class_exists 条件挂载；任务可见性在控制器内按发起人与
// system:task:index 等效权限判定。
Route::get('/api/admin/v1/tasks', [TaskController::class, 'index']);
Route::get('/api/admin/v1/tasks/{task_id}', [TaskController::class, 'show']);
Route::get('/api/admin/v1/tasks/{task_id}/download', [TaskController::class, 'download']);
