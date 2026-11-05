<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 把已经在内存中的静态清单转成扩展声明。
 *
 * 插件安装器负责载入静态清单；本类不扫描目录，也不加载清单文件。
 */
final class ExtensionManifest
{
    private function __construct()
    {
    }

    /**
     * @param array<mixed> $manifest
     *
     * @return list<ExtensionDraft>
     */
    public static function drafts(array $manifest): array
    {
        $drafts = [];
        foreach ($manifest as $section => $items) {
            if (!is_string($section)) {
                throw new InvalidExtensionException('清单段名必须为字符串', ['manifest' => 'object']);
            }
            $kind = ExtensionKind::fromManifestSection($section);
            if (!is_array($items) || !array_is_list($items)) {
                throw new InvalidExtensionException('清单段必须为列表', [$section => 'list']);
            }
            foreach ($items as $index => $item) {
                $path = $section . '.' . $index;
                $drafts[] = self::draft($kind, self::object($item, $path), $path);
            }
        }

        return $drafts;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function draft(ExtensionKind $kind, array $item, string $path): ExtensionDraft
    {
        $sort = self::sort($item, $path);
        $enabled = self::flag($item, 'enabled', $path, true);

        return match ($kind) {
            ExtensionKind::SettingsCategory => self::with($item, ['key', 'title', 'description', 'icon', 'sort_order', 'enabled'], $path, ExtensionDraft::settingsCategory(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::optionalString($item, 'description', $path) ?? '',
                self::optionalString($item, 'icon', $path),
                $sort,
                $enabled,
            )),
            ExtensionKind::PersonalSettingsCategory => self::with($item, ['key', 'title', 'description', 'sort_order', 'enabled'], $path, ExtensionDraft::personalSettingsCategory(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::optionalString($item, 'description', $path) ?? '',
                $sort,
                $enabled,
            )),
            ExtensionKind::SearchSource => self::with($item, ['key', 'title', 'handler', 'placeholder', 'sort_order', 'enabled'], $path, ExtensionDraft::searchSource(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::requireString($item, 'handler', $path),
                self::optionalString($item, 'placeholder', $path),
                $sort,
                $enabled,
            )),
            ExtensionKind::NotificationSource => self::with($item, ['key', 'title', 'channel', 'sort_order', 'enabled'], $path, ExtensionDraft::notificationSource(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::requireString($item, 'channel', $path),
                $sort,
                $enabled,
            )),
            ExtensionKind::CronJob => self::with($item, ['key', 'title', 'expression', 'handler', 'timezone', 'sort_order', 'enabled'], $path, ExtensionDraft::cronJob(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::requireString($item, 'expression', $path),
                self::requireString($item, 'handler', $path),
                self::optionalString($item, 'timezone', $path),
                $sort,
                $enabled,
            )),
            ExtensionKind::FileUsage => self::with($item, ['key', 'title', 'owner_type', 'multiple', 'sort_order', 'enabled'], $path, ExtensionDraft::fileUsage(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::requireString($item, 'owner_type', $path),
                self::flag($item, 'multiple', $path, false),
                $sort,
                $enabled,
            )),
            ExtensionKind::WorkbenchCard => self::with($item, ['key', 'title', 'component', 'width', 'height', 'sort_order', 'enabled'], $path, ExtensionDraft::workbenchCard(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::requireString($item, 'component', $path),
                self::span($item, 'width', $path, 1),
                self::span($item, 'height', $path, 1),
                $sort,
                $enabled,
            )),
            ExtensionKind::OptionGroup => self::with($item, ['key', 'title', 'options', 'sort_order', 'enabled'], $path, ExtensionDraft::optionGroup(
                self::requireString($item, 'key', $path),
                self::requireString($item, 'title', $path),
                self::options($item, $path),
                $sort,
                $enabled,
            )),
        };
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $allowed
     */
    private static function with(array $item, array $allowed, string $path, ExtensionDraft $draft): ExtensionDraft
    {
        self::only($item, $allowed, $path);

        return $draft;
    }

    /**
     * 未知字段在声明构造成功后拒绝。纯函数，不写库。
     *
     * @param array<string, mixed> $item
     * @param list<string> $allowed
     */
    private static function only(array $item, array $allowed, string $path): void
    {
        $known = [];
        foreach ($allowed as $key) {
            $known[$key] = true;
        }
        foreach ($item as $key => $_) {
            if (!isset($known[$key])) {
                throw new InvalidExtensionException('清单含未知字段', [$path . '.' . $key => 'unknown']);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function object(mixed $item, string $path): array
    {
        if (!is_array($item)) {
            throw new InvalidExtensionException('清单项必须为对象', [$path => 'object']);
        }
        $out = [];
        foreach ($item as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidExtensionException('清单项必须为对象', [$path => 'object']);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /** @param array<string, mixed> $item */
    private static function requireString(array $item, string $field, string $path): string
    {
        if (!array_key_exists($field, $item) || !is_string($item[$field])) {
            throw new InvalidExtensionException('清单字段必须为字符串', [$path . '.' . $field => 'string']);
        }

        return $item[$field];
    }

    /** @param array<string, mixed> $item */
    private static function optionalString(array $item, string $field, string $path): ?string
    {
        if (!array_key_exists($field, $item) || $item[$field] === null) {
            return null;
        }
        if (!is_string($item[$field])) {
            throw new InvalidExtensionException('清单字段必须为字符串', [$path . '.' . $field => 'string']);
        }

        return $item[$field];
    }

    /** @param array<string, mixed> $item */
    private static function flag(array $item, string $field, string $path, bool $default): bool
    {
        if (!array_key_exists($field, $item)) {
            return $default;
        }
        if (!is_bool($item[$field])) {
            throw new InvalidExtensionException('清单字段必须为布尔', [$path . '.' . $field => 'bool']);
        }

        return $item[$field];
    }

    /** @param array<string, mixed> $item */
    private static function sort(array $item, string $path): int
    {
        if (!array_key_exists('sort_order', $item)) {
            return 0;
        }
        if (!is_int($item['sort_order'])) {
            throw new InvalidExtensionException('sort_order 必须为整数', [$path . '.sort_order' => 'int']);
        }

        return $item['sort_order'];
    }

    /** @param array<string, mixed> $item */
    private static function span(array $item, string $field, string $path, int $default): int
    {
        if (!array_key_exists($field, $item)) {
            return $default;
        }
        if (!is_int($item[$field])) {
            throw new InvalidExtensionException($field . ' 必须为整数', [$path . '.' . $field => 'int']);
        }

        return $item[$field];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return list<OptionItem>
     */
    private static function options(array $item, string $path): array
    {
        if (!array_key_exists('options', $item)) {
            throw new InvalidExtensionException('清单字段缺失', [$path . '.options' => 'required']);
        }
        $raw = $item['options'];
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new InvalidExtensionException('options 必须为列表', [$path . '.options' => 'list']);
        }
        $options = [];
        foreach ($raw as $index => $option) {
            $optionPath = $path . '.options.' . $index;
            $object = self::object($option, $optionPath);
            self::only($object, ['value', 'label'], $optionPath);
            $options[] = new OptionItem(
                self::requireString($object, 'value', $optionPath),
                self::requireString($object, 'label', $optionPath),
            );
        }

        return $options;
    }
}
