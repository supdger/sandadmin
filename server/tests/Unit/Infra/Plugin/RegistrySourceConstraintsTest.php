<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Audit\AuditModuleOption;
use Sandadmin\Domain\Plugin\CronTask;
use Sandadmin\Domain\Plugin\DisableCheckItem;
use Sandadmin\Domain\Plugin\EffectiveExtension;
use Sandadmin\Domain\Plugin\ExtensionCatalog;
use Sandadmin\Domain\Plugin\ExtensionCodec;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionManifest;
use Sandadmin\Domain\Plugin\ExtensionOverride;
use Sandadmin\Domain\Plugin\ExtensionResolver;
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\FileUsageAuthorizer;
use Sandadmin\Domain\Plugin\FileUsageRegistration;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\NotificationSource;
use Sandadmin\Domain\Plugin\OptionGroup;
use Sandadmin\Domain\Plugin\OptionItem;
use Sandadmin\Domain\Plugin\PersonalSettingsCategory;
use Sandadmin\Domain\Plugin\PluginExtensions;
use Sandadmin\Domain\Plugin\ScheduleDefinition;
use Sandadmin\Domain\Plugin\ScheduleFreq;
use Sandadmin\Domain\Plugin\ScheduleNextRun;
use Sandadmin\Domain\Plugin\ScheduleOverride;
use Sandadmin\Domain\Plugin\SearchSource;
use Sandadmin\Domain\Plugin\SettingsCategory;
use Sandadmin\Domain\Plugin\UserDisableCheck;
use Sandadmin\Domain\Plugin\UserTransfer;
use Sandadmin\Domain\Plugin\WorkbenchCard;
use Sandadmin\Infra\Audit\AuditModuleOptions;
use Sandadmin\Infra\Plugin\ExtensionCallbacks;
use Sandadmin\Infra\Plugin\ExtensionRegistry;
use Sandadmin\Infra\Plugin\PluginDictRepository;
use Sandadmin\Infra\Plugin\PluginExtensionRepository;
use Sandadmin\Infra\Plugin\PluginResidue;
use Sandadmin\Infra\Plugin\ScheduleClaim;

final class RegistrySourceConstraintsTest extends TestCase
{
    public function testProductionCodeDoesNotScanDirectoriesOrKeepStaticState(): void
    {
        $roots = [
            dirname(__DIR__, 4) . '/src/Domain/Plugin',
            dirname(__DIR__, 4) . '/src/Infra/Plugin',
        ];
        $needles = [
            'scandir(',
            'glob(',
            'opendir(',
            'readdir(',
            'RecursiveDirectoryIterator',
            'DirectoryIterator',
            'FilesystemIterator',
            'GlobIterator',
            '$GLOBALS',
            '$_SESSION',
            '$_ENV',
            '$_SERVER',
            'getenv(',
            'putenv(',
            'file_get_contents(',
            'file_put_contents(',
            'include ',
            'include_once',
            'require ',
            'require_once',
            'EntityManager',
            'static $',
            'eval(',
            'extract(',
            'compact(',
            '__get',
            '__set',
            '__call',
        ];
        $files = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        // 合并 #75（33 个）与 #76（新增 PluginResidue、ScheduleClaim 两个 Infra 源文件）后为 35 个；
        // J11 再加 Domain/Plugin/HostSchedule.php 为 36 个；
        // 重算依据：find src/Domain/Plugin src/Infra/Plugin -name '*.php'，30 + 6 = 36。
        self::assertCount(36, $files);
        foreach ($files as $path) {
            $source = file_get_contents($path);
            if (!is_string($source)) {
                self::fail($path . ' 无法读取');
            }
            self::assertStringContainsString('declare(strict_types=1);', $source, $path);
            self::assertDoesNotMatchRegularExpression('/^class /m', $source, $path);
            foreach ($needles as $needle) {
                self::assertStringNotContainsString($needle, $source, $path . ' contains ' . $needle);
            }
        }
    }

    public function testRegistryTypesAreFinalAndHaveNoStaticProperties(): void
    {
        foreach ([
            ExtensionRules::class,
            ExtensionCodec::class,
            ExtensionManifest::class,
            ExtensionDraft::class,
            ExtensionResolver::class,
            ExtensionCatalog::class,
            PluginExtensions::class,
            EffectiveExtension::class,
            ExtensionOverride::class,
            DisableCheckItem::class,
            SettingsCategory::class,
            PersonalSettingsCategory::class,
            SearchSource::class,
            NotificationSource::class,
            CronTask::class,
            FileUsageRegistration::class,
            WorkbenchCard::class,
            OptionGroup::class,
            OptionItem::class,
            ScheduleDefinition::class,
            ScheduleOverride::class,
            ScheduleNextRun::class,
            InvalidExtensionException::class,
            ExtensionKind::class,
            ScheduleFreq::class,
            ExtensionRegistry::class,
            PluginExtensionRepository::class,
            PluginResidue::class,
            ScheduleClaim::class,
            PluginDictRepository::class,
            ExtensionCallbacks::class,
            AuditModuleOption::class,
            AuditModuleOptions::class,
        ] as $class) {
            $ref = new \ReflectionClass($class);
            self::assertTrue($ref->isFinal(), $class);
            foreach ($ref->getProperties() as $property) {
                self::assertFalse($property->isStatic(), $class . '::$' . $property->getName());
            }
        }
    }

    public function testValueObjectsExposeOnlyReadonlyProperties(): void
    {
        foreach ([
            ExtensionDraft::class,
            ExtensionCatalog::class,
            PluginExtensions::class,
            EffectiveExtension::class,
            ExtensionOverride::class,
            DisableCheckItem::class,
            SettingsCategory::class,
            PersonalSettingsCategory::class,
            SearchSource::class,
            NotificationSource::class,
            CronTask::class,
            FileUsageRegistration::class,
            WorkbenchCard::class,
            OptionGroup::class,
            OptionItem::class,
            ScheduleDefinition::class,
            ScheduleOverride::class,
            ExtensionRegistry::class,
            PluginExtensionRepository::class,
            PluginResidue::class,
            ScheduleClaim::class,
            PluginDictRepository::class,
            AuditModuleOption::class,
            AuditModuleOptions::class,
        ] as $class) {
            $ref = new \ReflectionClass($class);
            foreach ($ref->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }
                self::assertTrue($property->isReadOnly(), $class . '::$' . $property->getName());
            }
        }
    }

    public function testCallbackContractsAreInterfaces(): void
    {
        self::assertTrue((new \ReflectionClass(UserDisableCheck::class))->isInterface());
        self::assertTrue((new \ReflectionClass(UserTransfer::class))->isInterface());
        self::assertTrue((new \ReflectionClass(FileUsageAuthorizer::class))->isInterface());
    }
}
