<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** GET /files 的具名过滤与分页参数（api-conventions §3）。 */
final readonly class FileQuery
{
    public function __construct(
        public ?string $keyword,
        public ?FileKind $kind,
        public ?FileUsageState $usageState,
        public ?int $uploaderId,
        public ?\DateTimeImmutable $createTimeFrom,
        public ?\DateTimeImmutable $createTimeTo,
        public int $page,
        public int $pageSize,
    ) {
    }
}
