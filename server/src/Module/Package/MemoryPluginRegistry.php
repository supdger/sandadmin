<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 内存版账本：不传 stateFile 时进程退出即丢，只作占位；
 * 传入 stateFile 后按 JSON 落盘，tools/package-ops --registry memory
 * 的无库演练靠它把装/升/卸的状态跨命令进程连起来，不能当真实登记用。
 */
final class MemoryPluginRegistry implements PluginRegistry
{
    /** @var array<string, array{version: string, state: string}> */
    private array $rows = [];

    public function __construct(private readonly ?string $stateFile = null)
    {
        if ($stateFile === null || !is_file($stateFile)) {
            return;
        }
        $raw = file_get_contents($stateFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return;
        }
        foreach ($decoded as $app => $row) {
            if (
                is_array($row)
                && is_string($row['version'] ?? null)
                && is_string($row['state'] ?? null)
            ) {
                $this->rows[(string) $app] = ['version' => $row['version'], 'state' => $row['state']];
            }
        }
    }

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
        $this->save();
    }

    public function recordUninstall(string $app, bool $purge): void
    {
        if ($purge) {
            unset($this->rows[$app]);
            $this->save();

            return;
        }
        if (isset($this->rows[$app])) {
            $this->rows[$app]['state'] = 'uninstalled';
            $this->save();
        }
    }

    /** 落盘失败要抛出来：账本悄悄失真比直接报错更难查。 */
    private function save(): void
    {
        if ($this->stateFile === null) {
            return;
        }
        $encoded = json_encode($this->rows, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($this->stateFile, $encoded) === false) {
            throw new \RuntimeException('无法写演练账本：' . $this->stateFile);
        }
    }
}
