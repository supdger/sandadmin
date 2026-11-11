<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\CronTask;
use Sandadmin\Domain\Plugin\ExtensionCodec;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\OptionItem;
use Sandadmin\Domain\Plugin\ScheduleDefinition;
use Sandadmin\Domain\Plugin\ScheduleFreq;
use Sandadmin\Domain\Plugin\SettingsCategory;

final class ExtensionDraftTest extends TestCase
{
    public function testCronTaskDraftMaterializesWithSchedule(): void
    {
        $draft = ExtensionDraft::cronTask(
            'nightly',
            ' 夜间清理 ',
            'Acme\\Tasks\\Cleanup',
            new ScheduleDefinition(ScheduleFreq::Daily, time: '03:00'),
            ' 每日清理 ',
            true,
            true,
            3,
        );
        self::assertSame(ExtensionKind::CronTask, $draft->kind);
        self::assertSame('夜间清理', $draft->title);
        self::assertSame(ScheduleFreq::Daily, $draft->schedule?->freq);
        self::assertTrue($draft->scheduleLocked);
        self::assertTrue($draft->isCritical);

        $task = ExtensionCodec::cronTask('sand-iam', $draft, $draft->sort);
        self::assertInstanceOf(CronTask::class, $task);
        self::assertSame('sand-iam', $task->pluginCode);
        self::assertSame('Acme\\Tasks\\Cleanup', $task->handler);
        self::assertSame('03:00', $task->schedule->time);
        self::assertSame('每日清理', $task->description);
        self::assertSame(3, $task->sort);
    }

    public function testCronTaskWithoutDescriptionSerializesNull(): void
    {
        $draft = ExtensionDraft::cronTask(
            'tick',
            '心跳',
            'Acme\\Tick',
            new ScheduleDefinition(ScheduleFreq::Interval, interval: 5),
        );
        $task = ExtensionCodec::cronTask('acme', $draft, $draft->sort);
        self::assertNull($task->description);
        self::assertSame(5, $task->schedule->interval);
    }

    public function testRejectsUnsafeHandler(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionDraft::searchSource('docs', '文档', 'Acme\\Task;system');
    }

    public function testOptionGroupRejectsDuplicateValues(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionDraft::optionGroup('status', '状态', [
            new OptionItem('on', '开'),
            new OptionItem('on', '启用'),
        ]);
    }

    public function testRejectsControlCharactersInTitle(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionDraft::settingsCategory('general', "通\n用");
    }

    public function testSettingsCategoryMaterializesFromDraft(): void
    {
        $draft = ExtensionDraft::settingsCategory('general', '通用', '说明', 'icon-setting', 3, false);
        self::assertFalse($draft->enabled);
        $view = ExtensionCodec::settingsCategory('acme', $draft, $draft->sort);
        self::assertInstanceOf(SettingsCategory::class, $view);
        self::assertSame('acme', $view->pluginCode);
        self::assertSame('说明', $view->description);
        self::assertSame('icon-setting', $view->icon);
        self::assertSame(3, $view->sort);
    }

    public function testEmptyIconBecomesNull(): void
    {
        $draft = ExtensionDraft::settingsCategory('general', '通用', '', '  ');
        self::assertNull($draft->icon);
    }

    public function testCodecRejectsKindMismatch(): void
    {
        $draft = ExtensionDraft::settingsCategory('general', '通用');
        $this->expectException(InvalidExtensionException::class);
        ExtensionCodec::cronTask('acme', $draft, $draft->sort);
    }
}
