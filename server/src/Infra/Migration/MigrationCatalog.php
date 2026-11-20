<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

/**
 * 迁移目录清单：命名空间 → 目录的扫描结果。
 * 同一命名空间内版本不重复；目录里每个 .sql 必须是迁移文件或配对的 .down.sql。
 */
final class MigrationCatalog
{
    /** @var list<MigrationFile> 按命名空间、版本排序 */
    private array $files = [];

    /**
     * @param array<string, string> $sources 命名空间 => 迁移目录
     */
    public static function load(array $sources): self
    {
        $catalog = new self();
        ksort($sources, SORT_STRING);
        foreach ($sources as $namespace => $dir) {
            if (preg_match('/^[a-z0-9][a-z0-9\-]*$/', $namespace) !== 1) {
                throw new \InvalidArgumentException("迁移命名空间非法：{$namespace}");
            }
            if (!is_dir($dir)) {
                throw new \InvalidArgumentException("迁移目录不存在：{$dir}");
            }
            $upNames = [];
            $downNames = [];
            foreach (glob($dir . '/*.sql') ?: [] as $path) {
                $name = basename($path);
                if (str_ends_with($name, '.down.sql')) {
                    $downNames[$name] = true;
                } elseif (preg_match('/^\d{4}_[a-z0-9_]+\.sql$/', $name) === 1) {
                    $upNames[$name] = true;
                } else {
                    throw new \InvalidArgumentException("迁移目录里出现不合规范的文件名：{$name}");
                }
            }
            ksort($upNames, SORT_STRING);
            $seen = [];
            foreach (array_keys($upNames) as $name) {
                $file = MigrationFile::fromPath($namespace, $dir, $name);
                if (isset($seen[$file->version])) {
                    throw new \InvalidArgumentException(
                        "命名空间 {$namespace} 版本 {$file->version} 重复：{$name}"
                    );
                }
                $seen[$file->version] = true;
                if ($file->hasDown()) {
                    unset($downNames[$file->downName]);
                }
                $catalog->files[] = $file;
            }
            if ($downNames !== []) {
                throw new \InvalidArgumentException(
                    '存在没有对应升级文件的回退文件：' . implode(', ', array_keys($downNames))
                );
            }
        }

        return $catalog;
    }

    /** @return list<MigrationFile> */
    public function all(): array
    {
        return $this->files;
    }

    /** @return list<MigrationFile> */
    public function forNamespace(string $namespace): array
    {
        return array_values(array_filter(
            $this->files,
            static fn (MigrationFile $file): bool => $file->namespace === $namespace,
        ));
    }
}
