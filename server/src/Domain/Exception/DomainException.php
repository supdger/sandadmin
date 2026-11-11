<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Exception;

/**
 * 可展示给调用方的业务错误。由 ErrorMiddleware 映射为 {code,message,fields,request_id}。
 * errorCode 必须是 api-conventions §2.4 错误码总表里的大写蛇形枚举；
 * 新增错误码先登记总表再使用，不在这里发明。
 */
abstract class DomainException extends \RuntimeException
{
    private const CODE_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    /** @param array<string, string> $fields */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly array $fields = [],
        private readonly int $httpStatus = 400,
        ?\Throwable $previous = null,
    ) {
        if ($message === '') {
            throw new \InvalidArgumentException('message 不能为空');
        }
        if (preg_match(self::CODE_PATTERN, $errorCode) !== 1) {
            throw new \InvalidArgumentException('error code 必须是大写蛇形枚举');
        }
        if ($httpStatus < 400 || $httpStatus > 599) {
            throw new \InvalidArgumentException('http status 必须是 4xx 或 5xx');
        }
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
