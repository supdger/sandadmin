<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 上传时登记用途的入参（尚无 file_id）。
 * HTTP 上传的 usage 参数形如「subject_type:subject_id」，由 FileHttp 解析后构造。
 */
final readonly class UsageDraft
{
    public string $label;

    public function __construct(
        public string $subjectType,
        public string $subjectId,
        string $label,
    ) {
        FileUsage::assertSubject($subjectType, 'subject_type');
        FileUsage::assertSubject($subjectId, 'subject_id');
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 128) {
            throw FileException::invalid(['label' => ['用途说明需为 1-128 字']]);
        }
        $this->label = $label;
    }

    /**
     * 解析「subject_type:subject_id」形态；subject_id 允许含冒号，按第一个冒号切分。
     */
    public static function parse(string $raw): self
    {
        $raw = trim($raw);
        $pos = strpos($raw, ':');
        if ($pos === false || $pos === 0 || $pos === strlen($raw) - 1) {
            throw FileException::invalid(['usage' => ['用途标识格式应为 类型:编号']]);
        }
        try {
            // label 用完整用途标识原文：subject_type 本身不是给人看的说明
            return new self(substr($raw, 0, $pos), substr($raw, $pos + 1), $raw);
        } catch (FileException) {
            throw FileException::invalid(['usage' => ['用途标识格式应为 类型:编号']]);
        }
    }
}
