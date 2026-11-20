<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Runtime;

/**
 * 执行单元结束时上下文存储仍有残留键：说明有代码把请求/任务态
 * 写进了上下文却没有清理，属于跨请求串数据隐患，按 fail-closed 处理。
 */
final class ContextResidueException extends \RuntimeException
{
    /**
     * @param list<string> $keys 残留的键名
     */
    public function __construct(array $keys)
    {
        parent::__construct('请求结束时上下文存储存在残留键：' . implode(', ', $keys));
    }
}
