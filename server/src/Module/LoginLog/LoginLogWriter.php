<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\LoginLog;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Db\DbalConnection;

/**
 * user.login 事件的 1.x 登录日志写库：插件监听器写的是旧版
 * sand_system_login_log 形状（username/ip_location/os/browser），与
 * 1.x 列（username_input/succeeded/reason/device_label…）对不上。
 * 这里按 1.x 列补写一行；写失败只记错误日志，不影响登录本身。
 */
final class LoginLogWriter
{
    /** @param array<array-key, mixed> $item 事件负载：username/status/message/admin_id? */
    public static function record(array $item): void
    {
        $request = function_exists('request') ? request() : null;
        $ip = '127.0.0.1';
        $ua = '';
        if ($request instanceof \Webman\Http\Request) {
            $ip = $request->getRemoteIp();
            $agent = $request->header('user-agent', '');
            $ua = is_string($agent) ? $agent : '';
        }

        $username = is_string($item['username'] ?? null) ? $item['username'] : '';
        if ($username === '') {
            return;
        }
        $succeeded = ($item['status'] ?? 1) === 1;
        $message = is_string($item['message'] ?? null) ? $item['message'] : '';
        $reason = $succeeded ? null : LoginLogReason::fromMessage($message);
        $userId = ($item['admin_id'] ?? null);
        $userId = is_int($userId) && $userId > 0 ? $userId : null;

        try {
            $database = config('database.connections.pgsql');
            if (!is_array($database)) {
                return;
            }
            self::write(DbalConnection::connection($database), [
                'user_id' => $userId,
                'username_input' => $username,
                'succeeded' => $succeeded,
                'reason' => $reason,
                'ip' => $ip,
                'location' => self::location($ip),
                'device_label' => DeviceLabel::of($ua),
                'user_agent' => mb_substr($ua, 0, 512),
            ]);
        } catch (\Throwable $error) {
            error_log('登录日志写入失败 ' . $error::class . ': ' . $error->getMessage());
        }
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $row */
    public static function write(Connection $connection, array $row): void
    {
        $connection->insert('sand_system_login_log', [
            'user_id' => $row['user_id'],
            'username_input' => mb_substr(self::text($row['username_input'] ?? null), 0, 64),
            'succeeded' => ($row['succeeded'] ?? false) ? 'true' : 'false',
            'reason' => $row['reason'],
            'ip' => $row['ip'],
            'location' => mb_substr(self::text($row['location'] ?? null), 0, 64),
            'device_label' => mb_substr(self::text($row['device_label'] ?? null), 0, 64),
            'user_agent' => $row['user_agent'],
        ]);
    }

    /** 内网地址统一写「内部网络」；外网查不到城市级归属写「未知」。 */
    private static function location(string $ip): string
    {
        return self::isPrivate($ip) ? '内部网络' : '未知';
    }

    private static function isPrivate(string $ip): bool
    {
        if ($ip === '::1' || str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fc')) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
