<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

/**
 * 一份迁移文件的元数据：命名空间、4 位序号版本、文件 sha256、
 * 头注里的 release_version，以及同名 <basename>.down.sql 回退文件配对。
 */
final class MigrationFile
{
    private ?string $downChecksum = null;

    private function __construct(
        public readonly string $namespace,
        public readonly string $version,
        public readonly string $name,
        public readonly string $path,
        public readonly string $checksum,
        public readonly ?string $releaseVersion,
        public readonly ?string $downPath,
        public readonly ?string $downName,
    ) {
    }

    /**
     * @param string $name 文件名，须为 <4位序号>_<动词>_<对象>.sql（不含 .down.sql）
     */
    public static function fromPath(string $namespace, string $dir, string $name): self
    {
        if (preg_match('/^(\d{4})_[a-z0-9_]+\.sql$/', $name, $match) !== 1
            || str_ends_with($name, '.down.sql')) {
            throw new \InvalidArgumentException("迁移文件名不合规范：{$name}");
        }
        $path = $dir . '/' . $name;
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \InvalidArgumentException("迁移文件不可读：{$path}");
        }
        $releaseVersion = null;
        if (preg_match('/迁移版本（release_version）：\s*([0-9][0-9A-Za-z.\-]*)/', $content, $ver) === 1) {
            $releaseVersion = $ver[1];
        }
        $downName = substr($name, 0, -4) . '.down.sql';
        $downPath = $dir . '/' . $downName;
        if (!is_file($downPath)) {
            $downName = null;
            $downPath = null;
        }

        return new self(
            $namespace,
            $match[1],
            $name,
            $path,
            hash('sha256', $content),
            $releaseVersion,
            $downPath,
            $downName,
        );
    }

    public function hasDown(): bool
    {
        return $this->downPath !== null;
    }

    /**
     * @throws \InvalidArgumentException 文件不可读时
     */
    public function sql(): string
    {
        $content = file_get_contents($this->path);
        if ($content === false) {
            throw new \InvalidArgumentException("迁移文件不可读：{$this->path}");
        }

        return $content;
    }

    /**
     * 回退文件的 sha256；没有回退文件时不许调用（先查 hasDown）。
     */
    public function downChecksum(): string
    {
        if ($this->downChecksum !== null) {
            return $this->downChecksum;
        }
        if ($this->downPath === null) {
            throw new \LogicException("迁移 {$this->name} 没有回退文件");
        }
        $content = file_get_contents($this->downPath);
        if ($content === false) {
            throw new \InvalidArgumentException("回退文件不可读：{$this->downPath}");
        }

        return $this->downChecksum = hash('sha256', $content);
    }
}
