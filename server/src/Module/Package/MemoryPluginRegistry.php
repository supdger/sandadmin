<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 内存版账本：进程退出即丢，仅供 tools/package-ops --registry memory
 * 的本地无库演练与 recover 这种不碰账本的场景占位，不能当真实登记用。
 */
final class MemoryPluginRegistry implements PluginRegistry
{
    /** @var array<string, array{version: string, state: string}> */
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
        $this->rows[$app] = ['version' => $version, 'state' => 'installed'];
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
}
