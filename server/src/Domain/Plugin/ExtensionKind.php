<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 插件扩展位种类。kind 列取值与 sand_system_plugin_extension 的 CHECK 约束一致。 */
enum ExtensionKind: string
{
    case SettingsCategory = 'settings_category';
    case PersonalSettingsCategory = 'personal_settings_category';
    case SearchSource = 'search_source';
    case NotificationSource = 'notification_source';
    case CronTask = 'cron_task';
    case FileUsage = 'file_usage';
    case WorkbenchCard = 'workbench_card';
    case OptionGroup = 'option_group';

    /** 清单中承载该类扩展声明的段名。 */
    public function manifestSection(): string
    {
        return match ($this) {
            self::SettingsCategory => 'settings_categories',
            self::PersonalSettingsCategory => 'personal_settings_categories',
            self::SearchSource => 'search_sources',
            self::NotificationSource => 'notification_sources',
            self::CronTask => 'cron_tasks',
            self::FileUsage => 'file_usages',
            self::WorkbenchCard => 'workbench_cards',
            self::OptionGroup => 'option_groups',
        };
    }

    public static function tryFromManifestSection(string $section): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->manifestSection() === $section) {
                return $case;
            }
        }

        return null;
    }
}
