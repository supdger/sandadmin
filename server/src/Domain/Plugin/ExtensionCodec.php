<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 把生效扩展（清单声明 + 覆盖后的 sort）物化成对外领域对象。不访问数据库或文件系统。 */
final class ExtensionCodec
{
    private function __construct()
    {
    }

    public static function settingsCategory(string $pluginCode, ExtensionDraft $draft, int $sort): SettingsCategory
    {
        self::kind($draft, ExtensionKind::SettingsCategory);

        return new SettingsCategory(
            $pluginCode,
            $draft->key,
            $draft->title,
            $draft->description,
            $draft->icon,
            $sort,
        );
    }

    public static function personalSettingsCategory(string $pluginCode, ExtensionDraft $draft, int $sort): PersonalSettingsCategory
    {
        self::kind($draft, ExtensionKind::PersonalSettingsCategory);

        return new PersonalSettingsCategory($pluginCode, $draft->key, $draft->title, $draft->description, $sort);
    }

    public static function searchSource(string $pluginCode, ExtensionDraft $draft, int $sort): SearchSource
    {
        self::kind($draft, ExtensionKind::SearchSource);

        return new SearchSource(
            $pluginCode,
            $draft->key,
            $draft->title,
            self::handler($draft),
            $draft->placeholder,
            $sort,
        );
    }

    public static function notificationSource(string $pluginCode, ExtensionDraft $draft, int $sort): NotificationSource
    {
        self::kind($draft, ExtensionKind::NotificationSource);

        return new NotificationSource($pluginCode, $draft->key, $draft->title, self::channel($draft), $sort);
    }

    public static function cronTask(string $pluginCode, ExtensionDraft $draft, int $sort): CronTask
    {
        self::kind($draft, ExtensionKind::CronTask);

        return new CronTask(
            $pluginCode,
            $draft->key,
            $draft->title,
            self::handler($draft),
            self::schedule($draft),
            $draft->description === '' ? null : $draft->description,
            $draft->scheduleLocked,
            $draft->isCritical,
            $sort,
        );
    }

    public static function fileUsage(string $pluginCode, ExtensionDraft $draft, int $sort): FileUsageRegistration
    {
        self::kind($draft, ExtensionKind::FileUsage);

        return new FileUsageRegistration(
            $pluginCode,
            $draft->key,
            $draft->title,
            self::ownerType($draft),
            $draft->multiple,
            $sort,
        );
    }

    public static function workbenchCard(string $pluginCode, ExtensionDraft $draft, int $sort): WorkbenchCard
    {
        self::kind($draft, ExtensionKind::WorkbenchCard);

        return new WorkbenchCard(
            $pluginCode,
            $draft->key,
            $draft->title,
            self::component($draft),
            $draft->width,
            $draft->height,
            $sort,
        );
    }

    public static function optionGroup(string $pluginCode, ExtensionDraft $draft, int $sort): OptionGroup
    {
        self::kind($draft, ExtensionKind::OptionGroup);

        return new OptionGroup($pluginCode, $draft->key, $draft->title, $draft->options, $sort);
    }

    private static function kind(ExtensionDraft $draft, ExtensionKind $expected): void
    {
        if ($draft->kind !== $expected) {
            throw new InvalidExtensionException('扩展声明类别不匹配', ['kind' => 'enum']);
        }
    }

    private static function handler(ExtensionDraft $draft): string
    {
        if ($draft->handler === null || $draft->handler === '') {
            throw new InvalidExtensionException('扩展声明缺少处理类', ['handler' => 'required']);
        }

        return $draft->handler;
    }

    private static function channel(ExtensionDraft $draft): string
    {
        if ($draft->channel === null || $draft->channel === '') {
            throw new InvalidExtensionException('扩展声明缺少频道', ['channel' => 'required']);
        }

        return $draft->channel;
    }

    private static function schedule(ExtensionDraft $draft): ScheduleDefinition
    {
        if ($draft->schedule === null) {
            throw new InvalidExtensionException('扩展声明缺少时间结构', ['schedule' => 'required']);
        }

        return $draft->schedule;
    }

    private static function ownerType(ExtensionDraft $draft): string
    {
        if ($draft->ownerType === null || $draft->ownerType === '') {
            throw new InvalidExtensionException('扩展声明缺少 owner_type', ['owner_type' => 'required']);
        }

        return $draft->ownerType;
    }

    private static function component(ExtensionDraft $draft): string
    {
        if ($draft->component === null || $draft->component === '') {
            throw new InvalidExtensionException('扩展声明缺少组件', ['component' => 'required']);
        }

        return $draft->component;
    }
}
