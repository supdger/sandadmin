<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 扩展位字段的取值边界。无状态；plugin_code 规则与 sand_system_plugin.code 的 CHECK 一致。 */
final class ExtensionRules
{
    public const MAX_PLUGIN_CODE = 64;
    public const MAX_KEY = 128;
    public const MAX_TITLE = 200;
    public const MAX_DESCRIPTION = 500;
    public const MAX_ICON = 64;
    public const MAX_HANDLER = 255;
    public const MAX_PLACEHOLDER = 120;
    public const MAX_CHANNEL = 64;
    public const MAX_OWNER_TYPE = 64;
    public const MAX_SUBJECT_TYPE = 64;
    public const MAX_COMPONENT = 128;
    public const MAX_OPTION_VALUE = 64;
    public const MAX_OPTION_LABEL = 100;
    public const MIN_SORT = -32768;
    public const MAX_SORT = 32767;
    public const MIN_SPAN = 1;
    public const MAX_SPAN = 24;

    private function __construct()
    {
    }

    public static function pluginCode(string $pluginCode): string
    {
        self::slug($pluginCode, self::MAX_PLUGIN_CODE, '/^[a-z0-9][a-z0-9_-]{0,63}$/', 'plugin_code');

        return $pluginCode;
    }

    public static function key(string $key): string
    {
        self::slug($key, self::MAX_KEY, '/^[a-z][a-z0-9._-]*$/', 'key');

        return $key;
    }

    public static function title(string $title): string
    {
        return self::text(trim($title), self::MAX_TITLE, 'title', false);
    }

    public static function description(string $description): string
    {
        return self::text(trim($description), self::MAX_DESCRIPTION, 'description', true);
    }

    public static function icon(?string $icon): ?string
    {
        if ($icon === null) {
            return null;
        }
        $icon = trim($icon);
        if ($icon === '') {
            return null;
        }
        self::slug($icon, self::MAX_ICON, '/^[A-Za-z0-9_-]+$/', 'icon');

        return $icon;
    }

    public static function className(string $className, string $field): string
    {
        self::slug($className, self::MAX_HANDLER, '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $field);

        return $className;
    }

    public static function placeholder(?string $placeholder): ?string
    {
        if ($placeholder === null) {
            return null;
        }
        $placeholder = trim($placeholder);
        if ($placeholder === '') {
            return null;
        }

        return self::text($placeholder, self::MAX_PLACEHOLDER, 'placeholder', false);
    }

    public static function channel(string $channel): string
    {
        self::slug($channel, self::MAX_CHANNEL, '/^[a-z][a-z0-9._-]*$/', 'channel');

        return $channel;
    }

    public static function ownerType(string $ownerType): string
    {
        self::slug($ownerType, self::MAX_OWNER_TYPE, '/^[a-z][a-z0-9._-]*$/', 'owner_type');

        return $ownerType;
    }

    /** sand_system_file_usage.subject_type 的取值边界，与列宽一致。 */
    public static function subjectType(string $subjectType): string
    {
        self::slug($subjectType, self::MAX_SUBJECT_TYPE, '/^[a-z][a-z0-9._-]*$/', 'subject_type');

        return $subjectType;
    }

    public static function component(string $component): string
    {
        if (str_contains($component, '..')) {
            throw new InvalidExtensionException('component 格式错误', ['component' => 'slug']);
        }
        self::slug($component, self::MAX_COMPONENT, '/^[A-Za-z][A-Za-z0-9_\/-]*$/', 'component');

        return $component;
    }

    public static function span(int $span, string $field): int
    {
        if ($span < self::MIN_SPAN || $span > self::MAX_SPAN) {
            throw new InvalidExtensionException($field . ' 超出范围', [$field => 'between:' . self::MIN_SPAN . ',' . self::MAX_SPAN]);
        }

        return $span;
    }

    /** sort 列为 smallint，取值限定在其范围内。 */
    public static function sort(int $sort): int
    {
        if ($sort < self::MIN_SORT || $sort > self::MAX_SORT) {
            throw new InvalidExtensionException('sort 超出范围', ['sort' => 'between:' . self::MIN_SORT . ',' . self::MAX_SORT]);
        }

        return $sort;
    }

    public static function optionValue(string $value): string
    {
        self::slug($value, self::MAX_OPTION_VALUE, '/^[A-Za-z0-9_.:-]+$/', 'value');

        return $value;
    }

    public static function optionLabel(string $label): string
    {
        return self::text(trim($label), self::MAX_OPTION_LABEL, 'label', false);
    }

    /**
     * @param list<OptionItem> $options
     *
     * @return list<OptionItem>
     */
    public static function options(array $options): array
    {
        $seen = [];
        foreach ($options as $option) {
            if (isset($seen[$option->value])) {
                throw new InvalidExtensionException('选项值重复', ['options' => 'unique']);
            }
            $seen[$option->value] = true;
        }

        return $options;
    }

    private static function slug(string $value, int $max, string $pattern, string $field): void
    {
        if (self::chars($value) > $max || preg_match($pattern, $value) !== 1) {
            throw new InvalidExtensionException($field . ' 格式错误', [$field => 'slug']);
        }
    }

    private static function text(string $value, int $max, string $field, bool $allowEmpty): string
    {
        if ($value === '' && !$allowEmpty) {
            throw new InvalidExtensionException($field . ' 不能为空', [$field => 'required']);
        }
        if (self::chars($value) > $max) {
            throw new InvalidExtensionException($field . ' 过长', [$field => 'max:' . $max]);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidExtensionException($field . ' 含控制字符', [$field => 'text']);
        }

        return $value;
    }

    private static function chars(string $value): int
    {
        $count = preg_match_all('/./u', $value);
        if ($count === false) {
            throw new InvalidExtensionException('文本必须是合法 UTF-8', ['text' => 'utf8']);
        }

        return $count;
    }
}
