<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\controller;

use plugin\sandadmin\app\model\system\SystemUser;
use plugin\sandadmin\basic\OpenController;
use plugin\sandadmin\utils\ForcedPasswordChange;
use plugin\sandadmin\utils\JwtSecretGuard;
use support\Request;
use support\Response;
use Tinywan\Jwt\JwtToken;
use Webman\Event\Event;

/**
 * 开发模式本机一键登录：SAND_DEV_LOGIN=1 且真实对端 IP 为回环地址时，
 * 免口令给开发账号签发与正常登录同构的令牌。任一条件不满足一律 404，
 * 不暴露入口存在性；生产环境不得开启 SAND_DEV_LOGIN。
 * 对端 IP 只取 getRemoteIp()（Workerman 真实连接地址），不信代理头。
 */
final class DevLoginController extends OpenController
{
    private const DEV_USERNAME = 'admin';

    private const LOOPBACK_IPS = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];

    /**
     * 与插件 LoginController 同机制：插件全局中间件按该属性放行本方法。
     */
    protected array $noNeedLogin = ['login'];

    public function login(Request $request): Response
    {
        if (env('SAND_DEV_LOGIN') !== '1' || !$this->isLoopback($request->getRemoteIp())) {
            return new Response(404);
        }

        // 与 SystemUserLogic::login 校验密码通过后的发牌流程保持一致
        try {
            JwtSecretGuard::assertRuntimeConfig();
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage());
        }
        $user = SystemUser::where('username', self::DEV_USERNAME)->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->fail('账号或密码错误，请重新输入!');
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

        // 与正常登录同一事件、同一负载键，登录日志等监听方照常工作
        Event::emit('user.login', [
            'username' => $user->username,
            'status' => 1,
            'message' => '登录成功',
            'admin_id' => $user->id,
        ]);

        // 旧库没有该列时视为未置位；前端据此跳转强制改密页
        $token['must_change_password'] =
            (int) ($user->toArray()[ForcedPasswordChange::COLUMN] ?? 0) === 1;

        return $this->success($token);
    }

    private function isLoopback(string $ip): bool
    {
        return in_array($ip, self::LOOPBACK_IPS, true);
    }
}
