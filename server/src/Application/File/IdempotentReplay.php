<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

/**
 * 幂等重放信号：携带首次响应 data 快照，由 FileHttp 转为成功信封返回。
 * 不是错误——同键同请求应拿到与首次一致的结果。
 */
final class IdempotentReplay extends \RuntimeException
{
    public function __construct(public readonly mixed $response)
    {
        parent::__construct('幂等重放');
    }
}
