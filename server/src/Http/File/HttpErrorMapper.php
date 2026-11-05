<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

use Sandadmin\Storage\Exception\StorageException;

final class HttpErrorMapper
{
    public function map(\Throwable $error, string $requestId): HttpResult
    {
        if ($error instanceof StorageException) {
            return HttpResult::error(
                $this->status($error->errorCode),
                $error->errorCode,
                $error->getMessage(),
                $error->fields,
                $requestId,
            );
        }

        return HttpResult::error(500, 'internal_error', '处理请求时发生错误', [], $requestId);
    }

    private function status(string $code): int
    {
        return match ($code) {
            'upload_too_large' => 413,
            'mime_not_allowed', 'invalid_upload', 'invalid_usage' => 422,
            'file_not_found' => 404,
            'file_in_use' => 409,
            'storage_unavailable' => 503,
            default => 500,
        };
    }
}
