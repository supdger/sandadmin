<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 定时任务频率。取值与 sand_system_schedule.schedule 的 freq 枚举一致。 */
enum ScheduleFreq: string
{
    case Interval = 'interval';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
