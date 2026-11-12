<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/** 公告发送范围：all 全员 / dept 指定部门（不含下级部门）。 */
enum AnnouncementScope: string
{
    case All = 'all';
    case Dept = 'dept';
}
