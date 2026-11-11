<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\Error\ErrorLogger;

final class RecordingErrorLogger implements ErrorLogger
{
    /** @var list<array{requestId: string, class: class-string, message: string}> */
    public array $entries = [];

    public function unexpected(\Throwable $error, string $requestId): void
    {
        $this->entries[] = [
            'requestId' => $requestId,
            'class' => $error::class,
            'message' => $error->getMessage(),
        ];
    }
}
