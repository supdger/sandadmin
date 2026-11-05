<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Error;

use Sandadmin\Domain\Error\ErrorLogger;
use Sandadmin\Domain\Exception\DomainException;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;

/** 把异常映射为稳定错误体。未知异常不把内部信息返回给调用方。 */
final class ErrorResponder
{
    public function __construct(private readonly ErrorLogger $logger)
    {
    }

    public function respond(\Throwable $error, string $requestId): ErrorBody
    {
        if ($requestId === '') {
            throw new \InvalidArgumentException('request_id 不能为空');
        }
        if ($error instanceof InvalidQueryException) {
            return new ErrorBody(422, 'invalid_query', $error->getMessage(), $error->fields, $requestId);
        }
        if ($error instanceof DomainException) {
            if ($error->httpStatus() >= 500) {
                $this->logger->unexpected($error, $requestId);
            }

            return new ErrorBody(
                $error->httpStatus(),
                $error->errorCode(),
                $error->getMessage(),
                $error->fields(),
                $requestId,
            );
        }

        $this->logger->unexpected($error, $requestId);

        return new ErrorBody(500, 'internal_error', '服务器内部错误', [], $requestId);
    }
}
