<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileDraft;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileRecord;
use Sandadmin\Domain\File\FileRepository;
use Sandadmin\Domain\File\FileUsage;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Domain\File\UsageDraft;
use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;
use Sandadmin\Infra\File\FinfoMimeSniffer;
use Sandadmin\Infra\File\LocalFileStorage;

/**
 * POST /files 的上传主流程：限长复制 → 内容嗅探 → 扩展名+MIME 双白名单 →
 * 幂等预检 → Flysystem 落盘 → sand_system_file 元数据、用途登记与幂等记录
 * 同一事务入库。幂等重放经 IdempotentReplay 抛给 FileHttp 转成功信封。
 */
final class UploadService
{
    private const ROUTE = 'POST /api/admin/v1/files';

    public function __construct(
        private readonly LocalFileStorage $storage,
        private readonly FileRepository $files,
        private readonly StorageConfig $config,
        private readonly FinfoMimeSniffer $sniffer,
        private readonly BoundedCopier $copier,
        private readonly ?IdempotencyStore $idempotency = null,
    ) {
    }

    /**
     * @param resource $stream
     */
    public function upload(
        mixed $stream,
        ?int $contentLength,
        string $originalName,
        FileVisibility $visibility,
        ?UsageDraft $usage,
        ?int $uploaderId,
        ?string $idempotencyKey = null,
    ): FileRecord {
        if (!is_resource($stream)) {
            throw FileException::invalid(['file' => ['上传内容不是可读流']]);
        }
        if ($contentLength !== null && $contentLength < 1) {
            throw FileException::invalid(['file' => ['不能上传空文件']]);
        }
        if ($contentLength !== null && $contentLength > $this->config->maxBytes) {
            throw FileException::tooLarge();
        }
        $pending = $this->copier->copy($stream, $this->config->maxBytes);
        try {
            $mime = $this->sniffer->sniff($pending->stream());
            if (!in_array($mime, $this->config->allowedMimeTypes, true)) {
                throw FileException::typeNotAllowed();
            }
            $name = OriginalName::sanitize($originalName);
            $extension = OriginalName::extension($name);
            if ($extension !== '' && !in_array($extension, $this->config->allowedExtensions, true)) {
                throw FileException::typeNotAllowed();
            }
            $kind = FileKind::fromMediaType($mime, $extension);
            // 上传人恒为已登录用户；无身份时幂等键无处可记，直接跳过幂等
            $idemUser = $uploaderId !== null && $uploaderId > 0 ? $uploaderId : null;
            $requestHash = $idemUser !== null && $idempotencyKey !== null
                ? $this->requestHash($pending->sha256, $name, $visibility, $usage)
                : null;
            $this->assertReplayable($idemUser, $idempotencyKey, $requestHash);
            $storageKey = $this->storage->writeStream($pending->stream());
            $draft = new FileDraft(
                originalName: $name,
                kind: $kind,
                mediaType: $mime,
                extension: $extension,
                sizeBytes: $pending->size,
                sha256: $pending->sha256,
                storageKey: $storageKey,
                visibility: $visibility,
                purpose: FilePurpose::Attachment,
                // 登记了用途标识才算 usage_tracked；否则无引用时为 unknown
                usageTracked: $usage !== null,
                uploaderId: $uploaderId,
            );
            try {
                return $this->files->transaction(function () use (
                    $draft, $usage, $idemUser, $idempotencyKey, $requestHash
                ): FileRecord {
                    $id = $this->files->insert($draft);
                    if ($usage !== null) {
                        $this->files->addUsage(new FileUsage(
                            $id,
                            $usage->subjectType,
                            $usage->subjectId,
                            $usage->label,
                        ));
                    }
                    $record = $this->files->findById($id);
                    if ($record === null) {
                        throw FileException::internal('文件元数据写入失败');
                    }
                    if ($idemUser !== null && $idempotencyKey !== null && $requestHash !== null) {
                        $this->idempotency?->record($idemUser, $idempotencyKey, $requestHash, $record->toApi());
                    }

                    return $record;
                });
            } catch (IdempotencyConflict $conflict) {
                $this->deleteQuietly($storageKey);
                $this->replayOrFail($idemUser, $idempotencyKey, $requestHash, $conflict);
            } catch (\Throwable $error) {
                // 元数据未落库时回收已写入的对象，避免孤儿文件
                $this->deleteQuietly($storageKey);
                throw $error;
            }
        } finally {
            $pending->close();
        }
    }

    /**
     * request_hash 覆盖方法+路径+文件内容哈希+生效参数；同键同内容重放、
     * 同键不同内容判 IDEMPOTENCY_KEY_REUSED（api-conventions §8）。
     */
    private function requestHash(string $sha256, string $name, FileVisibility $visibility, ?UsageDraft $usage): string
    {
        $usagePart = $usage === null ? '' : $usage->subjectType . ':' . $usage->subjectId;

        return hash('sha256', self::ROUTE . "\n{$sha256}\n{$name}\n{$visibility->value}\n{$usagePart}");
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
        // response 为空属异常残留：放行，record 冲突时走 replayOrFail 兜底
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

        throw FileException::internal('文件上传失败', $cause);
    }

    private function deleteQuietly(string $storageKey): void
    {
        try {
            $this->storage->delete($storageKey);
        } catch (\Throwable) {
            // 回收失败不掩盖原始错误；孤儿对象由清理任务兜底
        }
    }
}
