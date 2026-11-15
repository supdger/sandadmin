<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\LoginLog\DeviceLabel;
use support\Request;

/** 审计 device_label 的统一取值：UA 原文不进库，只存解析出的中文设备名。 */
final class RequestDevice
{
    private function __construct()
    {
    }

    public static function label(Request $request): ?string
    {
        $agent = $request->header('user-agent');
        if (!is_string($agent) || $agent === '') {
            return null;
        }

        return DeviceLabel::of($agent);
    }
}
