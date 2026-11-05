<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\CronJob;
use Sandadmin\Domain\Plugin\ExtensionCodec;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\OptionItem;

final class ExtensionDraftTest extends TestCase
{
    public function testNormalizesCronAndRoundTrips(): void
    {
        $draft = ExtensionDraft::cronJob('nightly', ' 夜间清理 ', '0  3 * * *', 'Acme\\Jobs\\Cleanup', ' Asia/Shanghai ');
        self::assertSame(ExtensionKind::CronJob, $draft->kind);
        self::assertSame('夜间清理', $draft->title);
        self::assertSame('0 3 * * *', $draft->expression);
        self::assertSame('Asia/Shanghai', $draft->timezone);
        $payload = ExtensionCodec::payload($draft);
        $job = ExtensionCodec::cronJob('sand-iam', $draft->key, $draft->title, $draft->sortOrder, $payload);
        self::assertInstanceOf(CronJob::class, $job);
        self::assertSame('sand-iam', $job->pluginId);
        self::assertSame('Acme\\Jobs\\Cleanup', $job->handler);
        self::assertSame([
            'plugin_id' => 'sand-iam',
            'key' => 'nightly',
            'title' => '夜间清理',
            'expression' => '0 3 * * *',
            'handler' => 'Acme\\Jobs\\Cleanup',
            'timezone' => 'Asia/Shanghai',
            'sort_order' => 0,
        ], $job->jsonSerialize());
    }

    public function testAcceptsSixFieldCron(): void
    {
        $draft = ExtensionDraft::cronJob('tick', '心跳', '*/5 * * * * *', 'Acme\\Tick');
        self::assertSame('*/5 * * * * *', $draft->expression);
    }

    public function testRejectsBadCronTimezoneAndClass(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionDraft::cronJob('nightly', '夜间', 'nope', 'Acme\\Job');
    }

    public function testRejectsUnknownTimezone(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionDraft::cronJob('nightly', '夜间', '0 3 * * *', 'Acme\\Job', 'Not/AZone');
    }

    public function testRejectsUnsafeHandler(): void
    {
        $this->expectException(InvalidExtensionException::class);
        ExtensionDraft::searchSource('docs', '文档', 'Acme\\Job;system');
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

    public function testSettingsPayloadOmitsOtherKinds(): void
    {
        $draft = ExtensionDraft::settingsCategory('general', '通用', '说明', 'icon-setting', 3, false);
        self::assertFalse($draft->enabled);
        self::assertSame([
            'description' => '说明',
            'icon' => 'icon-setting',
        ], ExtensionCodec::payload($draft));
        $view = ExtensionCodec::settingsCategory('acme', $draft->key, $draft->title, $draft->sortOrder, ExtensionCodec::payload($draft));
        self::assertSame('说明', $view->description);
        self::assertSame('icon-setting', $view->icon);
        self::assertSame(3, $view->sortOrder);
    }

    public function testEmptyIconBecomesNull(): void
    {
        $draft = ExtensionDraft::settingsCategory('general', '通用', '', '  ');
        self::assertNull($draft->icon);
        self::assertNull(ExtensionCodec::payload($draft)['icon']);
    }
}
