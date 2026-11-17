<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * 给第三方 SDK 用的收口日志：只放行 warning 及以上，且丢掉上下文。
 * 第三方 SDK 的 debug/info 会把请求参数原文写进日志，参数里可能有敏感内容。
 */
final class SdkQuietLogger extends AbstractLogger
{
    private const PASS_LEVELS = [
        LogLevel::WARNING => true,
        LogLevel::ERROR => true,
        LogLevel::CRITICAL => true,
        LogLevel::ALERT => true,
        LogLevel::EMERGENCY => true,
    ];

    public function __construct(private readonly LoggerInterface $inner)
    {
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * $level 收窄不了：PSR-3 LoggerInterface::log 签名就是无类型参数。
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        if (!is_string($level) || !isset(self::PASS_LEVELS[$level])) {
            return;
        }
        $this->inner->log($level, (string) $message);
    }
}
