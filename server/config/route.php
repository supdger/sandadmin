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
use Sandadmin\Http\DashboardController;
use Sandadmin\Http\DictController;
use Sandadmin\Http\HostReadController;
use Sandadmin\Http\LifecycleController;
use Sandadmin\Http\PackageController;
use Sandadmin\Http\MeController;
use Sandadmin\Http\MenuController;
use Sandadmin\Http\NotificationController;
use Sandadmin\Http\OrgController;
use Sandadmin\Http\TaskController;
use Sandadmin\Http\ToolsController;
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

// 消息中心：未读数 = 本人未读通知 + 当前可见未读公告。静态路径先于带编号的路径。
Route::get('/api/admin/v1/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('/api/admin/v1/notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::get('/api/admin/v1/notifications', [NotificationController::class, 'index']);
Route::post('/api/admin/v1/notifications/{notification_id}/read', [NotificationController::class, 'markRead']);

// 生命周期申请详情：对外编号为 public_id（uuid）；契约无取消端点。
Route::get('/api/admin/v1/lifecycle-requests/{request_id}', [LifecycleController::class, 'show']);

// 当前登录人与侧栏。开发本机登录的令牌在 MeController 内解析。
Route::get('/api/admin/v1/auth/password-policy', [MeController::class, 'passwordPolicy']);
Route::post('/api/admin/v1/auth/password/change', [MeController::class, 'changePassword']);
Route::get('/api/admin/v1/me', [MeController::class, 'me']);
Route::get('/api/admin/v1/me/profile', [MeController::class, 'profile']);
Route::put('/api/admin/v1/me/profile', [MeController::class, 'updateProfile']);
Route::get('/api/admin/v1/me/menus', [MeController::class, 'menus']);

// 菜单管理。静态路径先于带键的路径。失败由 MenuController 按 4xx/5xx 返回。
Route::get('/api/admin/v1/menus/tree', [MenuController::class, 'tree']);
Route::post('/api/admin/v1/menus/groups', [MenuController::class, 'storeGroup']);
Route::post('/api/admin/v1/menus/links', [MenuController::class, 'storeLink']);
Route::post('/api/admin/v1/menus/reset', [MenuController::class, 'reset']);
Route::patch('/api/admin/v1/menus/{menu_key}', [MenuController::class, 'update']);
Route::delete('/api/admin/v1/menus/{menu_key}', [MenuController::class, 'destroy']);
Route::post('/api/admin/v1/menus/{menu_key}/move', [MenuController::class, 'move']);
Route::put('/api/admin/v1/menus/{menu_key}/visible', [MenuController::class, 'visible']);

// 工作台：没有待办、动态、提示时返回空列表，页面画空状态而不是报错。
Route::get('/api/admin/v1/dashboard/onboarding', [DashboardController::class, 'onboarding']);
Route::get('/api/admin/v1/dashboard/summary', [DashboardController::class, 'summary']);
Route::get('/api/admin/v1/dashboard/todos/all', [DashboardController::class, 'allTodos']);
Route::get('/api/admin/v1/dashboard/todos', [DashboardController::class, 'todos']);
Route::get('/api/admin/v1/dashboard/charts', [DashboardController::class, 'charts']);
Route::get('/api/admin/v1/dashboard/activities', [DashboardController::class, 'activities']);
Route::get('/api/admin/v1/dashboard/notices', [DashboardController::class, 'notices']);
Route::get('/api/admin/v1/dashboard/shortcuts', [DashboardController::class, 'shortcuts']);

// 用户、部门、角色、岗位。静态路径先于带编号的路径。
Route::get('/api/admin/v1/users', [OrgController::class, 'usersIndex']);
Route::post('/api/admin/v1/users', [OrgController::class, 'usersStore']);
Route::post('/api/admin/v1/users/export', [OrgController::class, 'usersExport']);
Route::get('/api/admin/v1/users/{user_id}', [OrgController::class, 'usersShow']);
Route::put('/api/admin/v1/users/{user_id}', [OrgController::class, 'usersUpdate']);
Route::delete('/api/admin/v1/users/{user_id}', [OrgController::class, 'usersDestroy']);
Route::put('/api/admin/v1/users/{user_id}/status', [OrgController::class, 'usersStatus']);
Route::get('/api/admin/v1/users/{user_id}/disable-check', [OrgController::class, 'usersDisableCheck']);
Route::post('/api/admin/v1/users/{user_id}/reset-password', [OrgController::class, 'usersResetPassword']);
Route::put('/api/admin/v1/users/{user_id}/roles', [OrgController::class, 'usersRoles']);
Route::get('/api/admin/v1/org/user-options', [OrgController::class, 'userOptions']);
Route::get('/api/admin/v1/org/dept-options', [OrgController::class, 'deptOptions']);
Route::get('/api/admin/v1/depts/tree', [OrgController::class, 'deptsTree']);
Route::post('/api/admin/v1/depts', [OrgController::class, 'deptsStore']);
Route::put('/api/admin/v1/depts/{dept_id}', [OrgController::class, 'deptsUpdate']);
Route::get('/api/admin/v1/depts/{dept_id}/delete-check', [OrgController::class, 'deptsDeleteCheck']);
Route::delete('/api/admin/v1/depts/{dept_id}', [OrgController::class, 'deptsDestroy']);
Route::get('/api/admin/v1/roles', [OrgController::class, 'rolesIndex']);
Route::post('/api/admin/v1/roles', [OrgController::class, 'rolesStore']);
Route::get('/api/admin/v1/roles/{role_id}', [OrgController::class, 'rolesShow']);
Route::put('/api/admin/v1/roles/{role_id}', [OrgController::class, 'rolesUpdate']);
Route::delete('/api/admin/v1/roles/{role_id}', [OrgController::class, 'rolesDestroy']);
Route::post('/api/admin/v1/roles/{role_id}/copy', [OrgController::class, 'rolesCopy']);
Route::put('/api/admin/v1/roles/{role_id}/status', [OrgController::class, 'rolesStatus']);
Route::get('/api/admin/v1/roles/{role_id}/permissions', [OrgController::class, 'rolesPermissions']);
Route::put('/api/admin/v1/roles/{role_id}/permissions', [OrgController::class, 'rolesSavePermissions']);
Route::get('/api/admin/v1/permission-tree', [OrgController::class, 'permissionTree']);
Route::get('/api/admin/v1/posts', [OrgController::class, 'postsIndex']);
Route::post('/api/admin/v1/posts', [OrgController::class, 'postsStore']);
Route::put('/api/admin/v1/posts/{post_id}', [OrgController::class, 'postsUpdate']);
Route::delete('/api/admin/v1/posts/{post_id}', [OrgController::class, 'postsDestroy']);

// 选项组与选项。
Route::get('/api/admin/v1/dicts', [DictController::class, 'index']);
Route::post('/api/admin/v1/dicts', [DictController::class, 'store']);
Route::get('/api/admin/v1/dicts/{type_id}', [DictController::class, 'show']);
Route::put('/api/admin/v1/dicts/{type_id}', [DictController::class, 'update']);
Route::delete('/api/admin/v1/dicts/{type_id}', [DictController::class, 'destroy']);
Route::post('/api/admin/v1/dicts/{type_id}/items', [DictController::class, 'storeItem']);
Route::put('/api/admin/v1/dicts/items/sort', [DictController::class, 'sortItems']);
Route::put('/api/admin/v1/dicts/items/{item_id}', [DictController::class, 'updateItem']);
Route::delete('/api/admin/v1/dicts/items/{item_id}', [DictController::class, 'destroyItem']);
Route::put('/api/admin/v1/dicts/items/{item_id}/status', [DictController::class, 'itemStatus']);

// 插件与系统更新。编译版和生产环境拒绝在线安装；定时任务时间不合法时返回 422 TASK_SCHEDULE_INVALID。
Route::get('/api/admin/v1/plugins/overview', [HostReadController::class, 'pluginsOverview']);
Route::get('/api/admin/v1/plugins/market', [HostReadController::class, 'pluginsMarket']);
Route::get('/api/admin/v1/plugins/installed', [HostReadController::class, 'pluginsInstalled']);
Route::post('/api/admin/v1/plugins/upgrade-all', [PackageController::class, 'upgradeAll']);
Route::post('/api/admin/v1/plugins/upload', [PackageController::class, 'upload']);
Route::get('/api/admin/v1/plugins/{plugin_code}', [PackageController::class, 'show']);
Route::post('/api/admin/v1/plugins/{plugin_code}/install', [PackageController::class, 'install']);
Route::post('/api/admin/v1/plugins/{plugin_code}/upgrade', [PackageController::class, 'upgrade']);
Route::post('/api/admin/v1/plugins/{plugin_code}/uninstall', [PackageController::class, 'uninstall']);
Route::get('/api/admin/v1/system/update/status', [HostReadController::class, 'updateStatus']);
Route::post('/api/admin/v1/system/update/check', [HostReadController::class, 'updateCheck']);
Route::post('/api/admin/v1/system/update/start', [PackageController::class, 'startUpdate']);

// 监控和工具批次（M1/M2/J10/J11、系统设置、全局搜索）。
// 静态段（module-options、export、groups）必须排在 {log_id}/{group} 参数段前面。
Route::get('/api/admin/v1/monitor/server', [ToolsController::class, 'monitorServer']);
Route::get('/api/admin/v1/monitor/series', [ToolsController::class, 'monitorSeries']);
Route::post('/api/admin/v1/login-logs/export', [ToolsController::class, 'loginLogExport']);
Route::get('/api/admin/v1/login-logs', [ToolsController::class, 'loginLogs']);
Route::get('/api/admin/v1/login-logs/{log_id}', [ToolsController::class, 'loginLogShow']);
Route::post('/api/admin/v1/audit-logs/export', [ToolsController::class, 'auditLogExport']);
Route::get('/api/admin/v1/audit-logs/module-options', [ToolsController::class, 'auditLogModuleOptions']);
Route::get('/api/admin/v1/audit-logs', [ToolsController::class, 'auditLogs']);
Route::get('/api/admin/v1/audit-logs/{log_id}', [ToolsController::class, 'auditLogShow']);
Route::get('/api/admin/v1/scheduled-tasks', [ToolsController::class, 'scheduledTasks']);
Route::put('/api/admin/v1/scheduled-tasks/{code}', [ToolsController::class, 'scheduledTaskUpdate']);
Route::post('/api/admin/v1/scheduled-tasks/{code}/run', [ToolsController::class, 'scheduledTaskRun']);
Route::get('/api/admin/v1/scheduled-tasks/{code}/runs', [ToolsController::class, 'scheduledTaskRuns']);
Route::get('/api/admin/v1/settings/groups', [ToolsController::class, 'settingGroups']);
Route::post('/api/admin/v1/settings/logo', [ToolsController::class, 'settingLogo']);
Route::get('/api/admin/v1/settings/{group}', [ToolsController::class, 'settingShow']);
Route::put('/api/admin/v1/settings/{group}', [ToolsController::class, 'settingUpdate']);
Route::get('/api/admin/v1/search', [ToolsController::class, 'search']);
