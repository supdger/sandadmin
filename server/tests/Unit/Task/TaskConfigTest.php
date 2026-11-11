<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sandadmin\Task\InvalidTaskException;
use Sandadmin\Task\MappedTaskHandlerFactory;
use Sandadmin\Task\TaskConfig;

final class TaskConfigTest extends TestCase
{
    public function testDefaultsAreValid(): void
    {
        $config = TaskConfig::fromArray([]);
        self::assertSame(1.0, $config->pollInterval);
        self::assertSame(60, $config->leaseSeconds);
        self::assertSame(3, $config->maxAttempts);
        self::assertSame(300, $config->timeoutSeconds);
        self::assertSame(4, $config->defaultConcurrency);
        self::assertSame(10, $config->maxBatch);
        $policy = $config->claimPolicy();
        self::assertSame(60, $policy->leaseSeconds);
        self::assertSame(3, $policy->maxAttempts);
        self::assertSame(300, $config->timeoutFor('anything'));
    }

    public function testOverridesApply(): void
    {
        $config = TaskConfig::fromArray([
            'poll_interval' => 0.5,
            'lease_seconds' => 30,
            'max_attempts' => 5,
            'timeout_seconds' => 0,
            'timeouts' => ['export' => 600],
            'concurrency' => ['export' => 2],
            'default_concurrency' => 0,
            'max_batch' => 50,
        ]);
        self::assertSame(0.5, $config->pollInterval);
        self::assertSame(30, $config->leaseSeconds);
        self::assertSame(600, $config->timeoutFor('export'));
        self::assertSame(0, $config->timeoutFor('other'));
        self::assertSame(2, $config->claimPolicy()->limitFor('export'));
        self::assertSame(0, $config->claimPolicy()->limitFor('other'));
    }

    public function testInvalidValuesRejected(): void
    {
        $this->expectException(InvalidTaskException::class);
        TaskConfig::fromArray(['max_attempts' => 0]);
    }

    public function testNonIntegerRejected(): void
    {
        $this->expectException(InvalidTaskException::class);
        TaskConfig::fromArray(['lease_seconds' => 'sixty']);
    }

    public function testBadMapRejected(): void
    {
        $this->expectException(InvalidTaskException::class);
        TaskConfig::fromArray(['timeouts' => ['export' => 'x']]);
    }
}
