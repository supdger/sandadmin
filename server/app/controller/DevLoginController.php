<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\controller;

use app\support\DevLoginGate;
use plugin\sandadmin\app\model\system\SystemOperLog;
use plugin\sandadmin\app\model\system\SystemUser;
use plugin\sandadmin\basic\OpenController;
use plugin\sandadmin\utils\ForcedPasswordChange;
use plugin\sandadmin\utils\JwtSecretGuard;
use support\exception\PageNotFoundException;
use support\Request;
use support\Response;
use Tinywan\Jwt\JwtToken;
use Webman\Event\Event;

/**
 * 开发模式本机一键登录：SAND_DEV_LOGIN=1 且真实对端 IP 为回环地址时，
 * 免口令给开发账号签发与正常登录同构的令牌。任一条件不满足抛
 * PageNotFoundException，与路由不存在响应逐字节一致，消除可探测指纹；
 * 生产环境不得开启 SAND_DEV_LOGIN。
 * 对端 IP 只取 getRemoteIp()（Workerman 真实连接地址），不信代理头；
 * 请求出现 X-Forwarded-For / X-Real-IP / Forwarded 时一律拒绝，
 * 因为此时回环地址可能是伪造的代理终点。
 */
final class DevLoginController extends OpenController
{
    /**
     * 与插件 LoginController 同机制：插件全局中间件按该属性放行本方法。
     */
    protected array $noNeedLogin = ['login', 'devLogin', 'available'];

    /** 旧宿主路径 /core/dev-login */
    public function login(Request $request): Response
    {
        // 抛与路由不存在相同的异常，响应逐字节一致以消除可探测指纹
        if (!DevLoginGate::allowed($request)) {
            throw new PageNotFoundException();
        }

        return $this->issueToken($request);
    }

    /** 1.x 前端开发登录 /api/auth/dev-login（亦挂 /api/admin/v1/auth/dev-login） */
    public function devLogin(Request $request): Response
    {
        if (!DevLoginGate::allowed($request)) {
            throw new PageNotFoundException();
        }
        try {
            return $this->issueToken($request);
        } catch (PageNotFoundException $error) {
            throw $error;
        } catch (\Throwable $error) {
            error_log('开发登录旧账号表不可用，改发本机令牌 ' . $error::class);

            return $this->localToken();
        }
    }

    /**
     * 开发登录可用性探测：不满足条件时回 available=false，
     * 前端据此隐藏一键进入入口；不发牌所以不抛 404。
     */
    public function available(Request $request): Response
    {
        return $this->success([
            'available' => DevLoginGate::allowed($request),
            'username' => DevLoginGate::DEV_USERNAME,
        ]);
    }

    /** 与 SystemUserLogic::login 校验密码通过后的发牌流程保持一致 */
    private function issueToken(Request $request): Response
    {
        try {
            JwtSecretGuard::assertRuntimeConfig();
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage());
        }
        $user = SystemUser::where('username', DevLoginGate::DEV_USERNAME)->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->localToken();
        }
        if ($user->status === 2) {
            return $this->fail('您已被禁止登录!');
        }
        $user->login_time = date('Y-m-d H:i:s');
        $user->login_ip = $request->getRemoteIp();
        $user->save();

        $accessExp = config('plugin.sandadmin.saithink.access_exp', 3 * 3600);
        $token = JwtToken::generateToken([
            'access_exp' => $accessExp,
            'id' => $user->id,
            'username' => $user->username,
            'type' => $request->post('type', 'pc'),
            'plat' => 'sandadmin',
        ]);

        // 与正常登录同一事件、同一负载键；message 记「开发本机登录」供审计区分
        Event::emit('user.login', [
            'username' => $user->username,
            'status' => 1,
            'message' => '开发本机登录',
            'admin_id' => $user->id,
        ]);
        $this->recordOperLog($request, (string) $user->username);

        // 旧库没有该列时视为未置位；前端据此跳转强制改密页
        $token['must_change_password'] =
            (int) ($user->toArray()[ForcedPasswordChange::COLUMN] ?? 0) === 1;

        return $this->success($token);
    }

    /** 纯 1.x 库没有旧账号表时，仍发一枚本机令牌。审计文案与正常开发登录相同。 */
    private function localToken(): Response
    {
        try {
            Event::emit('user.login', [
                'username' => DevLoginGate::DEV_USERNAME,
                'status' => 1,
                'message' => '开发本机登录',
                'admin_id' => 0,
            ]);
        } catch (\Throwable $error) {
            error_log('开发本机登录事件失败 ' . $error::class);
        }

        return $this->success([
            'token' => bin2hex(random_bytes(16)),
            'mustChangePassword' => false,
        ]);
    }

    /** 操作日志表缺失（纯 1.x 库未装 0.x 插件表）时不阻断发牌，仅记录后端日志 */
    private function recordOperLog(Request $request, string $username): void
    {
        try {
            SystemOperLog::create([
                'username' => $username,
                'app' => 'sandadmin',
                'method' => $request->method(),
                'router' => $request->uri(),
                'service_name' => '开发本机登录',
                'ip' => $request->getRemoteIp(),
                'request_data' => '开发本机一键登录',
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $error) {
            error_log('开发登录审计写入失败 ' . $error::class . ': ' . $error->getMessage());
        }
    }
}
