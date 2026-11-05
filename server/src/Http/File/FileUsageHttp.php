<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

use Sandadmin\Application\File\FileUsage;
use Sandadmin\Storage\Exception\InvalidUsageException;
use Sandadmin\Storage\FileUsageRef;

final class FileUsageHttp
{
    public function __construct(
        private readonly FileUsage $usages,
        private readonly HttpErrorMapper $errors,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function register(string $fileId, array $payload, string $requestId): HttpResult
    {
        try {
            $ref = $this->ref($fileId, $payload);
            $this->usages->register($ref);

            return HttpResult::success(201, [
                'file_id' => $ref->fileId,
                'subject_type' => $ref->subjectType,
                'subject_id' => $ref->subjectId,
            ], $requestId);
        } catch (\Throwable $error) {
            return $this->errors->map($error, $requestId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function unregister(string $fileId, array $payload, string $requestId): HttpResult
    {
        try {
            $this->usages->unregister($this->ref($fileId, $payload));

            return HttpResult::success(200, ['removed' => true], $requestId);
        } catch (\Throwable $error) {
            return $this->errors->map($error, $requestId);
        }
    }

    public function delete(string $fileId, string $requestId): HttpResult
    {
        try {
            $this->usages->deleteFile($fileId);

            return HttpResult::success(200, ['deleted' => true], $requestId);
        } catch (\Throwable $error) {
            return $this->errors->map($error, $requestId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ref(string $fileId, array $payload): FileUsageRef
    {
        $subjectType = $payload['subject_type'] ?? null;
        $subjectId = $payload['subject_id'] ?? null;
        if (!is_string($subjectType) || !is_string($subjectId)) {
            throw new InvalidUsageException([
                'subject_type' => '引用主体类型无效',
                'subject_id' => '引用主体标识无效',
            ]);
        }

        return new FileUsageRef($fileId, $subjectType, $subjectId);
    }
}
