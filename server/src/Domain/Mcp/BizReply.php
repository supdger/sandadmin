<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 业务工具经 v1 业务层执行后的回包：与契约信封同语义（status +
 * code/message/data|fields），只去掉传输层的 request_id——
 * request_id 已在 CallContext 与 McpCall 审计里。
 */
final readonly class BizReply
{
    /**
     * @param array<string, list<string>> $fields
     */
    private function __construct(
        public int $status,
        public string $code,
        public string $message,
        public mixed $data,
        public array $fields,
    ) {
    }

    public static function ok(mixed $data): self
    {
        return new self(200, 'OK', '操作成功', $data, []);
    }

    /** @param array<string, list<string>> $fields */
    public static function fail(int $status, string $code, string $message, array $fields = []): self
    {
        if ($code === '') {
            throw new \InvalidArgumentException('失败回包必须带错误码');
        }

        return new self($status, $code, $message, null, $fields);
    }

    public function succeeded(): bool
    {
        return $this->status < 400;
    }
}
