<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/** 组织查询里反复用到的标量转换。 */
final class OrgSql
{
    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function statusName(int $status): string
    {
        return $status === 2 ? 'disabled' : 'enabled';
    }

    public static function time(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->format(DATE_ATOM);
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }
}
