<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Security\CorsConfig;
use Sandadmin\Application\Security\SecurityHeadersConfig;

/**
 * env 数值配置全链路口径：security.php 透传 env 原值，装载层把
 * 缺失/空串/非整数落默认、显式 '0' 保留。
 */
final class SecurityConfigFileTest extends TestCase
{
    private const SECURITY_FILE = __DIR__ . '/../../../../config/security.php';

    /** @var array<string, array{server: string|null, env: string|null}> */
    private array $backup = [];

    private const KEYS = [
        'SAND_HSTS_MAX_AGE',
        'SAND_CORS_MAX_AGE',
    ];

    protected function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $serverValue = $_SERVER[$key] ?? null;
            $envValue = getenv($key);
            $this->backup[$key] = [
                'server' => is_string($serverValue) ? $serverValue : null,
                'env' => is_string($envValue) ? $envValue : null,
            ];
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::KEYS as $key) {
            $saved = $this->backup[$key];
            if ($saved['server'] === null) {
                unset($_SERVER[$key], $_ENV[$key]);
            } else {
                $_SERVER[$key] = $saved['server'];
            }
            if ($saved['env'] === null) {
                putenv($key);
            } else {
                putenv("{$key}={$saved['env']}");
            }
        }
    }

    /** @return array<array-key, mixed> */
    private static function loadSecurityFile(): array
    {
        $file = realpath(self::SECURITY_FILE);
        if (!is_string($file)) {
            self::fail('找不到 config/security.php');
        }
        $config = include $file;
        if (!is_array($config)) {
            self::fail('security.php 未返回数组');
        }

        return $config;
    }

    /**
     * @return iterable<string, array{env: string|null, expected: int}>
     */
    public static function hstsMaxAgeCases(): iterable
    {
        yield '未设置→默认' => ['env' => null, 'expected' => 15552000];
        yield '空串→默认' => ['env' => '', 'expected' => 15552000];
        yield '非整数→默认' => ['env' => 'abc', 'expected' => 15552000];
        yield '显式 0 保留' => ['env' => '0', 'expected' => 0];
        yield '正常值' => ['env' => '86400', 'expected' => 86400];
    }

    /**
     * @return iterable<string, array{env: string|null, expected: int}>
     */
    public static function corsMaxAgeCases(): iterable
    {
        yield '空串→默认' => ['env' => '', 'expected' => 600];
        yield '非整数→默认' => ['env' => 'soon', 'expected' => 600];
        yield '显式 0 保留' => ['env' => '0', 'expected' => 0];
    }

    #[DataProvider('hstsMaxAgeCases')]
    public function testHstsMaxAgeEnvParsing(?string $env, int $expected): void
    {
        if ($env !== null) {
            $_SERVER['SAND_HSTS_MAX_AGE'] = $env;
        }

        self::assertSame($expected, SecurityHeadersConfig::fromArray(self::loadSecurityFile())->hstsMaxAge);
    }

    #[DataProvider('corsMaxAgeCases')]
    public function testCorsMaxAgeEnvParsing(?string $env, int $expected): void
    {
        if ($env !== null) {
            $_SERVER['SAND_CORS_MAX_AGE'] = $env;
        }

        self::assertSame($expected, CorsConfig::fromArray(self::loadSecurityFile())->maxAge);
    }
}
