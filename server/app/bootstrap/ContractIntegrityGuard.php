<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\bootstrap;

use RuntimeException;
use Sandadmin\Contract\GeneratedIntegrity;
use Webman\Bootstrap;

/**
 * 契约产物启动守卫：worker 起之前校验生成物与契约源一致，
 * 不一致直接拒启（fail-closed）。排在 ProductionDebugGuard 之后。
 */
final class ContractIntegrityGuard implements Bootstrap
{
    public static function start(mixed $worker): void
    {
        try {
            GeneratedIntegrity::assert(base_path());
        } catch (RuntimeException $error) {
            // bootstrap 异常会让进程退出；先把原因打到 stderr 再抛
            fwrite(STDERR, '[contract] ' . $error->getMessage() . PHP_EOL);
            throw $error;
        }
    }
}
