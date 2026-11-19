<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

/**
 * 人工登记的恢复点：运维把快照号 / PITR 位点 / 测试标识经 --recovery-point 传入。
 * 空引用视为拿不到恢复点，抛异常拒绝迁移。
 */
final class ManualRecoveryPoint implements RecoveryPointProvider
{
    public function __construct(private readonly string $reference)
    {
    }

    public function describe(): string
    {
        $reference = trim($this->reference);
        if ($reference === '') {
            throw new \RuntimeException('恢复点引用为空：拿不到可用恢复点，拒绝执行迁移');
        }

        return $reference;
    }
}
