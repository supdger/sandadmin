<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * sand_system_file / sand_system_file_usage 的持久化端口。
 * 只读视图含 usage_state/usage_label/uploader_name 推出字段；
 * 软删除保留墓碑行，被引用信息不丢。
 */
interface FileRepository
{
    public function insert(FileDraft $draft): int;

    /** 未软删的文件（任意 purpose）；不存在或已删返回 null。 */
    public function findById(int $id): ?FileRecord;

    public function search(FileQuery $query): FilePage;

    /** 置 delete_time；只影响未删除行，返回是否真的更新了一行。 */
    public function softDelete(int $id): bool;

    /** @return list<FileUsage> */
    public function usagesFor(int $fileId): array;

    /** 幂等登记：同 (file_id, subject_type, subject_id) 重复写入直接忽略。 */
    public function addUsage(FileUsage $usage): void;

    public function removeUsage(FileUsage $usage): void;

    /**
     * 在同一事务内执行 $work；$work 抛错整体回滚。
     * 已在事务中时直接执行（并入外层事务）。
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed;
}
