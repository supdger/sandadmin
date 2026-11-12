<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

/** 生命周期固定阶段标识；仅 system_upgrade 申请写入，与迁移 0003 约束一致。 */
enum LifecycleStage: string
{
    case Check = 'check';
    case Backup = 'backup';
    case Download = 'download';
    case Switch = 'switch';
    case Verify = 'verify';
}
