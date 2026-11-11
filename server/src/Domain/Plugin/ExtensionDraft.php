<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 签名清单中的一条扩展位声明。由工厂校验；不含插件标识（由 ExtensionCatalog 归组给出）。
 * 声明里的处理类名、组件名等可执行目标只存在于清单与编译产物，不落库。
 */
final readonly class ExtensionDraft
{
    /**
     * @param list<OptionItem> $options
     */
    private function __construct(
        public ExtensionKind $kind,
        public string $key,
        public string $title,
        public int $sort,
        public bool $enabled,
        public string $description,
        public ?string $icon,
        public ?string $handler,
        public ?string $placeholder,
        public ?string $channel,
        public ?ScheduleDefinition $schedule,
        public bool $scheduleLocked,
        public bool $isCritical,
        public ?string $ownerType,
        public bool $multiple,
        public ?string $component,
        public int $width,
        public int $height,
        public array $options,
    ) {
    }

    public static function settingsCategory(
        string $key,
        string $title,
        string $description = '',
        ?string $icon = null,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::SettingsCategory,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            ExtensionRules::description($description),
            ExtensionRules::icon($icon),
            null,
            null,
            null,
            null,
            false,
            false,
            null,
            false,
            null,
            1,
            1,
            [],
        );
    }

    public static function personalSettingsCategory(
        string $key,
        string $title,
        string $description = '',
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::PersonalSettingsCategory,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            ExtensionRules::description($description),
            null,
            null,
            null,
            null,
            null,
            false,
            false,
            null,
            false,
            null,
            1,
            1,
            [],
        );
    }

    public static function searchSource(
        string $key,
        string $title,
        string $handler,
        ?string $placeholder = null,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::SearchSource,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            '',
            null,
            ExtensionRules::className($handler, 'handler'),
            ExtensionRules::placeholder($placeholder),
            null,
            null,
            false,
            false,
            null,
            false,
            null,
            1,
            1,
            [],
        );
    }

    public static function notificationSource(
        string $key,
        string $title,
        string $channel,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::NotificationSource,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            '',
            null,
            null,
            null,
            ExtensionRules::channel($channel),
            null,
            false,
            false,
            null,
            false,
            null,
            1,
            1,
            [],
        );
    }

    public static function cronTask(
        string $key,
        string $title,
        string $handler,
        ScheduleDefinition $schedule,
        string $description = '',
        bool $scheduleLocked = false,
        bool $isCritical = false,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::CronTask,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            ExtensionRules::description($description),
            null,
            ExtensionRules::className($handler, 'handler'),
            null,
            null,
            $schedule,
            $scheduleLocked,
            $isCritical,
            null,
            false,
            null,
            1,
            1,
            [],
        );
    }

    public static function fileUsage(
        string $key,
        string $title,
        string $ownerType,
        bool $multiple = false,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::FileUsage,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            '',
            null,
            null,
            null,
            null,
            null,
            false,
            false,
            ExtensionRules::ownerType($ownerType),
            $multiple,
            null,
            1,
            1,
            [],
        );
    }

    public static function workbenchCard(
        string $key,
        string $title,
        string $component,
        int $width = 1,
        int $height = 1,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::WorkbenchCard,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            '',
            null,
            null,
            null,
            null,
            null,
            false,
            false,
            null,
            false,
            ExtensionRules::component($component),
            ExtensionRules::span($width, 'width'),
            ExtensionRules::span($height, 'height'),
            [],
        );
    }

    /** @param list<OptionItem> $options */
    public static function optionGroup(
        string $key,
        string $title,
        array $options,
        int $sort = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::OptionGroup,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sort($sort),
            $enabled,
            '',
            null,
            null,
            null,
            null,
            null,
            false,
            false,
            null,
            false,
            null,
            1,
            1,
            ExtensionRules::options($options),
        );
    }
}
