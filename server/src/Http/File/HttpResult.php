<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

final readonly class HttpResult
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string> $fields
     */
    private function __construct(
        public int $status,
        public ?array $data,
        public ?string $errorCode,
        public ?string $message,
        public array $fields,
        public string $requestId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(int $status, array $data, string $requestId): self
    {
        return new self($status, $data, null, null, [], $requestId);
    }

    /**
     * @param array<string, string> $fields
     */
    public static function error(int $status, string $code, string $message, array $fields, string $requestId): self
    {
        return new self($status, null, $code, $message, $fields, $requestId);
    }

    public function json(): string
    {
        if ($this->errorCode !== null) {
            $payload = [
                'code' => $this->errorCode,
                'message' => $this->message ?? '',
                'fields' => $this->fields === [] ? new \stdClass() : $this->fields,
                'request_id' => $this->requestId,
            ];
        } else {
            $payload = $this->data ?? new \stdClass();
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
