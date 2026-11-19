<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 内存版账本：不传 stateFile 时进程退出即丢，只作占位；
 * 传入 stateFile 后按 JSON 落盘，tools/package-ops --registry memory
 * 的无库演练靠它把装/升/卸的状态跨命令进程连起来，不能当真实登记用。
 * 共享同一 stateFile 的进程间靠旁路锁文件串行化：后到命令排队，
 * 读到的都是前一个命令写完的最新状态。
 */
final class MemoryPluginRegistry implements PluginRegistry
{
    /** @var array<string, array{version: string, state: string}> */
    private array $rows = [];

    /** @var array<string, resource> 本进程已握的账本锁：flock 不可重入，同一 stateFile 二次实例化不能再 flock 死等 */
    private static array $locks = [];

    public function __construct(private readonly ?string $stateFile = null)
    {
        if ($stateFile === null) {
            return;
        }
        $dir = dirname($stateFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法创建演练账本目录：' . $dir);
        }
        if (!isset(self::$locks[$stateFile])) {
            $lock = fopen($stateFile . '.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_EX)) {
                throw new \RuntimeException('无法锁定演练账本：' . $stateFile);
            }
            self::$locks[$stateFile] = $lock;
        }
        if (!is_file($stateFile)) {
            return;
        }
        // 账本存在但读不出内容时直接报错，不能装成空账本让已装记录凭空消失
        $raw = file_get_contents($stateFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('演练账本解析失败：' . $stateFile);
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

    /** 先写临时文件再原子改名：进程死在半途也不会留下半个账本；写失败要抛出来。 */
    private function save(): void
    {
        if ($this->stateFile === null) {
            return;
        }
        $encoded = json_encode($this->rows, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new \RuntimeException('无法编码演练账本');
        }
        $stagingFile = $this->stateFile . '.staging-' . getmypid();
        if (file_put_contents($stagingFile, $encoded) === false || !rename($stagingFile, $this->stateFile)) {
            throw new \RuntimeException('无法写演练账本：' . $this->stateFile);
        }
    }
}
