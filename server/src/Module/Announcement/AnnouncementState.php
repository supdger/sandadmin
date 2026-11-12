<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/**
 * 落库流转状态。「已过期」不入枚举：由 expire_time 与当前时间派生，
 * 列表筛选 state=expired 走派生条件。
 */
enum AnnouncementState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    public const DERIVED_EXPIRED = 'expired';
}
