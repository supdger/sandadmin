<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 插件扩展位种类。值写入 plugin_extension.kind，安装后只按此列读取。 */
enum ExtensionKind: string
{
    case SettingsCategory = 'settings_category';
    case PersonalSettingsCategory = 'personal_settings_category';
    case SearchSource = 'search_source';
    case NotificationSource = 'notification_source';
    case CronJob = 'cron_job';
    case FileUsage = 'file_usage';
    case WorkbenchCard = 'workbench_card';
    case OptionGroup = 'option_group';

    public function manifestSection(): string
    {
        return match ($this) {
            self::SettingsCategory => 'settings_categories',
            self::PersonalSettingsCategory => 'personal_settings_categories',
            self::SearchSource => 'search_sources',
            self::NotificationSource => 'notification_sources',
            self::CronJob => 'cron_jobs',
            self::FileUsage => 'file_usages',
            self::WorkbenchCard => 'workbench_cards',
            self::OptionGroup => 'option_groups',
        };
    }

    public static function fromManifestSection(string $section): self
    {
        foreach (self::cases() as $case) {
            if ($case->manifestSection() === $section) {
                return $case;
            }
        }

        throw new InvalidExtensionException('未知扩展清单段', ['manifest' => $section]);
    }
}
