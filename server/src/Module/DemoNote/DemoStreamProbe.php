<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\DemoNote;

use Sandadmin\Domain\Stream\StreamPermissionProbe;

/**
 * 演示端点专用权限探针：固定返回版本号 0，与开流快照一致即永不撤权。
 * 演示流不绑真实用户；生产鉴权接探针时在权限侧另写实现。
 */
final class DemoStreamProbe implements StreamPermissionProbe
{
    public function authzVersion(string $subject): int
    {
        return 0;
    }
}
