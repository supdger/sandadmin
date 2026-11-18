<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\Stream\StreamPermissionProbe;

/** 映射表实现的权限探针：set() 改版本或置 null 模拟撤权/探针故障。 */
final class MapPermissionProbe implements StreamPermissionProbe
{
    /** @var array<string, int|null> */
    private array $versions;

    /** @param array<string, int|null> $versions */
    public function __construct(array $versions = [])
    {
        $this->versions = $versions;
    }

    public function authzVersion(string $subject): ?int
    {
        return $this->versions[$subject] ?? null;
    }

    public function set(string $subject, ?int $version): void
    {
        $this->versions[$subject] = $version;
    }
}
