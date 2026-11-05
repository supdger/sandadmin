<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

final class OriginalName
{
    public static function sanitize(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $base);
        if (!is_string($stripped)) {
            return 'file';
        }
        $stripped = trim($stripped);
        if ($stripped === '' || $stripped === '.' || $stripped === '..') {
            return 'file';
        }
        if (mb_strlen($stripped) > 200) {
            $stripped = mb_substr($stripped, 0, 200);
        }

        return $stripped;
    }
}
