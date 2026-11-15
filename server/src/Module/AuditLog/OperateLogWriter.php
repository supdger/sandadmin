<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\AuditLog;

use Doctrine\DBAL\Connection;
use Sandadmin\Http\RequestDevice;
use Sandadmin\Infra\Db\DbalConnection;
use Sandadmin\Module\Org\OrgSql;
use support\Request;
use Tinywan\Jwt\JwtToken;
use Webman\Http\Response;

/**
 * user.operateLog 事件的操作日志写库：插件监听器写的 0.x 旧表
 * sand_system_oper_log 在 1.x 基线不存在，且事件在业务处理之前发出，
 * 在事件里落库只能把「已受理」伪记成 succeeded=true。现在事件只打
 * 待记录标记，OperateLogMiddleware 在响应后调 recordOutcome 按
 * 真实结果写 audit_log 唯一一行；写失败只记错误日志。
 */
final class OperateLogWriter
{
    /** X-Request-Id 沿用口径：与 src/Http 各控制器一致。 */
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    /** 事件入口：emit 时还没有结果可记，只给当前请求打待记录标记。 */
    public static function markPending(mixed $item = null): void
    {
        $request = function_exists('request') ? request() : null;
        if ($request instanceof Request) {
            $request->properties['operateLogPending'] = true;
        }
    }

    /**
     * 响应生成后的落库入口，由 OperateLogMiddleware 调用：只有被事件
     * 标记的请求才写；$response 为 null 表示下游异常逃逸出链，记为
     * 失败；写库异常只落 error_log，不抛出、不伪报。
     */
    public static function recordOutcome(\Webman\Http\Request $request, ?Response $response): void
    {
        if (!$request instanceof Request || !self::hasPendingMark($request)) {
            return;
        }
        $succeeded = $response !== null && self::resolveSucceeded($response);
        $row = self::rowFor($request, self::actor($request), $succeeded);
        if ($row === null) {
            return;
        }
        try {
            $database = config('database.connections.pgsql');
            if (!is_array($database)) {
                return;
            }
            self::write(DbalConnection::connection($database), $row);
        } catch (\Throwable $error) {
            // 只记异常类与摘要；changes 落库前已脱敏，错误信息不含参数原文
            error_log('操作日志写入失败 ' . $error::class . ': ' . $error->getMessage());
        }
    }

    /** 结果判定：异常渲染必然失败；业务壳按 code===200 判成功；无 code 键的非 JSON 响应按 HTTP 状态判定。 */
    public static function resolveSucceeded(Response $response): bool
    {
        if ($response->exception() !== null) {
            return false;
        }
        $decoded = json_decode($response->rawBody(), true);
        if (is_array($decoded) && array_key_exists('code', $decoded)) {
            $code = $decoded['code'];
            if (is_int($code)) {
                return $code === 200;
            }

            return is_string($code) && ctype_digit($code) && (int) $code === 200;
        }

        return $response->getStatusCode() < 400;
    }

    /**
     * 组装 audit_log 行。GET 或 username 为空时返回 null（与原
     * 监听器跳过条件一致），调用方据此不落行。
     *
     * @param array<array-key, mixed> $actor 登录令牌扩展（id/username）
     *
     * @return array<string, mixed>|null
     */
    public static function rowFor(Request $request, array $actor, bool $succeeded): ?array
    {
        if ($request->method() === 'GET') {
            return null;
        }
        $username = is_string($actor['username'] ?? null) ? trim($actor['username']) : '';
        if ($username === '') {
            return null;
        }
        $rawId = $actor['id'] ?? null;
        $userId = (is_int($rawId) || (is_string($rawId) && ctype_digit($rawId))) && (int) $rawId > 0
            ? (int) $rawId
            : null;
        $ip = self::ip($request->getRemoteIp());
        $masked = class_exists(\plugin\sandadmin\utils\SensitiveData::class)
            ? \plugin\sandadmin\utils\SensitiveData::maskParams($request->all())
            : $request->all();
        $changes = json_encode($masked, JSON_UNESCAPED_UNICODE);

        return [
            'actor_type' => 'user',
            'actor_id' => $userId,
            'actor_name' => $username,
            'module' => self::module($request),
            'action' => self::action($request),
            'action_text' => self::actionText($request),
            'target_type' => 'request',
            'target_id' => mb_substr($request->path(), 0, 64),
            'target_label' => self::targetLabel($request),
            'succeeded' => $succeeded,
            'changes' => is_string($changes) ? $changes : null,
            'ip' => $ip,
            'location' => $ip === null ? '' : self::ipLocation($ip),
            'device_label' => RequestDevice::label($request) ?? '',
            'request_id' => self::requestId($request),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function write(Connection $connection, array $row): void
    {
        $connection->insert('sand_system_audit_log', [
            'actor_type' => 'user',
            'actor_id' => is_int($row['actor_id'] ?? null) ? $row['actor_id'] : null,
            'actor_name' => mb_substr(self::text($row['actor_name'] ?? null), 0, 20),
            'module' => mb_substr(self::text($row['module'] ?? null), 0, 64),
            'action' => mb_substr(self::text($row['action'] ?? null), 0, 96),
            'action_text' => mb_substr(self::text($row['action_text'] ?? null), 0, 255),
            'target_type' => mb_substr(self::text($row['target_type'] ?? null), 0, 64),
            'target_id' => mb_substr(self::text($row['target_id'] ?? null), 0, 64),
            'target_label' => mb_substr(self::text($row['target_label'] ?? null), 0, 128),
            'succeeded' => ($row['succeeded'] ?? false) ? 'true' : 'false',
            'changes' => is_string($row['changes'] ?? null) ? $row['changes'] : null,
            'ip' => self::ip(is_string($row['ip'] ?? null) ? $row['ip'] : null),
            'location' => mb_substr(self::text($row['location'] ?? null), 0, 64),
            'device_label' => mb_substr(self::text($row['device_label'] ?? null), 0, 64),
            'request_id' => mb_substr(self::text($row['request_id'] ?? null), 0, 64),
        ]);
    }

    /** 待记录标记：插件 SystemLog emit 时由 markPending 打在请求上。 */
    private static function hasPendingMark(Request $request): bool
    {
        return ($request->properties['operateLogPending'] ?? null) === true;
    }

    /**
     * 登录上下文：到本方法时 CheckLogin 已验签并把令牌覆写进
     * check_admin 请求头（setHeader 覆盖客户端同名字段，伪造值
     * 到不了这里）；缺请求头时按插件同口径用 JwtToken 服务端验签，
     * 验不过返回空、不落行，不做无凭据回退。
     *
     * @return array<array-key, mixed>
     */
    private static function actor(Request $request): array
    {
        $token = $request->header('check_admin');
        if (is_array($token)) {
            return $token;
        }
        try {
            return JwtToken::getExtend();
        } catch (\Throwable) {
            return [];
        }
    }

    /** 插件请求记插件名；宿主 1.x 请求 plugin 为空，统一记后台应用名（与原监听器 app 列口径一致）。 */
    private static function module(Request $request): string
    {
        $plugin = OrgSql::str($request->plugin);

        return $plugin === '' ? 'sandadmin' : $plugin;
    }

    /** 动作标识取路由解析出的控制器方法名（save/update 等），解析不到用 HTTP 方法兜底。 */
    private static function action(Request $request): string
    {
        $action = OrgSql::str($request->action);

        return $action === '' ? strtolower($request->method()) : $action;
    }

    /** 菜单名：复用插件权限属性反射，无 Permission 注解时与原监听器同为「未命名业务」。 */
    private static function actionText(Request $request): string
    {
        $controller = OrgSql::str($request->controller);
        $action = OrgSql::str($request->action);
        if ($controller !== '' && $action !== ''
            && class_exists(\plugin\sandadmin\app\cache\ReflectionCache::class)) {
            try {
                $permissions = \plugin\sandadmin\app\cache\ReflectionCache::getPermissionAttributes($controller, $action);
                $title = $permissions['title'] ?? null;
                if (is_string($title) && $title !== '') {
                    return $title;
                }
            } catch (\Throwable) {
                // 反射/缓存不可用时落到未命名，不阻断写行
            }
        }

        return '未命名业务';
    }

    /** 无业务对象时的对象显示名：控制器::方法签名，解析不到退成「方法 路径」。 */
    private static function targetLabel(Request $request): string
    {
        $controller = OrgSql::str($request->controller);
        $action = OrgSql::str($request->action);
        if ($controller !== '' && $action !== '') {
            $short = str_contains($controller, '\\') ? (string) substr($controller, (int) strrpos($controller, '\\') + 1) : $controller;

            return mb_substr($short . '::' . $action, 0, 128);
        }

        return mb_substr($request->method() . ' ' . $request->path(), 0, 128);
    }

    /** X-Request-Id 合法即沿用，否则生成；与 src/Http 各控制器同口径。 */
    private static function requestId(Request $request): string
    {
        $incoming = $request->header('x-request-id');
        if (is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return bin2hex(random_bytes(16));
    }

    /**
     * IP 归属地：与插件 SystemUser::getIpLocation 同口径，内网/查不到
     * 给确定文案；Ip2Region 缺失或查询失败不阻断写行。
     */
    private static function ipLocation(string $ip): string
    {
        if (!class_exists(\Ip2Region::class)) {
            return '未知';
        }
        try {
            $region = (new \Ip2Region())->memorySearch($ip);
        } catch (\Throwable) {
            return '未知';
        }
        // memorySearch 契约返回 array{region: string}
        $text = is_string($region['region'] ?? null) ? $region['region'] : '';
        $parts = explode('|', $text);
        $parts += ['0', '0', '0', '0'];
        [$country, $province, $city, $network] = $parts;
        if ($network === '内网IP') {
            return $network;
        }
        if ($country === '中国') {
            return $province . '-' . $city . ':' . $network;
        }

        return $country === '0' ? '未知' : $country;
    }

    /** inet 列只收合法 IP，其余置 NULL（与 OrgAudit::ip 同口径）。 */
    private static function ip(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : $ip;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
