<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileActor;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FilePage;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileQuery;
use Sandadmin\Domain\File\FileRecord;
use Sandadmin\Domain\File\FileRepository;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Domain\File\UsageAccess;
use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;
use Sandadmin\Infra\File\LocalFileStorage;

/**
 * 附件管理与下载的领域服务。附件管理面（列表/详情/删除/下载）只作用于
 * purpose=attachment；其他用途一律按 FILE_NOT_FOUND 处理（A29 下载分流）。
 */
final class FileService
{
    private const ROUTE_DELETE = 'DELETE /api/admin/v1/files/';

    public function __construct(
        private readonly FileRepository $files,
        private readonly LocalFileStorage $storage,
        private readonly CleanupQueue $cleanup,
        private readonly ?IdempotencyStore $idempotency = null,
    ) {
    }

    public function search(FileQuery $query): FilePage
    {
        return $this->files->search($query);
    }

    /** 附件管理面取文件；非 attachment 或不存在/已删均报 FILE_NOT_FOUND。 */
    public function findForManagement(int $id): FileRecord
    {
        $record = $this->files->findById($id);
        if ($record === null || $record->purpose !== FilePurpose::Attachment) {
            throw FileException::notFound();
        }

        return $record;
    }

    /**
     * 软删元数据（保留墓碑与引用记录），再尽力删除磁盘对象。
     * 已被引用的文件也允许删除——前端删除前自行提醒「可能有地方还在用」。
     * 携带幂等键时首次结果落 sand_system_idempotency_key，重放返回同一结果。
     */
    public function remove(int $id, ?int $actorId, ?string $idempotencyKey = null): void
    {
        $idemUser = $actorId !== null && $actorId > 0 ? $actorId : null;
        $requestHash = $idemUser !== null && $idempotencyKey !== null
            ? hash('sha256', self::ROUTE_DELETE . $id)
            : null;
        $this->assertReplayable($idemUser, $idempotencyKey, $requestHash);
        $record = $this->findForManagement($id);
        try {
            $this->files->transaction(function () use ($id, $idemUser, $idempotencyKey, $requestHash): void {
                $this->files->softDelete($id);
                if ($idemUser !== null && $idempotencyKey !== null && $requestHash !== null) {
                    // 删除无业务返回体，data 存空对象 {}
                    $this->idempotency?->record($idemUser, $idempotencyKey, $requestHash, new \stdClass());
                }
            });
        } catch (IdempotencyConflict $conflict) {
            $this->replayOrFail($idemUser, $idempotencyKey, $requestHash, $conflict);
        }
        try {
            $this->storage->delete($record->storageKey);
        } catch (\Throwable $error) {
            // 墓碑已生效，孤儿对象由后续清理兜底；不向用户暴露存储细节
            error_log('文件对象删除失败 file_id=' . $id . ' ' . $error::class . ': ' . $error->getMessage());
        }
    }

    /**
     * purpose=attachment 的下载鉴权（api-conventions §10）：
     * 有 file_usage 引用时按引用方规则经 UsageAccess 判定；
     * 无引用时 public 任何登录用户可下，private 仅上传人或 system:file:index。
     */
    public function assertDownloadable(FileRecord $file, FileActor $actor, UsageAccess $access): void
    {
        $usages = $this->files->usagesFor($file->id);
        if ($usages !== []) {
            if (!$access->authorize($actor->userId, $file->id, $usages)) {
                throw FileException::denied();
            }

            return;
        }
        if ($file->visibility === FileVisibility::Public) {
            return;
        }
        if ($file->uploaderId === $actor->userId || $actor->can('system:file:index')) {
            return;
        }

        throw FileException::denied();
    }

    /** 下载响应用的本机路径；对象缺失按 FILE_NOT_FOUND 处理。 */
    public function downloadPath(FileRecord $file): string
    {
        $path = $this->storage->localPath($file->storageKey);
        if (!is_file($path) || !is_readable($path)) {
            throw FileException::notFound();
        }

        return $path;
    }

    /**
     * 一键清理受理：只清 usage_state=unused 且上传超 30 天的文件，
     * 由 task_run 后台任务执行（执行体随 #57 task_run 合入）。
     *
     * @return array{task_id: string, state: string}
     */
    public function cleanup(int $createdBy, ?string $idempotencyKey): array
    {
        return $this->cleanup->enqueue($createdBy, $idempotencyKey);
    }

    private function assertReplayable(?int $userId, ?string $key, ?string $requestHash): void
    {
        if ($userId === null || $key === null || $requestHash === null || $this->idempotency === null) {
            return;
        }
        $existing = $this->idempotency->findActive($userId, $key);
        if ($existing === null) {
            return;
        }
        if ($existing['request_hash'] !== $requestHash) {
            throw FileException::idempotencyReused();
        }
        if ($existing['response'] !== null) {
            throw new IdempotentReplay($existing['response']);
        }
    }

    private function replayOrFail(?int $userId, ?string $key, ?string $requestHash, \Throwable $cause): never
    {
        if ($userId !== null && $key !== null && $requestHash !== null && $this->idempotency !== null) {
            $existing = $this->idempotency->findActive($userId, $key);
            if ($existing !== null) {
                if ($existing['request_hash'] !== $requestHash) {
                    throw FileException::idempotencyReused();
                }
                if ($existing['response'] !== null) {
                    throw new IdempotentReplay($existing['response']);
                }
            }
        }

        throw FileException::internal('文件删除失败', $cause);
    }
}
