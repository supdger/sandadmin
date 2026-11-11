<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** 文件类型分类，对应 sand_system_file.kind 的 CHECK 枚举。 */
enum FileKind: string
{
    case Image = 'image';
    case Document = 'document';
    case Sheet = 'sheet';
    case Archive = 'archive';
    case Other = 'other';

    private const SHEET_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
    ];

    private const SHEET_EXTENSIONS = ['xls', 'xlsx', 'csv'];

    private const ARCHIVE_MIMES = [
        'application/zip',
        'application/x-zip-compressed',
        'application/gzip',
        'application/x-gzip',
        'application/x-tar',
        'application/x-bzip2',
        'application/x-7z-compressed',
        'application/vnd.rar',
        'application/x-rar-compressed',
        'application/x-xz',
    ];

    private const ARCHIVE_EXTENSIONS = ['zip', 'tar', 'gz', 'tgz', 'bz2', 'xz', '7z', 'rar'];

    private const DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
        'application/rtf',
        'text/plain',
        'text/markdown',
        'text/html',
        'application/json',
        'application/xml',
        'text/xml',
    ];

    private const DOCUMENT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'md', 'rtf', 'json', 'xml', 'html', 'htm',
    ];

    /**
     * 由嗅探出的 MIME 为主、扩展名为辅推断分类；扩展名只在 MIME 无法区分时起作用。
     */
    public static function fromMediaType(string $mime, string $extension): self
    {
        $ext = strtolower($extension);
        if (str_starts_with($mime, 'image/')) {
            return self::Image;
        }
        if (in_array($mime, self::SHEET_MIMES, true) || in_array($ext, self::SHEET_EXTENSIONS, true)) {
            return self::Sheet;
        }
        if (in_array($mime, self::ARCHIVE_MIMES, true) || in_array($ext, self::ARCHIVE_EXTENSIONS, true)) {
            return self::Archive;
        }
        if (in_array($mime, self::DOCUMENT_MIMES, true) || in_array($ext, self::DOCUMENT_EXTENSIONS, true)) {
            return self::Document;
        }

        return self::Other;
    }
}
