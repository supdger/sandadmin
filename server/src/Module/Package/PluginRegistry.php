<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 插件登记账本：sand_system_plugin 表的读写口。安装闸查已装版本
 * 与依赖，生命周期收尾写状态；state 只有 installed/uninstalled 两值。
 */
interface PluginRegistry
{
    /** 已装返回版本号，未装或已卸载返回 null。 */
    public function installedVersion(string $app): ?string;

    /**
     * 全部已装插件的版本表，requires 依赖检查用。
     * @return array<string, string>
     */
    public function installedVersions(): array;

    public function recordInstall(string $app, string $name, string $version, string $manifestSha256): void;

    /** purge 为真删行，否则置 uninstalled 保留行（数据处理口径是 keep 的账本对应）。 */
    public function recordUninstall(string $app, bool $purge): void;
}
