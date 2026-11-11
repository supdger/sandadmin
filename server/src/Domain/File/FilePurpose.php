<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 文件用途分级，对应 sand_system_file.purpose（A29 定稿枚举）。
 * attachment 走附件管理与 /files/{file_id}/download；export 只走
 * /tasks/{task_id}/download；package/plugin 不对外下载。
 */
enum FilePurpose: string
{
    case Attachment = 'attachment';
    case Export = 'export';
    case Package = 'package';
    case Plugin = 'plugin';
}
