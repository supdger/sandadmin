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

use app\controller\DevLoginController;
use app\controller\FileController;
use Sandadmin\Http\AnnouncementController;
use Sandadmin\Http\LifecycleController;
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

// 开发模式本机一键登录：与 /core/login 同前缀，是否放行由控制器按
// SAND_DEV_LOGIN 与真实回环对端 IP 判定，不满足时返回 404 等同路由不存在。
Route::post('/core/dev-login', [DevLoginController::class, 'login']);
// 1.x 前端按 /api/auth 与 /api/admin/v1/auth 两种前缀访问同一开发登录；
// 两条映射共用 DevLoginGate，拒绝转发头伪装的非本机请求。
Route::post('/api/auth/dev-login', [DevLoginController::class, 'devLogin']);
Route::post('/api/admin/v1/auth/dev-login', [DevLoginController::class, 'devLogin']);
Route::get('/api/auth/dev-login/available', [DevLoginController::class, 'available']);
Route::get('/api/admin/v1/auth/dev-login/available', [DevLoginController::class, 'available']);

// 公告：管理与收件人侧端点；权限码（system:announcement:* / authenticated）
// 在 AnnouncementHttp 内判定，路由只挂形状。
Route::get('/api/admin/v1/me/announcements/popup', [AnnouncementController::class, 'popup']);
Route::get('/api/admin/v1/announcements', [AnnouncementController::class, 'index']);
Route::post('/api/admin/v1/announcements', [AnnouncementController::class, 'store']);
Route::get('/api/admin/v1/announcements/{announcement_id}', [AnnouncementController::class, 'show']);
Route::put('/api/admin/v1/announcements/{announcement_id}', [AnnouncementController::class, 'update']);
Route::delete('/api/admin/v1/announcements/{announcement_id}', [AnnouncementController::class, 'destroy']);
Route::post('/api/admin/v1/announcements/{announcement_id}/publish', [AnnouncementController::class, 'publish']);
Route::post('/api/admin/v1/announcements/{announcement_id}/revoke', [AnnouncementController::class, 'revoke']);
Route::post('/api/admin/v1/announcements/{announcement_id}/read', [AnnouncementController::class, 'markRead']);
Route::get('/api/admin/v1/announcements/{announcement_id}/read-stats', [AnnouncementController::class, 'readStats']);

// 生命周期申请详情：对外编号为 public_id（uuid）；契约无取消端点。
Route::get('/api/admin/v1/lifecycle-requests/{request_id}', [LifecycleController::class, 'show']);
