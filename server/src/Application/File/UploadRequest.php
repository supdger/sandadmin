<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

final readonly class UploadRequest
{
    /**
     * @param resource $stream
     */
    public function __construct(
        public mixed $stream,
        public ?int $contentLength,
        public string $originalName,
    ) {
    }
}
