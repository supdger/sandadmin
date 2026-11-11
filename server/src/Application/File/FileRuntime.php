<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileActorProvider;
use Sandadmin\Domain\File\UnavailableFileActorProvider;
use Sandadmin\Domain\File\UnavailableUsageAccess;
use Sandadmin\Domain\File\UsageAccess;
use Sandadmin\Infra\File\FinfoMimeSniffer;
use Sandadmin\Infra\File\FileConnection;
use Sandadmin\Infra\File\LocalFileStorage;
use Sandadmin\Infra\File\PdoCleanupQueue;
use Sandadmin\Infra\File\PdoFileRepository;
use Sandadmin\Infra\Idempotency\PdoIdempotencyStore;

/**
 * 文件子系统的运行时装配：配置、存储、仓储、服务与各接缝实现。
 * 数据库连接由 FileConnection 从 config('database.connections.pgsql') 构造；
 * 统一连接入口（§3.16.5）落地后只改 FileConnection 一处。
 * 鉴权身份与引用方鉴权在底座（#56/#59）合入前使用默认拒绝实现。
 */
final readonly class FileRuntime
{
    public function __construct(
        public UploadService $uploads,
        public FileService $files,
        public UsageService $usages,
        public UsageAccess $usageAccess,
        public FileActorProvider $actors,
    ) {
    }

    public static function fromWebman(): ?self
    {
        if (!function_exists('config') || !function_exists('runtime_path')) {
            return null;
        }
        $fileConfig = config('file');
        $database = config('database.connections.pgsql');
        if (!is_array($fileConfig) || !is_array($database)) {
            return null;
        }
        try {
            $config = StorageConfig::fromArray(self::stringKeyed($fileConfig));
            $pdo = FileConnection::pdo($database);
            $storage = LocalFileStorage::open($config->root);
            $repository = new PdoFileRepository($pdo);
            $cleanup = new PdoCleanupQueue($pdo);
            $idempotency = new PdoIdempotencyStore($pdo);
            $service = new FileService($repository, $storage, $cleanup, $idempotency);

            return new self(
                uploads: new UploadService(
                    $storage,
                    $repository,
                    $config,
                    new FinfoMimeSniffer(),
                    new BoundedCopier(),
                    $idempotency,
                ),
                files: $service,
                usages: new UsageService($repository),
                usageAccess: new UnavailableUsageAccess(),
                actors: new UnavailableFileActorProvider(),
            );
        } catch (\Throwable $exception) {
            error_log('file 运行时初始化失败 ' . $exception::class . ': ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $config): array
    {
        $result = [];
        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
