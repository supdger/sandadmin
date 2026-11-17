<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 一次 MCP 工具调用的审计记录（U-3：身份来源 + 工具名 + 档位 + 幂等键 +
 * 参数摘要 + 结果）。参数只存规范化 JSON 的 sha256，不落原文，
 * 避免把参数里的密钥材料写进审计。
 */
final readonly class McpCall
{
    public function __construct(
        public string $requestId,
        public string $subject,
        public string $credential,
        public string $tool,
        public ToolTier $tier,
        public ?string $idempotencyKey,
        public string $paramsDigest,
        public string $outcome,
        public int $durationMs,
        public ?string $ip,
    ) {
        if ($this->requestId === '' || $this->tool === '' || $this->outcome === '') {
            throw new \InvalidArgumentException('审计记录的 request_id、tool、outcome 不能为空');
        }
        if ($this->durationMs < 0) {
            throw new \InvalidArgumentException('duration_ms 不能为负');
        }
    }

    /** @return array<string, mixed> */
    public function toLogContext(): array
    {
        return [
            'channel' => McpIdentity::CHANNEL,
            'request_id' => $this->requestId,
            'subject' => $this->subject,
            'credential' => $this->credential,
            'tool' => $this->tool,
            'tier' => $this->tier->value,
            'idempotency_key' => $this->idempotencyKey,
            'params_digest' => $this->paramsDigest,
            'outcome' => $this->outcome,
            'duration_ms' => $this->durationMs,
            'ip' => $this->ip,
        ];
    }

    /** @param array<string, mixed> $params 规范化（键序稳定）后取 sha256 */
    public static function digestOf(array $params): string
    {
        $json = json_encode(self::sortKeys($params), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json === false ? '' : $json);
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    private static function sortKeys(array $params): array
    {
        ksort($params);
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = self::sortKeys($value);
            }
        }

        return $params;
    }
}
