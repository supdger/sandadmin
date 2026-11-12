<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 「文件引用鉴权」扩展点：文件被 sand_system_file_usage 引用后，
 * 下载等访问由引用方（subject_type）自己的规则判定。
 * 插件实现本接口并以 subject_type 向 ExtensionRegistry 注册；核心不识别具体插件。
 */
interface FileUsageAuthorizer
{
    /**
     * 判定 $userId 能否访问被 (subject_type, subject_id) 引用的 $fileId。
     * 返回 true 放行，false 拒绝；抛错由核心按失败处理。
     */
    public function authorize(int $userId, int $fileId, string $subjectType, string $subjectId): bool;
}
