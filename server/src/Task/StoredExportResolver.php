<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Task;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\File\LocalFileStorage;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Module\Org\UserExportFile;

/** 按任务结果里的 file_id 找到导出文件。只接受 purpose=export。 */
final class StoredExportResolver implements TaskArtifactResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function resolve(TaskRun $task): ?TaskArtifact
    {
        $result = $task->result;
        if ($result === null) {
            return null;
        }
        $rawId = $result['file_id'] ?? null;
        if (is_int($rawId)) {
            $fileId = $rawId;
        } elseif (is_string($rawId) && preg_match('/^\d+$/', $rawId) === 1) {
            $fileId = (int) $rawId;
        } else {
            return null;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT original_name, media_type, size_bytes, storage_key'
            . ' FROM sand_system_file WHERE id = ? AND delete_time IS NULL AND purpose = \'export\'',
            [$fileId],
        );
        if ($row === false) {
            return null;
        }
        $key = $row['storage_key'] ?? null;
        $name = $row['original_name'] ?? null;
        $media = $row['media_type'] ?? null;
        if (!is_string($key) || !is_string($name) || !is_string($media)) {
            return null;
        }
        $storage = LocalFileStorage::open(UserExportFile::root());
        if (!$storage->exists($key)) {
            return null;
        }

        return new TaskArtifact($fileId, $name, $media, OrgSql::int($row['size_bytes'] ?? 0), $storage->localPath($key));
    }
}
