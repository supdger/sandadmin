<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Tenancy;

/**
 * 当前请求归属的组织。1.x 框架单租户（T-1）：宿主不开租户管理、
 * 业务表不加组织列，本接口只是「不破坏将来多租户版本」的约定，
 * 默认实现全部返回固定值；产品侧租户归 SandIAM 插件。
 * 「组织」指租户，不是 Module\Org 的部门/岗位。
 */
interface OrgContextInterface
{
    /** 组织号；单组织部署返回固定组织号，未启用组织时返回 null */
    public function orgId(): ?int;

    /** 分片键；1.x 单租户恒 'default' */
    public function shardKey(): string;

    /**
     * 缓存键统一前缀，含结尾分隔符，调用方直接拼接键名；
     * 组织字段由基础设施统一加，业务代码不自己拼「属于谁」的键段。
     */
    public function cachePrefix(): string;

    /** 文件存储路径统一前缀，含结尾分隔符，约定同 cachePrefix() */
    public function storagePrefix(): string;
}
