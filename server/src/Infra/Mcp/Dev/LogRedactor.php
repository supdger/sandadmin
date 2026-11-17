<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

/**
 * 日志/命令输出脱敏：把键值、Bearer、长十六进制、手机号、邮箱等
 * 可能夹带密钥或个人信息的部分替换成占位符。宁多勿少。
 */
final class LogRedactor
{
    private const PLACEHOLDER = '[REDACTED]';

    /**
     * 顺序敏感：先挡赋值形态，再挡裸值形态。
     *
     * @var list<array{0: string, 1: string}>
     */
    private const RULES = [
        // password=… / "token": "…" / authorization: Bearer … 之类键值对
        ['/(?i)(password|passwd|token|secret|api[_-]?key|authorization|cookie|credential|private[_-]?key|access[_-]?key)("?\s*[:=]\s*"?)([^\s",}\]]+)/', '$1$2' . self::PLACEHOLDER],
        // 裸露的 Bearer / Basic 头
        ['/(?i)\b(bearer|basic)\s+[A-Za-z0-9._~+\/=-]{8,}/', '$1 ' . self::PLACEHOLDER],
        // 32 位以上连续十六进制（会话、密钥摘要）
        ['/\b[0-9a-f]{32,}\b/i', self::PLACEHOLDER],
        // 国内手机号
        ['/\b1[3-9]\d{9}\b/', self::PLACEHOLDER],
        // 邮箱
        ['/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', self::PLACEHOLDER],
    ];

    public static function redact(string $text): string
    {
        foreach (self::RULES as [$pattern, $replacement]) {
            $replaced = preg_replace($pattern, $replacement, $text);
            if (is_string($replaced)) {
                $text = $replaced;
            }
        }

        return $text;
    }
}
