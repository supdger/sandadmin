<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Db\DbalConnection;
use Sandadmin\Infra\Idempotency\ConnectionIdempotencyStore;
use Sandadmin\Module\Dict\DictService;
use Sandadmin\Module\Menu\MenuService;
use Sandadmin\Module\Org\DeptDeletion;
use Sandadmin\Module\Org\DeptRepository;
use Sandadmin\Module\Org\DeptService;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\PostService;
use Sandadmin\Module\Org\RoleRepository;
use Sandadmin\Module\Org\RoleService;
use Sandadmin\Module\Org\UserRepository;
use Sandadmin\Module\Org\UserService;

/** 一次请求里用的用户、部门、角色、岗位、选项服务。 */
final readonly class BizKernel
{
    public function __construct(
        public Connection $connection,
        public UserService $users,
        public DeptService $depts,
        public RoleService $roles,
        public PostService $posts,
        public DictService $dicts,
        public MenuService $menus,
        public DevPrincipal $principal,
    ) {
    }

    public static function fromWebman(): ?self
    {
        if (!function_exists('config')) {
            return null;
        }
        $database = config('database.connections.pgsql');
        if (!is_array($database)) {
            return null;
        }
        try {
            $connection = DbalConnection::connection($database);

            return self::fromConnection($connection);
        } catch (\Throwable $error) {
            error_log('业务运行时初始化失败 ' . $error::class . ': ' . $error->getMessage());

            return null;
        }
    }

    public static function fromConnection(Connection $connection): self
    {
        $audit = new OrgAudit($connection);
        $deletion = new DeptDeletion(
            $connection,
            new DeptRepository($connection),
            new UserRepository($connection),
            new RoleRepository($connection),
        );
        $keys = new OrgIdempotency(new ConnectionIdempotencyStore($connection));

        return new self(
            $connection,
            new UserService($connection, $audit, $keys),
            new DeptService($connection, $deletion, $audit, $keys),
            new RoleService($connection, $audit, $keys),
            new PostService($connection, $audit, $keys),
            new DictService($connection, $audit, $keys),
            new MenuService($connection, $audit, $keys),
            new DevPrincipal($connection),
        );
    }
}
