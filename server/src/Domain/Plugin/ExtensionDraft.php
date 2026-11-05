<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 安装时传入的一条扩展声明。由工厂校验，不含插件标识（由注册表方法参数给出）。 */
final readonly class ExtensionDraft
{
    /**
     * @param list<OptionItem> $options
     */
    private function __construct(
        public ExtensionKind $kind,
        public string $key,
        public string $title,
        public int $sortOrder,
        public bool $enabled,
        public string $description,
        public ?string $icon,
        public ?string $handler,
        public ?string $placeholder,
        public ?string $channel,
        public ?string $expression,
        public string $timezone,
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::SettingsCategory,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            ExtensionRules::description($description),
            ExtensionRules::icon($icon),
            null,
            null,
            null,
            null,
            'UTC',
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::PersonalSettingsCategory,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            ExtensionRules::description($description),
            null,
            null,
            null,
            null,
            null,
            'UTC',
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::SearchSource,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            '',
            null,
            ExtensionRules::className($handler, 'handler'),
            ExtensionRules::placeholder($placeholder),
            null,
            null,
            'UTC',
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::NotificationSource,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            '',
            null,
            null,
            null,
            ExtensionRules::channel($channel),
            null,
            'UTC',
            null,
            false,
            null,
            1,
            1,
            [],
        );
    }

    public static function cronJob(
        string $key,
        string $title,
        string $expression,
        string $handler,
        ?string $timezone = null,
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::CronJob,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            '',
            null,
            ExtensionRules::className($handler, 'handler'),
            null,
            null,
            ExtensionRules::cronExpression($expression),
            ExtensionRules::timezone($timezone),
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::FileUsage,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            '',
            null,
            null,
            null,
            null,
            null,
            'UTC',
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::WorkbenchCard,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            '',
            null,
            null,
            null,
            null,
            null,
            'UTC',
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
        int $sortOrder = 0,
        bool $enabled = true,
    ): self {
        return new self(
            ExtensionKind::OptionGroup,
            ExtensionRules::key($key),
            ExtensionRules::title($title),
            ExtensionRules::sortOrder($sortOrder),
            $enabled,
            '',
            null,
            null,
            null,
            null,
            null,
            'UTC',
            null,
            false,
            null,
            1,
            1,
            ExtensionRules::options($options),
        );
    }
}
