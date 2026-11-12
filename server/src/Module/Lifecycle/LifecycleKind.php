<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

/** 申请种类；与 sand_system_lifecycle_request.kind CHECK 一致。 */
enum LifecycleKind: string
{
    case PluginInstall = 'plugin_install';
    case PluginUpgrade = 'plugin_upgrade';
    case PluginUninstall = 'plugin_uninstall';
    case PluginUpgradeBatch = 'plugin_upgrade_batch';
    case SystemUpgrade = 'system_upgrade';
}
