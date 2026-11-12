<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use Psr\Log\LoggerInterface;
use Sandadmin\Domain\Error\ErrorLogger;

/** PSR-3 适配。上下文只含 request_id 与异常对象，不记录凭证。 */
final class PsrErrorLogger implements ErrorLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function unexpected(\Throwable $error, string $requestId): void
    {
        $this->logger->error('未处理的请求异常', [
            'request_id' => $requestId,
            'exception' => $error,
        ]);
    }
}
