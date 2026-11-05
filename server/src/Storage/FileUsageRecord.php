<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

final readonly class FileUsageRecord
{
    public function __construct(
        public FileUsageRef $ref,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
