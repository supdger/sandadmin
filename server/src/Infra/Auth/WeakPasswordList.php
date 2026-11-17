<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Auth;

/**
 * 常用弱密码命中表（C-18）。进程启动时从 vendored 文本文件载入一次，
 * 条目小写归一进哈希集合；比对只读取、不改动传入的候选密码。
 */
final class WeakPasswordList
{
    /** @param array<string, true> $entries */
    private function __construct(
        private readonly array $entries,
    ) {
    }

    /** @throws WeakPasswordException 文件缺失、不可读或没有有效条目 */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new WeakPasswordException('弱密码词表文件不存在或不可读：' . $path);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new WeakPasswordException('弱密码词表文件读取失败：' . $path);
        }
        $entries = [];
        foreach ($lines as $line) {
            $entry = mb_strtolower(trim($line));
            if ($entry !== '') {
                $entries[$entry] = true;
            }
        }
        if ($entries === []) {
            throw new WeakPasswordException('弱密码词表文件没有有效条目：' . $path);
        }

        return new self($entries);
    }

    /**
     * 候选密码是否命中弱表。先去掉首尾空白再小写比对；
     * 只在服务端明文期调用，命中与否都不记日志、不外发、不回显条目。
     */
    public function isWeak(string $password): bool
    {
        $key = mb_strtolower(trim($password));

        return $key !== '' && isset($this->entries[$key]);
    }

    public function size(): int
    {
        return count($this->entries);
    }
}
