<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Org;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Org\DeptLeader;

/** DeptLeader 序列化：未设置负责人时字段全 null，对应契约「按未设置」口径。 */
final class DeptLeaderTest extends TestCase
{
    public function testSerializeUnset(): void
    {
        $view = new DeptLeader(3, null, null, null, null);

        self::assertSame(
            [
                'dept_id' => 3,
                'leader_id' => null,
                'leader_name' => null,
                'leader_avatar_file_id' => null,
                'leader_status' => null,
            ],
            $view->jsonSerialize(),
        );
    }

    public function testSerializeDisabledLeader(): void
    {
        $view = new DeptLeader(3, 9, '李四', 12, 'disabled');

        self::assertSame(
            [
                'dept_id' => 3,
                'leader_id' => 9,
                'leader_name' => '李四',
                'leader_avatar_file_id' => 12,
                'leader_status' => 'disabled',
            ],
            $view->jsonSerialize(),
        );
    }
}
