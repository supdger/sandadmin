<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

use Sandadmin\Application\File\UploadRequest;
use Sandadmin\Application\File\UploadService;

final class UploadHttp
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly HttpErrorMapper $errors,
    ) {
    }

    public function upload(UploadRequest $request, string $requestId): HttpResult
    {
        try {
            $stored = $this->uploads->upload($request->stream, $request->contentLength, $request->originalName);

            return HttpResult::success(201, $stored->jsonSerialize(), $requestId);
        } catch (\Throwable $error) {
            return $this->errors->map($error, $requestId);
        }
    }
}
