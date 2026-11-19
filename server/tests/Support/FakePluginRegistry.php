<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Module\Package\PluginRegistry;

/** 内存版 PluginRegistry：单元测试用，连 row 状态一起记，方便断言 uninstalled。 */
final class FakePluginRegistry implements PluginRegistry
{
    /** @var array<string, array{name: string, version: string, sha256: string, state: string}> */
    private array $rows = [];

    public function installedVersion(string $app): ?string
    {
        $row = $this->rows[$app] ?? null;

        return $row !== null && $row['state'] === 'installed' ? $row['version'] : null;
    }

    /** @return array<string, string> */
    public function installedVersions(): array
    {
        $versions = [];
        foreach ($this->rows as $app => $row) {
            if ($row['state'] === 'installed') {
                $versions[$app] = $row['version'];
            }
        }

        return $versions;
    }

    public function recordInstall(string $app, string $name, string $version, string $manifestSha256): void
    {
        $this->rows[$app] = [
            'name' => $name,
            'version' => $version,
            'sha256' => $manifestSha256,
            'state' => 'installed',
        ];
    }

    public function recordUninstall(string $app, bool $purge): void
    {
        if ($purge) {
            unset($this->rows[$app]);

            return;
        }
        if (isset($this->rows[$app])) {
            $this->rows[$app]['state'] = 'uninstalled';
        }
    }

    /** 行的原始状态：installed / uninstalled / null（无行），断言用。 */
    public function stateOf(string $app): ?string
    {
        return $this->rows[$app]['state'] ?? null;
    }
}
