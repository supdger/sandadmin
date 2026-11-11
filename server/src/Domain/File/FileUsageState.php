<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 文件使用情况三态（api-conventions §10）：
 * used=有 sand_system_file_usage 引用；unused=上传方登记了用途跟踪但当前无引用；
 * unknown=未跟踪且无引用，界面显示「暂时看不出用在哪里」。
 */
enum FileUsageState: string
{
    case Used = 'used';
    case Unused = 'unused';
    case Unknown = 'unknown';

    public static function resolve(bool $hasUsage, bool $usageTracked): self
    {
        if ($hasUsage) {
            return self::Used;
        }

        return $usageTracked ? self::Unused : self::Unknown;
    }
}
