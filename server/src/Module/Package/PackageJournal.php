<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 插件操作的失败恢复 journal：工作目录下一操作一 JSON，
 * 记 kind、app、已完成步骤与 staged/backup/plugin 三个路径。
 * 进程中断后由 recoverPending 按已完成步骤反推回滚：
 * 清掉半成品目录、把 backup 挪回 plugin。账本状态 journal 管不着，
 * 所以卸载只恢复文件：账本未记卸载就把目录挪回去，
 * 账本已记卸载就把遗留 backup 向前删完，不留「已装但文件没了」的中间态。
 */
final class PackageJournal
{
    public function __construct(private readonly string $workDir)
    {
    }

    public function begin(string $kind, string $app, string $dataPolicy, string $plugin): string
    {
        if (!is_dir($this->workDir) && !mkdir($this->workDir, 0755, true) && !is_dir($this->workDir)) {
            throw new \RuntimeException('无法创建 journal 目录：' . $this->workDir);
        }
        $id = $kind . '-' . bin2hex(random_bytes(6));
        $this->write($id, [
            'kind' => $kind,
            'app' => $app,
            'data_policy' => $dataPolicy,
            'steps' => [],
            'staged' => null,
            'backup' => null,
            'plugin' => $plugin,
        ]);

        return $id;
    }

    public function mark(string $id, string $step): void
    {
        $data = $this->read($id);
        if ($data === null) {
            return;
        }
        $data['steps'][] = $step;
        $this->write($id, $data);
    }

    public function setPaths(string $id, ?string $staged = null, ?string $backup = null, ?string $plugin = null): void
    {
        $data = $this->read($id);
        if ($data === null) {
            return;
        }
        if ($staged !== null) {
            $data['staged'] = $staged;
        }
        if ($backup !== null) {
            $data['backup'] = $backup;
        }
        if ($plugin !== null) {
            $data['plugin'] = $plugin;
        }
        $this->write($id, $data);
    }

    public function finish(string $id): void
    {
        $file = $this->fileOf($id);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * 按 journal 记的已完成步骤回滚文件系统，返回恢复记录；
     * journal 不存在或解析不了返回 null。
     */
    public function rollback(string $id): ?string
    {
        $data = $this->read($id);
        if ($data === null) {
            return null;
        }
        $notes = [];
        $staged = $data['staged'];
        $backup = $data['backup'];
        $plugin = $data['plugin'];
        if (is_string($staged) && is_dir($staged)) {
            self::removeTree($staged);
            $notes[] = '清掉暂存目录 ' . $staged;
        }
        if ($data['kind'] === 'uninstall') {
            if (in_array('purged', $data['steps'], true)) {
                // 卸载已收尾，无文件遗留
            } elseif (in_array('unregistered', $data['steps'], true)) {
                // 账本已记卸载，只能向前把遗留备份删完
                if (is_string($backup) && is_dir($backup)) {
                    self::removeTree($backup);
                    $notes[] = '清掉已卸载插件的遗留备份 ' . $backup;
                }
            } elseif (is_string($backup) && is_dir($backup) && is_string($plugin)) {
                // 账本还没记卸载，目录挪回去
                if (!is_dir($plugin) && self::ensureParent($plugin) && rename($backup, $plugin)) {
                    $notes[] = '恢复插件目录 ' . $plugin;
                } else {
                    $notes[] = '无法恢复插件目录 ' . $plugin;
                }
            }
        } else {
            if (in_array('placed', $data['steps'], true) && is_string($plugin) && is_dir($plugin)) {
                self::removeTree($plugin);
                $notes[] = '删除未完成的插件目录 ' . $plugin;
            }
            if (is_string($backup) && is_dir($backup) && is_string($plugin) && !is_dir($plugin)) {
                if (self::ensureParent($plugin) && rename($backup, $plugin)) {
                    $notes[] = '恢复旧版插件目录 ' . $plugin;
                } else {
                    $notes[] = '无法恢复旧版插件目录 ' . $plugin;
                }
            }
        }
        $this->finish($id);

        return $notes === [] ? 'journal ' . $id . ' 无文件遗留' : implode('；', $notes);
    }

    /**
     * 扫工作目录里未完结的 journal 逐个回滚，返回每条的恢复记录。
     * 解析不了的 journal 不删，留人工核查。
     *
     * @return list<string>
     */
    public function recoverPending(): array
    {
        $files = glob($this->workDir . '/*.json');
        if ($files === false) {
            return [];
        }
        $notes = [];
        foreach ($files as $file) {
            $id = basename($file, '.json');
            $note = $this->rollback($id);
            if ($note === null) {
                $notes[] = 'journal 无法解析，留待人工核查：' . $file;
                continue;
            }
            $notes[] = $note;
        }

        return $notes;
    }

    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private static function ensureParent(string $dir): bool
    {
        $parent = dirname($dir);

        return is_dir($parent) || (mkdir($parent, 0755, true) && is_dir($parent));
    }

    private function fileOf(string $id): string
    {
        return $this->workDir . '/' . $id . '.json';
    }

    /** @return ?array{kind: string, app: string, data_policy: string, steps: list<string>, staged: ?string, backup: ?string, plugin: ?string} */
    private function read(string $id): ?array
    {
        $file = $this->fileOf($id);
        if (!is_file($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !is_string($data['kind'] ?? null) || !is_array($data['steps'] ?? null)) {
            return null;
        }
        $steps = [];
        foreach ($data['steps'] as $step) {
            if (is_string($step)) {
                $steps[] = $step;
            }
        }

        return [
            'kind' => $data['kind'],
            'app' => is_string($data['app'] ?? null) ? $data['app'] : '',
            'data_policy' => is_string($data['data_policy'] ?? null) ? $data['data_policy'] : 'keep',
            'steps' => $steps,
            'staged' => is_string($data['staged'] ?? null) ? $data['staged'] : null,
            'backup' => is_string($data['backup'] ?? null) ? $data['backup'] : null,
            'plugin' => is_string($data['plugin'] ?? null) ? $data['plugin'] : null,
        ];
    }

    /** @param array{kind: string, app: string, data_policy: string, steps: list<string>, staged: ?string, backup: ?string, plugin: ?string} $data */
    private function write(string $id, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($this->fileOf($id), $json) === false) {
            throw new \RuntimeException('无法写 journal：' . $this->fileOf($id));
        }
    }
}
