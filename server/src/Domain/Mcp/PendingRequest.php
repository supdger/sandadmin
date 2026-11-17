<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 高危写工具调用的返回形状（占位实现，U-3/M-02）。
 * 参数只带规范化摘要（sha256），批准页要展示的全参数由后续版本
 * 落 sand_system_lifecycle_request 同形记录，本类不落库。
 */
final readonly class PendingRequest implements \JsonSerializable
{
    public function __construct(
        public string $tool,
        public string $paramsDigest,
        public PendingState $state,
    ) {
        if ($this->tool === '' || $this->paramsDigest === '') {
            throw new \InvalidArgumentException('待确认请求的工具与参数摘要不能为空');
        }
    }

    /** @return array{tool: string, params_digest: string, state: string} */
    public function jsonSerialize(): array
    {
        return [
            'tool' => $this->tool,
            'params_digest' => $this->paramsDigest,
            'state' => $this->state->value,
        ];
    }
}
