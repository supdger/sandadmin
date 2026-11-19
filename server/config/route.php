<?php

declare(strict_types=1);

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

// SAND-DEV-BEGIN
use app\controller\DevLoginController;
use Sandadmin\Http\McpController;
use Sandadmin\Infra\DevBuild;
use Sandadmin\Module\DemoNote\SseDemoController;
// SAND-DEV-END
use Sandadmin\Http\Middleware\CorsMiddleware;
use Sandadmin\Http\Middleware\SecurityHeadersMiddleware;
use Sandadmin\Http\SpaFallback;
use Webman\Route;

// Public controllers must opt in explicitly; installed plugins register their
// own routes through Webman's plugin configuration.
Route::disableDefaultRoute();

// SAND-DEV-BEGIN
// 1.x 前端按 /api/auth 与 /api/admin/v1/auth 两种前缀访问同一开发登录；
// 两条映射共用 DevLoginGate，拒绝转发头伪装的非本机请求。
// 整条链只在开发构建存在：生产制品没有 .sand-dev-build 标记，路由不注册。
if (DevBuild::enabled()) {
    Route::post('/api/auth/dev-login', [DevLoginController::class, 'devLogin']);
    Route::post('/api/admin/v1/auth/dev-login', [DevLoginController::class, 'devLogin']);
    Route::get('/api/auth/dev-login/available', [DevLoginController::class, 'available']);
    Route::get('/api/admin/v1/auth/dev-login/available', [DevLoginController::class, 'available']);

    // 开发构建 MCP 端点（无状态 Streamable HTTP）：路由只按构建标记挂出，
    // 令牌、回环、Origin 三道门槛在 McpController 内收口，缺一按 404 处理。
    Route::post('/api/admin/mcp', [McpController::class, 'handle']);

    // 流式基础层演示/压测端点：类在 DemoNote 内，整目录随生产包剔除；
    // slow_ms 模拟慢上游，ping 给普通请求延迟对照。
    Route::get('/api/dev/stream/sse', [SseDemoController::class, 'events']);
    Route::get('/api/dev/stream/ping', [SseDemoController::class, 'ping']);
}
// SAND-DEV-END

// 1.x 常驻路由全部由 contract/openapi.yaml 经 tools/contract-build 生成；
// 本文件哨兵段外禁止手写 Route:: 调用（contract-build --check 会打回）。
// 静态路径先于参数路径的注册语义由契约 paths 的书写顺序承载。
// 装载标记：本文件只在 Route::load 的调度回调内被包含（Config 装载排除
// 基名 route.php），生成表据此判定当前处于注册期而非配置装载期。
defined('SAND_CONTRACT_ROUTE_LOAD') || define('SAND_CONTRACT_ROUTE_LOAD', true);
require __DIR__ . '/route.generated.php';

// SPA 回退：非 /api、非真实文件的 GET 返回 index.html（见 SpaFallback 注释）；
// /api/** 未命中与非 GET 在其内重抛 404，与反代部署不冲突。
// fallback 回调不经全局中间件（webman getFallback 固定 withGlobalMiddleware=false），
// 安全头与 CORS 改挂路由级：SPA/404 响应同样输出 N3 头；v1 的 OPTIONS 预检
// 在 FastRoute 层因方法未注册即落 fallback，只有这里挂 CorsMiddleware 预检才可达。
Route::fallback([SpaFallback::class, 'respond'])
    ->middleware([SecurityHeadersMiddleware::class, CorsMiddleware::class]);
