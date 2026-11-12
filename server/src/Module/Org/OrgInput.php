<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/** 把请求体收成组织模块用的标量。缺字段和类型不对都是 422。 */
final class OrgInput
{
    /** @param array<string, mixed> $body */
    public static function optionalText(array $body, string $key): ?string
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            return null;
        }
        if (!is_string($body[$key])) {
            throw OrgException::invalid([$key => ['格式不正确']]);
        }
        $text = trim($body[$key]);

        return $text === '' ? null : $text;
    }

    /** @param array<string, mixed> $body */
    public static function requiredText(array $body, string $key): string
    {
        $text = self::optionalText($body, $key);
        if ($text === null) {
            throw OrgException::invalid([$key => ['这一项需要填写']]);
        }

        return $text;
    }

    /** @param array<string, mixed> $body */
    public static function optionalId(array $body, string $key): ?int
    {
        if (!array_key_exists($key, $body) || $body[$key] === null || $body[$key] === '') {
            return null;
        }

        return self::parseId($body[$key], $key);
    }

    /** @param array<string, mixed> $body */
    public static function requiredId(array $body, string $key): int
    {
        $id = self::optionalId($body, $key);
        if ($id === null) {
            throw OrgException::invalid([$key => ['这一项需要填写']]);
        }

        return $id;
    }

    public static function pathId(string $raw, string $key): int
    {
        return self::parseId($raw, $key);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<int>
     */
    public static function idList(array $body, string $key, bool $required): array
    {
        if (!array_key_exists($key, $body) || $body[$key] === null) {
            if ($required) {
                throw OrgException::invalid([$key => ['这一项需要填写']]);
            }

            return [];
        }
        $raw = $body[$key];
        if (!is_array($raw)) {
            throw OrgException::invalid([$key => ['格式不正确']]);
        }
        $ids = [];
        foreach ($raw as $item) {
            $ids[] = self::parseId($item, $key);
        }

        return $ids;
    }

    /** @param array<string, mixed> $body */
    public static function status(array $body, int $default = 1): int
    {
        if (!array_key_exists('status', $body) || $body['status'] === null) {
            return $default;
        }
        $value = $body['status'];
        if ($value === 'enabled' || $value === 1) {
            return 1;
        }
        if ($value === 'disabled' || $value === 2) {
            return 2;
        }
        throw OrgException::invalid(['status' => ['状态不正确']]);
    }

    /**
     * 更新时请求没带状态，就沿用库里的值。
     * 缺省不能当成启用，否则只改名字会把已停用覆盖回去。
     *
     * @param array<string, mixed> $body
     */
    public static function keptStatus(array $body, int $current): int
    {
        if (!array_key_exists('status', $body) || $body['status'] === null || $body['status'] === '') {
            return $current;
        }

        return self::status($body);
    }

    /** @param array<string, mixed> $body */
    public static function sort(array $body): int
    {
        if (!array_key_exists('sort', $body) || $body['sort'] === null) {
            return 0;
        }
        $value = $body['sort'];
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw OrgException::invalid(['sort' => ['排序不正确']]);
    }

    private static function parseId(mixed $raw, string $key): int
    {
        if (is_int($raw)) {
            $raw = (string) $raw;
        }
        if (!is_string($raw) || preg_match('/^\d{1,19}$/', $raw) !== 1) {
            throw OrgException::invalid([$key => ['编号不正确']]);
        }
        $id = (int) $raw;
        if ($id <= 0) {
            throw OrgException::invalid([$key => ['编号不正确']]);
        }

        return $id;
    }
}
