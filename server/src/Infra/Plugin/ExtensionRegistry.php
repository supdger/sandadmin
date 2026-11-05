<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Sandadmin\Domain\Plugin\CronJob;
use Sandadmin\Domain\Plugin\ExtensionCodec;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\FileUsageRegistration;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\NotificationSource;
use Sandadmin\Domain\Plugin\OptionGroup;
use Sandadmin\Domain\Plugin\PersonalSettingsCategory;
use Sandadmin\Domain\Plugin\SearchSource;
use Sandadmin\Domain\Plugin\SettingsCategory;
use Sandadmin\Domain\Plugin\WorkbenchCard;

/**
 * 插件扩展位注册表。
 *
 * 安装流程调用 installPlugin / uninstallPlugin 写入 plugin_extension。
 * 运行时只读该表，不扫描插件目录，不加载清单，不在进程内缓存结果。
 */
final readonly class ExtensionRegistry
{
    public function __construct(private PluginExtensionRepository $extensions)
    {
    }

    public function installPlugin(string $pluginId, ExtensionDraft ...$extensions): void
    {
        $pluginId = ExtensionRules::pluginId($pluginId);
        $seen = [];
        foreach ($extensions as $extension) {
            $identity = $extension->kind->value . "\0" . $extension->key;
            if (isset($seen[$identity])) {
                throw new InvalidExtensionException('同一插件内扩展键重复', [
                    'key' => $extension->kind->value . ':' . $extension->key,
                ]);
            }
            $seen[$identity] = true;
        }

        $rows = [];
        foreach ($extensions as $extension) {
            $rows[] = new StoredExtension(
                $pluginId,
                $extension->kind,
                $extension->key,
                $extension->title,
                $extension->sortOrder,
                $extension->enabled,
                ExtensionCodec::payload($extension),
            );
        }

        $this->extensions->replacePlugin($pluginId, $rows, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function uninstallPlugin(string $pluginId): void
    {
        $this->extensions->deletePlugin($pluginId);
    }

    /** @return list<SettingsCategory> */
    public function settingsCategories(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::SettingsCategory) as $row) {
            $out[] = ExtensionCodec::settingsCategory($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<PersonalSettingsCategory> */
    public function personalSettingsCategories(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::PersonalSettingsCategory) as $row) {
            $out[] = ExtensionCodec::personalSettingsCategory($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<SearchSource> */
    public function searchSources(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::SearchSource) as $row) {
            $out[] = ExtensionCodec::searchSource($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<NotificationSource> */
    public function notificationSources(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::NotificationSource) as $row) {
            $out[] = ExtensionCodec::notificationSource($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<CronJob> */
    public function cronJobs(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::CronJob) as $row) {
            $out[] = ExtensionCodec::cronJob($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<FileUsageRegistration> */
    public function fileUsageRegistrations(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::FileUsage) as $row) {
            $out[] = ExtensionCodec::fileUsage($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<WorkbenchCard> */
    public function workbenchCards(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::WorkbenchCard) as $row) {
            $out[] = ExtensionCodec::workbenchCard($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }

    /** @return list<OptionGroup> */
    public function optionGroups(): array
    {
        $out = [];
        foreach ($this->extensions->listEnabled(ExtensionKind::OptionGroup) as $row) {
            $out[] = ExtensionCodec::optionGroup($row->pluginId, $row->key, $row->title, $row->sortOrder, $row->payload);
        }

        return $out;
    }
}
