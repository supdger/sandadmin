<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Sandadmin\Infra\Log\PsrErrorLogger;

final class PsrErrorLoggerTest extends TestCase
{
    public function testLogsRequestIdAndExceptionWithoutCredentialFields(): void
    {
        $inner = new class extends AbstractLogger {
            /** @var list<array{message: string, context: array<mixed>}> */
            public array $records = [];

            /** @param array<mixed> $context */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };
        $error = new \RuntimeException('token=super-secret');
        (new PsrErrorLogger($inner))->unexpected($error, 'req-12345678');

        self::assertCount(1, $inner->records);
        $context = $inner->records[0]['context'];
        self::assertSame('req-12345678', $context['request_id'] ?? null);
        self::assertSame($error, $context['exception'] ?? null);
        self::assertArrayNotHasKey('authorization', $context);
        self::assertArrayNotHasKey('credential', $context);
    }
}
