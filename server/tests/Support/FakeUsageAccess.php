<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\File\UsageAccess;

/** 可配置的引用方鉴权替身：默认放行，deny 时一律拒绝。 */
final class FakeUsageAccess implements UsageAccess
{
    public bool $allow = true;

    /** @var list<array{userId: int, fileId: int, usages: int}> */
    public array $calls = [];

    public function authorize(int $userId, int $fileId, array $usages): bool
    {
        $this->calls[] = ['userId' => $userId, 'fileId' => $fileId, 'usages' => count($usages)];

        return $this->allow;
    }
}
