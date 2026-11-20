<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 一条流式事件。id 即断线重连游标（SSE 的 Last-Event-ID），
 * 由生产方赋单调值；name 缺省时客户端按 message 事件处理。
 */
final readonly class StreamEvent
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public string $data,
    ) {
    }

    /** 通道传输载荷（JSON）；Redis pub/sub 与内存通道共用同一形状。 */
    public function toPayload(): string
    {
        return json_encode(
            ['id' => $this->id, 'name' => $this->name, 'data' => $this->data],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /** 载荷解不出结构时返回 null，不抛异常（对端数据不可信）。 */
    public static function fromPayload(string $payload): ?self
    {
        try {
            $row = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;
        $data = $row['data'] ?? null;

        return new self(
            is_string($id) ? $id : null,
            is_string($name) ? $name : null,
            is_string($data) ? $data : '',
        );
    }
}
