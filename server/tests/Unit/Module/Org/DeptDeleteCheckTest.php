<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Org;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Org\DeptDeleteCheck;

/** 序列化键与契约 DeptDeleteCheck 一致：user_count / child_count / affected_roles。 */
final class DeptDeleteCheckTest extends TestCase
{
    public function testSerializeShape(): void
    {
        $check = new DeptDeleteCheck(2, 1, [['id' => 7, 'name' => '只看技术部']]);

        self::assertSame(
            [
                'user_count' => 2,
                'child_count' => 1,
                'affected_roles' => [['id' => 7, 'name' => '只看技术部']],
            ],
            $check->jsonSerialize(),
        );
    }

    public function testSerializeEmptyRoles(): void
    {
        $check = new DeptDeleteCheck(0, 0, []);

        self::assertSame(
            ['user_count' => 0, 'child_count' => 0, 'affected_roles' => []],
            $check->jsonSerialize(),
        );
    }
}
