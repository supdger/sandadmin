<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Sandadmin\Infra\Log\SdkQuietLogger;

final class SdkQuietLoggerTest extends TestCase
{
    public function testDropsDebugAndInfoAndContext(): void
    {
        $inner = new class () extends AbstractLogger {
            /** @var list<array{string, string, array<array-key, mixed>}> */
            public array $records = [];

            /** @param array<array-key, mixed> $context */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [is_string($level) ? $level : '', (string) $message, $context];
            }
        };
        $logger = new SdkQuietLogger($inner);
        $logger->debug('调用', ['arguments' => ['password' => 'x']]);
        $logger->info('调用', ['arguments' => ['password' => 'x']]);
        $logger->warning('出错', ['arguments' => ['password' => 'x']]);

        self::assertSame([['warning', '出错', []]], $inner->records);
    }
}
