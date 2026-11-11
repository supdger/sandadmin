<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\File\LocalFileStorage;

/** 把用户导出表写成 purpose=export 的文件，供任务下载。不记录文件内容。 */
final class UserExportFile
{
    public static function root(): string
    {
        if (function_exists('config')) {
            $configured = config('file.root');
            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }
        $fromEnv = getenv('SAND_FILE_ROOT');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sandadmin-files';
    }

    public static function write(Connection $connection, string $csv, ?int $uploaderId): int
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('无法准备导出文件');
        }
        $written = fwrite($stream, $csv);
        if ($written === false) {
            fclose($stream);
            throw new \RuntimeException('无法准备导出文件');
        }
        $storage = LocalFileStorage::open(self::root());
        $key = $storage->writeStream($stream);
        fclose($stream);
        $sha = hash('sha256', $csv);

        return OrgSql::int($connection->fetchOne(
            'INSERT INTO sand_system_file ('
            . ' original_name, kind, media_type, extension, size_bytes, sha256,'
            . ' storage_key, visibility, purpose, usage_tracked, uploader_id'
            . ' ) VALUES ('
            . ' :original_name, :kind, :media_type, :extension, :size_bytes, :sha256,'
            . ' :storage_key, :visibility, :purpose, false, :uploader_id'
            . ' ) RETURNING id',
            [
                'original_name' => '用户列表.csv',
                'kind' => 'sheet',
                'media_type' => 'text/csv',
                'extension' => 'csv',
                'size_bytes' => strlen($csv),
                'sha256' => $sha,
                'storage_key' => $key,
                'visibility' => 'private',
                'purpose' => 'export',
                'uploader_id' => $uploaderId,
            ],
        ));
    }
}
