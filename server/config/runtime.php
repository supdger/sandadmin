<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

return [
    // 常驻进程内存水位（字节）：真实内存占用达到即触发 worker 平滑重启。
    // 显式 0 = 关闭水位回收；null（缺省）= 自动取「进程内存上限」的 80%，
    // 上限取 stream.profiles.*.memory_limit_bytes 的最大值，全部未配置时
    // 按 256MiB 兜底（见 Infra\Runtime\ProcessRecycle::deriveWatermark）。
    'memory_watermark_bytes' => null,
];
