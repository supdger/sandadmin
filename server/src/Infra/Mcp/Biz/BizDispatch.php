<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Biz;

use Sandadmin\Domain\Mcp\BizReply;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Http\BizKernel;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgInput;

/**
 * 业务操作键 → v1 业务服务调用的显式映射表（U-3：每个执行类工具
 * 对应一个现有 v1 接口，不开第二条路）。参数管道与各 v1 控制器
 * 一致：路径参数走 OrgInput::pathId、写操作透传幂等键进同一套
 * 幂等存储、审计上下文（request_id/ip/device）来自 CallContext。
 */
final class BizDispatch
{
    /** 服务审计的 device 字段：MCP 通道统一记 'mcp'。 */
    private const DEVICE = 'mcp';

    /** @param array<string, mixed> $arguments */
    public static function call(BizKernel $kernel, OrgActor $actor, string $operation, array $arguments, CallContext $context): BizReply
    {
        $data = self::execute($kernel, $actor, $operation, $arguments, $context);

        return BizReply::ok($data);
    }

    /** @return array<string, string> 操作键 → 需要的权限码（契约测试与登记表共用） */
    public static function permissions(): array
    {
        return [
            'menus_tree' => 'system:menu:index',
            'depts_tree' => 'system:dept:index',
            'roles_index' => 'system:role:index',
            'roles_show' => 'system:role:index',
            'roles_permissions' => 'system:role:index',
            'permission_tree' => 'system:role:index',
            'users_index' => 'system:user:index',
            'users_show' => 'system:user:index',
            'posts_index' => 'system:post:index',
            'dicts_index' => 'system:dict:index',
            'dicts_show' => 'system:dict:index',
            'depts_create' => 'system:dept:create',
            'depts_update' => 'system:dept:update',
            'posts_create' => 'system:post:create',
            'posts_update' => 'system:post:update',
            'dicts_create' => 'system:dict:create',
            'dicts_update' => 'system:dict:update',
            'menus_create_group' => 'system:menu:create',
            'menus_create_link' => 'system:menu:create',
            'menus_update' => 'system:menu:update',
            'menus_move' => 'system:menu:update',
            'menus_visible' => 'system:menu:update',
            'menus_reset' => 'system:menu:reset',
        ];
    }

    /** @param array<string, mixed> $arguments */
    private static function execute(BizKernel $kernel, OrgActor $actor, string $operation, array $arguments, CallContext $context): mixed
    {
        $requestId = $context->requestId;
        $ip = $context->ip;
        $device = self::DEVICE;
        $body = self::body($arguments);
        $idempotencyKey = self::idempotencyKey($arguments);

        return match ($operation) {
            'menus_tree' => $kernel->menus->loadTree(self::text($body, 'keyword'), self::text($body, 'source')),
            'depts_tree' => $kernel->depts->tree(self::text($body, 'keyword'), self::deptStatus($body)),
            'roles_index' => $kernel->roles->search($body),
            'roles_show' => $kernel->roles->show(self::pathId($body, 'role_id')),
            'roles_permissions' => $kernel->roles->permissions(self::pathId($body, 'role_id')),
            'permission_tree' => $kernel->roles->permissionTree(),
            'users_index' => $kernel->users->search($body),
            'users_show' => $kernel->users->show(self::pathId($body, 'user_id')),
            'posts_index' => $kernel->posts->search($body),
            'dicts_index' => $kernel->dicts->listTypes(self::text($body, 'keyword')),
            'dicts_show' => $kernel->dicts->showType(self::pathId($body, 'type_id')),
            'depts_create' => $kernel->depts->create($body, $actor, $requestId, $ip, $device, $idempotencyKey),
            'depts_update' => $kernel->depts->update(self::pathId($body, 'dept_id'), $body, $actor, $requestId, $ip, $device),
            'posts_create' => $kernel->posts->create($body, $actor, $requestId, $ip, $device, $idempotencyKey),
            'posts_update' => $kernel->posts->update(self::pathId($body, 'post_id'), $body, $actor, $requestId, $ip, $device),
            'dicts_create' => $kernel->dicts->createType($body, $actor, $requestId, $ip, $device, $idempotencyKey),
            'dicts_update' => $kernel->dicts->updateType(self::pathId($body, 'type_id'), $body, $actor, $requestId, $ip, $device),
            'menus_create_group' => $kernel->menus->createGroup($body, $actor, $requestId, $ip, $device, $idempotencyKey),
            'menus_create_link' => $kernel->menus->createLink($body, $actor, $requestId, $ip, $device, $idempotencyKey),
            'menus_update' => $kernel->menus->updateNode(self::menuKey($body), $body, $actor, $requestId, $ip, $device),
            'menus_move' => self::movedNode($kernel, $actor, $body, $requestId, $ip, $device),
            'menus_visible' => self::toggledVisible($kernel, $actor, $body, $requestId, $ip, $device),
            'menus_reset' => $kernel->menus->resetTree($actor, $requestId, $ip, $device),
            default => throw OrgException::invalid(['operation' => ['未登记的业务操作']]),
        };
    }

    /**
     * 工具入参 = v1 请求体 + 路径参数 + 控制键；剥掉控制键后整包透传，
     * 字段校验由业务服务按 v1 同一套规则判定。
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function body(array $arguments): array
    {
        unset($arguments['idempotency_key'], $arguments['dry_run']);

        return $arguments;
    }

    /** @param array<string, mixed> $body */
    private static function text(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $body */
    private static function deptStatus(array $body): ?int
    {
        return match ($body['status'] ?? null) {
            'enabled' => 1,
            'disabled' => 2,
            null, '' => null,
            default => throw OrgException::invalid(['status' => ['状态不正确']]),
        };
    }

    /** @param array<string, mixed> $body */
    private static function pathId(array $body, string $key): int
    {
        $value = $body[$key] ?? null;

        return OrgInput::pathId(is_scalar($value) ? (string) $value : '', $key);
    }

    /** @param array<string, mixed> $body */
    private static function menuKey(array $body): string
    {
        $value = $body['menu_key'] ?? null;
        if (!is_string($value) || $value === '') {
            throw OrgException::invalid(['menu_key' => ['请填写菜单标识']]);
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private static function idempotencyKey(array $arguments): ?string
    {
        $key = $arguments['idempotency_key'] ?? null;
        if (!is_string($key)) {
            return null;
        }
        $key = trim($key);

        return $key === '' ? null : $key;
    }

    /**
     * v1 移动接口返回空对象，工具回包保持一致。
     *
     * @param array<string, mixed> $body
     */
    private static function movedNode(BizKernel $kernel, OrgActor $actor, array $body, string $requestId, ?string $ip, string $device): \stdClass
    {
        $kernel->menus->moveNode(self::menuKey($body), $body, $actor, $requestId, $ip, $device);

        return new \stdClass();
    }

    /**
     * v1 显隐接口返回空对象，工具回包保持一致。
     *
     * @param array<string, mixed> $body
     */
    private static function toggledVisible(BizKernel $kernel, OrgActor $actor, array $body, string $requestId, ?string $ip, string $device): \stdClass
    {
        $kernel->menus->setVisible(self::menuKey($body), $body, $actor, $requestId, $ip, $device);

        return new \stdClass();
    }
}
