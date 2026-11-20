<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support\Factory;

use Sandadmin\Module\DemoNote\DemoNote;

/** Unit 用内存工厂：不连库造 sand_demo_note 行值对象。 */
final class DemoNoteFactory
{
    private const FIXED_TIME = '2026-01-01T00:00:00+00:00';

    public static function make(
        int $id = 1,
        string $title = '测试便签',
        int $status = 1,
        ?\DateTimeImmutable $createTime = null,
    ): DemoNote {
        return new DemoNote(
            $id,
            $title,
            $status,
            $createTime ?? new \DateTimeImmutable(self::FIXED_TIME),
        );
    }
}
