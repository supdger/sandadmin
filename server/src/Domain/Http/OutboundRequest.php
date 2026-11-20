<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Http;

/** 一次出站 HTTP 请求。method 入库前统一转大写；retry 语义见实现类。 */
final class OutboundRequest
{
    private const METHOD_PATTERN = '/^[A-Z][A-Z0-9_-]*$/';

    public readonly string $method;

    public readonly string $url;

    /** @var array<string, string> */
    public readonly array $headers;

    public readonly ?string $body;

    public readonly ?int $maxAttempts;

    public readonly ?bool $retry;

    /**
     * @param array<array-key, mixed> $headers 构造时校验并收敛为 array<string, string>
     * @param int|null $maxAttempts 覆盖配置的最大尝试次数；null 用配置默认
     * @param bool|null $retry null 按幂等方法默认（GET/HEAD/PUT/DELETE/OPTIONS/TRACE）；
     *                         true 强制允许重试，false 一律不重试
     */
    public function __construct(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $maxAttempts = null,
        ?bool $retry = null,
    ) {
        $clean = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value)) {
                throw new \InvalidArgumentException('出站请求头必须是非空字符串键与字符串值');
            }
            // 键值含 CR/LF 会让 "Name: value" 行被拆成多行注入额外头，构造期直接拒绝
            if (strpbrk($name, "\r\n") !== false || strpbrk($value, "\r\n") !== false) {
                throw new \InvalidArgumentException('出站请求头键值不得包含换行符');
            }
            $clean[$name] = $value;
        }
        $normalized = strtoupper(trim($method));
        if (preg_match(self::METHOD_PATTERN, $normalized) !== 1) {
            throw new \InvalidArgumentException('出站请求方法非法');
        }
        if (trim($url) === '') {
            throw new \InvalidArgumentException('出站请求 URL 不能为空');
        }
        if ($maxAttempts !== null && $maxAttempts < 1) {
            throw new \InvalidArgumentException('出站请求 max_attempts 必须为正整数');
        }
        $this->method = $normalized;
        $this->url = $url;
        $this->headers = $clean;
        $this->body = $body;
        $this->maxAttempts = $maxAttempts;
        $this->retry = $retry;
    }

    /** RFC 语义下的幂等方法；POST/PATCH 默认不重试。 */
    public function isIdempotent(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE'], true);
    }
}
