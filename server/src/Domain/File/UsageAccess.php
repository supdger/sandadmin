<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 「文件引用鉴权」端口：文件被 sand_system_file_usage 引用后，
 * 下载等访问按引用方（subject_type）规则判定。
 * 扩展位注册表（#59 ExtensionRegistry::authorizeFileUsage）合入后，
 * 由适配器委托给它；核心不识别具体插件。
 */
interface UsageAccess
{
    /**
     * 判定 $userId 能否访问被 $usages 引用的 $fileId。
     * 返回 true 放行，false 拒绝；空引用返回 true（是否引用由文件模块另行判定）。
     *
     * @param list<FileUsage> $usages
     */
    public function authorize(int $userId, int $fileId, array $usages): bool;
}
