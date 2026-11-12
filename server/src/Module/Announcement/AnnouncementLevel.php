<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/** 重要程度；important 的公告在收件人下次进入后台时弹一次。 */
enum AnnouncementLevel: string
{
    case Normal = 'normal';
    case Important = 'important';
}
