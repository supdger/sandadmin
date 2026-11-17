<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

/** 子进程跑完的收口结果；output 已截尾到有限大小。 */
final readonly class SubprocessOutcome
{
    public function __construct(
        public ?int $exitCode,
        public string $output,
        public bool $timedOut,
        public int $durationMs,
    ) {
    }
}
