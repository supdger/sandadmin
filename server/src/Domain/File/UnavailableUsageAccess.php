<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 默认实现：无任何引用方鉴权回调。空引用放行（由文件模块按 visibility 判定），
 * 存在引用一律拒绝——与「subject_type 无注册回调视为拒绝」口径一致。
 * 扩展位注册表合入后替换为 ExtensionRegistry 适配器。
 */
final class UnavailableUsageAccess implements UsageAccess
{
    public function authorize(int $userId, int $fileId, array $usages): bool
    {
        return $usages === [];
    }
}
