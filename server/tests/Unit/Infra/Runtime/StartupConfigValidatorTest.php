<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Runtime;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Runtime\ProcessRecycle;
use Sandadmin\Infra\Runtime\StartupConfigValidator;

final class StartupConfigValidatorTest extends TestCase
{
    public function testAcceptsTheShippedConfigurationShape(): void
    {
        StartupConfigValidator::assertValid(self::config());
        $this->addToAssertionCount(1);
    }

    public function testAcceptsMissingGroups(): void
    {
        StartupConfigValidator::assertValid([]);
        $this->addToAssertionCount(1);
    }

    public function testRejectsNegativeMemoryWatermark(): void
    {
        $config = self::config();
        $config['runtime']['memory_watermark_bytes'] = -1;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime.memory_watermark_bytes');
        StartupConfigValidator::assertValid($config);
    }

    public function testRejectsStringMemoryWatermark(): void
    {
        $config = self::config();
        $config['runtime']['memory_watermark_bytes'] = '80%';
        $this->expectException(\InvalidArgumentException::class);
        StartupConfigValidator::assertValid($config);
    }

    public function testRejectsNegativeStreamThreshold(): void
    {
        $config = self::config();
        $config['stream']['profiles']['web']['max_requests'] = -5;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('stream.profiles.web.max_requests');
        StartupConfigValidator::assertValid($config);
    }

    public function testRejectsUnknownBackpressure(): void
    {
        $config = self::config();
        $config['stream']['profiles']['ai_stream']['backpressure'] = 'drop-old';
        $this->expectException(\InvalidArgumentException::class);
        StartupConfigValidator::assertValid($config);
    }

    public function testRejectsNonPositiveServerStopTimeout(): void
    {
        $config = self::config();
        $config['server']['stop_timeout'] = 0;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('server.stop_timeout');
        StartupConfigValidator::assertValid($config);
    }

    public function testRejectsZeroTaskLease(): void
    {
        $config = self::config();
        $config['task']['lease_seconds'] = 0;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('task.lease_seconds');
        StartupConfigValidator::assertValid($config);
    }

    public function testRejectsZeroProcessCount(): void
    {
        $config = self::config();
        $config['process']['webman']['count'] = 0;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('process.webman.count');
        StartupConfigValidator::assertValid($config);
    }

    /**
     * @return array{
     *     runtime: array<string, mixed>,
     *     stream: array{profiles: array{web: array<string, mixed>, ai_stream: array<string, mixed>, ...}, ...},
     *     server: array<string, mixed>,
     *     task: array<string, mixed>,
     *     process: array<string, array<string, mixed>>, ...
     * }
     */
    private static function config(): array
    {
        return [
            'runtime' => ['memory_watermark_bytes' => null],
            'stream' => [
                'profiles' => [
                    'web' => [
                        'stream_limit' => 50, 'idle_seconds' => 30, 'stream_seconds' => 300,
                        'heartbeat_seconds' => 15, 'probe_seconds' => 30,
                        'backpressure' => 'pause', 'max_requests' => 10000, 'memory_limit_bytes' => 0,
                    ],
                    'ai_stream' => [
                        'stream_limit' => 100, 'idle_seconds' => 30, 'stream_seconds' => 300,
                        'heartbeat_seconds' => 15, 'probe_seconds' => 30,
                        'backpressure' => 'pause', 'max_requests' => 0, 'memory_limit_bytes' => 0,
                    ],
                ],
            ],
            'server' => ['stop_timeout' => 2, 'max_package_size' => 104857600],
            'task' => ['poll_interval' => 1.0, 'lease_seconds' => 60, 'max_attempts' => 3, 'timeout_seconds' => 300, 'max_batch' => 10, 'default_concurrency' => 4],
            'process' => ['webman' => ['handler' => 'app\\process\\Http', 'count' => 4]],
        ];
    }
}
