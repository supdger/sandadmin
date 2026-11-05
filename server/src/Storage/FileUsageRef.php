<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

use Sandadmin\Storage\Exception\InvalidUsageException;

final readonly class FileUsageRef
{
    private const SUBJECT_PATTERN = '/\A[A-Za-z0-9_.:-]{1,64}\z/';

    public function __construct(
        public string $fileId,
        public string $subjectType,
        public string $subjectId,
    ) {
        FileId::assert($fileId);
        if (preg_match(self::SUBJECT_PATTERN, $subjectType) !== 1) {
            throw new InvalidUsageException(['subject_type' => '引用主体类型无效']);
        }
        if (preg_match(self::SUBJECT_PATTERN, $subjectId) !== 1) {
            throw new InvalidUsageException(['subject_id' => '引用主体标识无效']);
        }
    }
}
