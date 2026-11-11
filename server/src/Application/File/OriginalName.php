<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

/** 原始文件名入库前的规整：去路径、去控制字符、限长。 */
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
        if (mb_strlen($stripped) > 255) {
            $stripped = mb_substr($stripped, 0, 255);
        }

        return $stripped;
    }

    /** 取小写扩展名（不含点）；无扩展名或含非法字符时返回空串。 */
    public static function extension(string $name): string
    {
        // pathinfo 会把 ".hidden" 的 hidden 当扩展名；点文件名按惯例视为无扩展名。
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === 0) {
            return '';
        }
        $extension = strtolower(substr($name, $dot + 1));
        if ($extension === '' || preg_match('/\A[a-z0-9]{1,16}\z/', $extension) !== 1) {
            return '';
        }

        return $extension;
    }
}
