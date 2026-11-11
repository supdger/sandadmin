<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Sandadmin\Domain\Plugin\CronTask;
use Sandadmin\Domain\Plugin\DisableCheckItem;
use Sandadmin\Domain\Plugin\EffectiveExtension;
use Sandadmin\Domain\Plugin\ExtensionCatalog;
use Sandadmin\Domain\Plugin\ExtensionCodec;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionOverride;
use Sandadmin\Domain\Plugin\ExtensionResolver;
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\FileUsageAuthorizer;
use Sandadmin\Domain\Plugin\FileUsageRegistration;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\NotificationSource;
use Sandadmin\Domain\Plugin\OptionGroup;
use Sandadmin\Domain\Plugin\PersonalSettingsCategory;
use Sandadmin\Domain\Plugin\SearchSource;
use Sandadmin\Domain\Plugin\SettingsCategory;
use Sandadmin\Domain\Plugin\UserDisableCheck;
use Sandadmin\Domain\Plugin\UserTransfer;
use Sandadmin\Domain\Plugin\WorkbenchCard;

/**
 * 插件扩展位注册表。
 *
 * 扩展定义来自签名清单编译产物（ExtensionCatalog），sand_system_plugin_extension
 * 只存管理员的启停与排序覆盖；运行时按「清单声明 + 覆盖行」合并出启用项。
 * 不扫描插件目录，不在进程内缓存合并结果；用户停用前检查与转交两个回调
 * 由插件注册，本类只做汇总与编排。
 */
final readonly class ExtensionRegistry
{
    private ExtensionCallbacks $callbacks;

    public function __construct(
        private PluginExtensionRepository $extensions,
        private ExtensionCatalog $catalog,
    ) {
        $this->callbacks = new ExtensionCallbacks();
    }

    /** 注册「用户停用前检查」回调；同一插件重复注册会被拒绝。 */
    public function registerDisableCheck(string $pluginCode, UserDisableCheck $check): void
    {
        $this->callbacks->addDisableCheck($pluginCode, $check);
    }

    /** 注册「转交」回调；停用/删除用户传 transfer_to_user_id 时由核心逐插件调用。 */
    public function registerUserTransfer(string $pluginCode, UserTransfer $transfer): void
    {
        $this->callbacks->addUserTransfer($pluginCode, $transfer);
    }

    /** 按 sand_system_file_usage.subject_type 注册「文件引用鉴权」回调。 */
    public function registerFileUsageAuthorizer(string $subjectType, FileUsageAuthorizer $authorizer): void
    {
        $this->callbacks->addFileUsageAuthorizer($subjectType, $authorizer);
    }

    /**
     * 按引用方规则判定文件访问。$usages 为 sand_system_file_usage 的
     * (subject_type, subject_id) 引用对；每条引用必须由其 subject_type
     * 注册的鉴权回调放行，某 subject_type 无注册回调视为拒绝。
     * 空引用返回 true（是否引用由文件模块另行判定）。
     *
     * @param list<array{subject_type: string, subject_id: string}> $usages
     */
    public function authorizeFileUsage(int $userId, int $fileId, array $usages): bool
    {
        foreach ($usages as $usage) {
            $authorizer = $this->callbacks->fileUsageAuthorizer($usage['subject_type']);
            if ($authorizer === null) {
                return false;
            }
            if (!$authorizer->authorize($userId, $fileId, $usage['subject_type'], $usage['subject_id'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 汇总各插件「用户停用前检查」结果，形状对应 disable-check 接口的
     * UserDisableCheckItem 数组；空数组表示无阻碍。
     *
     * @return list<DisableCheckItem>
     */
    public function disableCheckItems(int $userId): array
    {
        $items = [];
        foreach ($this->callbacks->disableChecks() as $check) {
            foreach ($check->pendingItems($userId) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** 逐插件执行「转交」回调；任一回调抛错即中断并向上传播。 */
    public function transferUserItems(int $userId, int $toUserId): void
    {
        foreach ($this->callbacks->userTransfers() as $transfer) {
            $transfer->transfer($userId, $toUserId);
        }
    }

    /**
     * 写入管理员对某扩展位的启停与排序覆盖。
     * 扩展必须已在签名清单中声明，否则拒绝，避免产生悬空覆盖行。
     */
    public function saveOverride(string $pluginCode, ExtensionKind $kind, string $extensionKey, bool $enabled, int $sort): void
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        $extensionKey = ExtensionRules::key($extensionKey);
        $sort = ExtensionRules::sort($sort);
        $this->assertDeclared($pluginCode, $kind, $extensionKey);
        $this->extensions->save(
            new ExtensionOverride($pluginCode, $kind, $extensionKey, $enabled, $sort),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** 删除覆盖行，扩展恢复清单默认；目标不存在时不报错。 */
    public function resetOverride(string $pluginCode, ExtensionKind $kind, string $extensionKey): void
    {
        $this->extensions->delete(
            ExtensionRules::pluginCode($pluginCode),
            $kind,
            ExtensionRules::key($extensionKey),
        );
    }

    /** @return list<SettingsCategory> */
    public function settingsCategories(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::SettingsCategory) as $extension) {
            $out[] = ExtensionCodec::settingsCategory($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<PersonalSettingsCategory> */
    public function personalSettingsCategories(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::PersonalSettingsCategory) as $extension) {
            $out[] = ExtensionCodec::personalSettingsCategory($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<SearchSource> */
    public function searchSources(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::SearchSource) as $extension) {
            $out[] = ExtensionCodec::searchSource($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<NotificationSource> */
    public function notificationSources(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::NotificationSource) as $extension) {
            $out[] = ExtensionCodec::notificationSource($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<CronTask> */
    public function cronTasks(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::CronTask) as $extension) {
            $out[] = ExtensionCodec::cronTask($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<FileUsageRegistration> */
    public function fileUsageRegistrations(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::FileUsage) as $extension) {
            $out[] = ExtensionCodec::fileUsage($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<WorkbenchCard> */
    public function workbenchCards(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::WorkbenchCard) as $extension) {
            $out[] = ExtensionCodec::workbenchCard($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<OptionGroup> */
    public function optionGroups(): array
    {
        $out = [];
        foreach ($this->resolve(ExtensionKind::OptionGroup) as $extension) {
            $out[] = ExtensionCodec::optionGroup($extension->pluginCode, $extension->draft, $extension->sort);
        }

        return $out;
    }

    /** @return list<EffectiveExtension> */
    private function resolve(ExtensionKind $kind): array
    {
        return ExtensionResolver::resolve($kind, $this->catalog->plugins(), $this->extensions->listByKind($kind));
    }

    private function assertDeclared(string $pluginCode, ExtensionKind $kind, string $extensionKey): void
    {
        $plugin = $this->catalog->plugin($pluginCode);
        if ($plugin === null) {
            throw new InvalidExtensionException('插件未在清单中声明', ['plugin_code' => 'undeclared']);
        }
        foreach ($plugin->extensions as $draft) {
            if ($draft->kind === $kind && $draft->key === $extensionKey) {
                return;
            }
        }
        throw new InvalidExtensionException('扩展位未在清单中声明', ['key' => $kind->value . ':' . $extensionKey]);
    }
}
