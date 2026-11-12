<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionManifest;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\OptionItem;
use Sandadmin\Domain\Plugin\ScheduleFreq;

final class ExtensionManifestTest extends TestCase
{
    public function testParsesEverySectionFromMemory(): void
    {
        $drafts = ExtensionManifest::drafts([
            'settings_categories' => [
                ['key' => 'general', 'title' => '通用', 'description' => '站点', 'icon' => 'setting', 'sort' => 2],
            ],
            'personal_settings_categories' => [
                ['key' => 'profile', 'title' => '资料'],
            ],
            'search_sources' => [
                ['key' => 'docs', 'title' => '文档', 'handler' => 'Acme\\Search', 'placeholder' => '搜索'],
            ],
            'notification_sources' => [
                ['key' => 'inbox', 'title' => '收件箱', 'channel' => 'inbox'],
            ],
            'cron_tasks' => [
                [
                    'key' => 'nightly',
                    'title' => '夜间',
                    'handler' => 'Acme\\Task',
                    'schedule' => ['freq' => 'daily', 'time' => '03:00'],
                    'enabled' => false,
                ],
            ],
            'file_usages' => [
                ['key' => 'cover', 'title' => '封面', 'owner_type' => 'article.cover', 'multiple' => true],
            ],
            'workbench_cards' => [
                ['key' => 'welcome', 'title' => '欢迎', 'component' => 'WelcomeCard', 'width' => 2, 'height' => 1],
            ],
            'option_groups' => [
                ['key' => 'status', 'title' => '状态', 'options' => [
                    ['value' => 'on', 'label' => '开'],
                    ['value' => 'off', 'label' => '关'],
                ]],
            ],
        ]);

        self::assertCount(8, $drafts);
        $kinds = array_map(static fn (ExtensionDraft $draft): string => $draft->kind->value, $drafts);
        self::assertSame(array_map(static fn (ExtensionKind $kind): string => $kind->value, ExtensionKind::cases()), $kinds);
        $cron = null;
        $file = null;
        $options = null;
        foreach ($drafts as $draft) {
            if ($draft->kind === ExtensionKind::CronTask) {
                $cron = $draft;
            }
            if ($draft->kind === ExtensionKind::FileUsage) {
                $file = $draft;
            }
            if ($draft->kind === ExtensionKind::OptionGroup) {
                $options = $draft;
            }
        }
        if (!$cron instanceof ExtensionDraft || !$file instanceof ExtensionDraft || !$options instanceof ExtensionDraft) {
            self::fail('清单未包含定时任务、文件使用登记或选项组');
        }
        self::assertFalse($cron->enabled);
        self::assertSame(ScheduleFreq::Daily, $cron->schedule?->freq);
        self::assertSame('03:00', $cron->schedule->time);
        self::assertTrue($file->multiple);
        self::assertSame('article.cover', $file->ownerType);
        self::assertSame(['on', 'off'], array_map(static fn (OptionItem $item): string => $item->value, $options->options));
    }

    public function testSkipsNonExtensionSections(): void
    {
        $drafts = ExtensionManifest::drafts([
            'menus' => [['key' => 'dashboard', 'title' => '仪表盘']],
            'permissions' => [['code' => 'system:user:list']],
            'settings_categories' => [
                ['key' => 'general', 'title' => '通用'],
            ],
        ]);
        self::assertCount(1, $drafts);
        self::assertSame(ExtensionKind::SettingsCategory, $drafts[0]->kind);
        self::assertSame([], ExtensionManifest::drafts(['menus' => []]));
    }

    public function testEmptyManifestHasNoDrafts(): void
    {
        self::assertSame([], ExtensionManifest::drafts([]));
    }

    public function testRejectsUnknownField(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionManifest::drafts([
            'settings_categories' => [
                ['key' => 'general', 'title' => '通用', 'path' => '/tmp'],
            ],
        ]);
    }

    public function testRejectsNonBoolEnabled(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionManifest::drafts([
            'settings_categories' => [
                ['key' => 'general', 'title' => '通用', 'enabled' => 1],
            ],
        ]);
    }

    public function testRejectsStringSort(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionManifest::drafts([
            'cron_tasks' => [
                ['key' => 'nightly', 'title' => '夜间', 'handler' => 'Acme\\Task', 'schedule' => ['freq' => 'daily', 'time' => '03:00'], 'sort' => '1'],
            ],
        ]);
    }

    public function testRejectsSortBeyondSmallint(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionManifest::drafts([
            'settings_categories' => [
                ['key' => 'general', 'title' => '通用', 'sort' => 40000],
            ],
        ]);
    }

    public function testRejectsUnknownScheduleFreq(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionManifest::drafts([
            'cron_tasks' => [
                ['key' => 'nightly', 'title' => '夜间', 'handler' => 'Acme\\Task', 'schedule' => ['freq' => 'yearly']],
            ],
        ]);
    }

    public function testRejectsScheduleShapeMismatch(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionManifest::drafts([
            'cron_tasks' => [
                ['key' => 'nightly', 'title' => '夜间', 'handler' => 'Acme\\Task', 'schedule' => ['freq' => 'interval', 'time' => '03:00']],
            ],
        ]);
    }
}
