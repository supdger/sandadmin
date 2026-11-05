<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 扩展声明与 plugin_extension.payload 之间的编解码。不访问数据库或文件系统。 */
final class ExtensionCodec
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function payload(ExtensionDraft $draft): array
    {
        return match ($draft->kind) {
            ExtensionKind::SettingsCategory => [
                'description' => $draft->description,
                'icon' => $draft->icon,
            ],
            ExtensionKind::PersonalSettingsCategory => [
                'description' => $draft->description,
            ],
            ExtensionKind::SearchSource => [
                'handler' => self::required($draft->handler, 'handler'),
                'placeholder' => $draft->placeholder,
            ],
            ExtensionKind::NotificationSource => [
                'channel' => self::required($draft->channel, 'channel'),
            ],
            ExtensionKind::CronJob => [
                'expression' => self::required($draft->expression, 'expression'),
                'handler' => self::required($draft->handler, 'handler'),
                'timezone' => $draft->timezone,
            ],
            ExtensionKind::FileUsage => [
                'owner_type' => self::required($draft->ownerType, 'owner_type'),
                'multiple' => $draft->multiple,
            ],
            ExtensionKind::WorkbenchCard => [
                'component' => self::required($draft->component, 'component'),
                'width' => $draft->width,
                'height' => $draft->height,
            ],
            ExtensionKind::OptionGroup => [
                'options' => self::optionPayload($draft->options),
            ],
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function settingsCategory(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): SettingsCategory {
        self::keys($payload, ['description', 'icon']);

        return new SettingsCategory(
            $pluginId,
            $key,
            $title,
            self::string($payload, 'description'),
            self::nullableString($payload, 'icon'),
            $sortOrder,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function personalSettingsCategory(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): PersonalSettingsCategory {
        self::keys($payload, ['description']);

        return new PersonalSettingsCategory($pluginId, $key, $title, self::string($payload, 'description'), $sortOrder);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function searchSource(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): SearchSource {
        self::keys($payload, ['handler', 'placeholder']);

        return new SearchSource(
            $pluginId,
            $key,
            $title,
            self::string($payload, 'handler'),
            self::nullableString($payload, 'placeholder'),
            $sortOrder,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function notificationSource(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): NotificationSource {
        self::keys($payload, ['channel']);

        return new NotificationSource($pluginId, $key, $title, self::string($payload, 'channel'), $sortOrder);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function cronJob(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): CronJob {
        self::keys($payload, ['expression', 'handler', 'timezone']);

        return new CronJob(
            $pluginId,
            $key,
            $title,
            self::string($payload, 'expression'),
            self::string($payload, 'handler'),
            self::string($payload, 'timezone'),
            $sortOrder,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fileUsage(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): FileUsageRegistration {
        self::keys($payload, ['owner_type', 'multiple']);

        return new FileUsageRegistration(
            $pluginId,
            $key,
            $title,
            self::string($payload, 'owner_type'),
            self::bool($payload, 'multiple'),
            $sortOrder,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function workbenchCard(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): WorkbenchCard {
        self::keys($payload, ['component', 'width', 'height']);

        return new WorkbenchCard(
            $pluginId,
            $key,
            $title,
            self::string($payload, 'component'),
            self::int($payload, 'width'),
            self::int($payload, 'height'),
            $sortOrder,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function optionGroup(
        string $pluginId,
        string $key,
        string $title,
        int $sortOrder,
        array $payload,
    ): OptionGroup {
        self::keys($payload, ['options']);

        return new OptionGroup($pluginId, $key, $title, self::options($payload), $sortOrder);
    }

    private static function required(?string $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new InvalidExtensionException('扩展声明缺少字段', [$field => 'required']);
        }

        return $value;
    }

    /**
     * @param list<OptionItem> $options
     *
     * @return list<array{value: string, label: string}>
     */
    private static function optionPayload(array $options): array
    {
        $payload = [];
        foreach ($options as $option) {
            $payload[] = ['value' => $option->value, 'label' => $option->label];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expected
     */
    private static function keys(array $payload, array $expected): void
    {
        $allowed = [];
        foreach ($expected as $key) {
            $allowed[$key] = true;
        }
        foreach ($payload as $key => $_) {
            if (!isset($allowed[$key])) {
                throw new InvalidExtensionException('扩展载荷含未知字段', ['payload.' . $key => 'unknown']);
            }
        }
        foreach ($expected as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidExtensionException('扩展载荷字段缺失', ['payload.' . $key => 'required']);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key];
        if (!is_string($value)) {
            throw new InvalidExtensionException('扩展载荷字段类型错误', ['payload.' . $key => 'string']);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key];
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidExtensionException('扩展载荷字段类型错误', ['payload.' . $key => 'string']);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function bool(array $payload, string $key): bool
    {
        $value = $payload[$key];
        if (!is_bool($value)) {
            throw new InvalidExtensionException('扩展载荷字段类型错误', ['payload.' . $key => 'bool']);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function int(array $payload, string $key): int
    {
        $value = $payload[$key];
        if (!is_int($value)) {
            throw new InvalidExtensionException('扩展载荷字段类型错误', ['payload.' . $key => 'int']);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<OptionItem>
     */
    private static function options(array $payload): array
    {
        $raw = $payload['options'];
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new InvalidExtensionException('扩展载荷字段类型错误', ['payload.options' => 'list']);
        }
        $options = [];
        foreach ($raw as $index => $item) {
            $path = 'payload.options.' . $index;
            if (!is_array($item) || array_is_list($item)) {
                throw new InvalidExtensionException('选项必须为对象', [$path => 'object']);
            }
            $value = $item['value'] ?? null;
            $label = $item['label'] ?? null;
            if (!is_string($value) || !is_string($label) || count($item) !== 2) {
                throw new InvalidExtensionException('选项字段无效', [$path => 'value,label']);
            }
            $options[] = new OptionItem($value, $label);
        }

        return $options;
    }
}
