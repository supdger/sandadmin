<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\KernelApi;

/**
 * 宿主 kernel_api 版本与语义化区间判定。CURRENT 是宿主版本唯一出处，
 * 插件 manifest 的 kernel_api_min/max 与 requires 区间都按同一口径
 * 解释：[min, max] 闭区间且与 min 同主版本（主版本变化即不兼容）。
 */
final class KernelApiVersion
{
    public const CURRENT = '1.0.0';

    public static function isSemver(string $version): bool
    {
        return preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version) === 1;
    }

    public static function compare(string $a, string $b): int
    {
        $partsA = self::parts($a);
        $partsB = self::parts($b);
        for ($i = 0; $i < 3; $i++) {
            if ($partsA[$i] !== $partsB[$i]) {
                return $partsA[$i] <=> $partsB[$i];
            }
        }

        return 0;
    }

    /** 宿主版本是否落在插件声明的兼容区间内。非法区间一律按不满足处理。 */
    public static function satisfies(string $min, string $max): bool
    {
        if (!self::isSemver($min) || !self::isSemver($max)) {
            return false;
        }

        return self::compare(self::CURRENT, $min) >= 0
            && self::compare(self::CURRENT, $max) <= 0
            && self::major(self::CURRENT) === self::major($min);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function parts(string $version): array
    {
        if (!self::isSemver($version)) {
            throw new \InvalidArgumentException('非语义化版本号：' . $version);
        }
        $segments = explode('.', $version);

        return [(int) $segments[0], (int) $segments[1], (int) $segments[2]];
    }

    private static function major(string $version): int
    {
        return self::parts($version)[0];
    }
}
