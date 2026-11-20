<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

/**
 * 恢复点检查（M-13）：任何迁移批次执行前，执行器先经本接口拿到可用恢复点。
 * 拿不到可用恢复点必须抛异常，执行器拒绝迁移、不静默跳过。
 * 小库/单机的现场 pg_dump/mysqldump 实现、以及大库的快照/PITR 登记实现，
 * 都是本接口的实现类；本批只交付人工登记实现 ManualRecoveryPoint。
 */
interface RecoveryPointProvider
{
    /**
     * @return string 恢复点位点描述（快照号、PITR 位点、导出文件路径等），写进执行日志
     * @throws \RuntimeException 拿不到可用恢复点时
     */
    public function describe(): string;
}
