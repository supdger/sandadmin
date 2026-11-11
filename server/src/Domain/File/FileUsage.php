<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * sand_system_file_usage 一行：file_id + subject_type + subject_id 复合主键，
 * subject 为多态引用不建外键；label 是给人看的用途说明。
 * 写入时 createTime 为空（由库默认 CURRENT_TIMESTAMP 填充），读出时带值。
 */
final readonly class FileUsage
{
    private const SUBJECT_PATTERN = '/\A[A-Za-z0-9_.:-]{1,64}\z/';

    public string $label;

    public function __construct(
        public int $fileId,
        public string $subjectType,
        public string $subjectId,
        string $label,
        public ?\DateTimeImmutable $createTime = null,
    ) {
        if ($fileId < 1) {
            throw new \InvalidArgumentException('file_id 必须为正整数');
        }
        self::assertSubject($subjectType, 'subject_type');
        self::assertSubject($subjectId, 'subject_id');
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 128) {
            throw FileException::invalid(['label' => ['用途说明需为 1-128 字']]);
        }
        $this->label = $label;
    }

    public static function assertSubject(string $value, string $field): void
    {
        if (preg_match(self::SUBJECT_PATTERN, $value) !== 1) {
            throw FileException::invalid([$field => ['引用主体标识无效']]);
        }
    }
}
