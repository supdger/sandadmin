<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Log;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Log\LogRetentionException;
use Sandadmin\Infra\Log\LogRetentionPolicy;

/** 日志留存策略：通道分类、下限校验与文件清理目标解析。 */
final class LogRetentionPolicyTest extends TestCase
{
    /** @return array<string, mixed> */
    private function channel(string $kind, int $days, string $file = '/tmp/x.log'): array
    {
        return [
            'retention' => ['kind' => $kind, 'days' => $days],
            'handlers' => [
                ['class' => 'Monolog\\Handler\\RotatingFileHandler', 'constructor' => [$file, 7]],
            ],
        ];
    }

    public function testBelowSecurityMinIsRejected(): void
    {
        $policy = LogRetentionPolicy::fromArray(['security' => $this->channel('security', 179)]);
        try {
            $policy->assertValid();
            self::fail('低于下限应抛异常');
        } catch (LogRetentionException $error) {
            self::assertStringContainsString('security', $error->getMessage());
            self::assertStringContainsString('179', $error->getMessage());
            self::assertStringContainsString('180', $error->getMessage());
        }
    }

    public function testBelowGeneralMinIsRejected(): void
    {
        $policy = LogRetentionPolicy::fromArray(['default' => $this->channel('general', 29)]);
        $this->expectException(LogRetentionException::class);
        $policy->assertValid();
    }

    public function testMinBoundaryIsAccepted(): void
    {
        $policy = LogRetentionPolicy::fromArray([
            'security' => $this->channel('security', 180),
            'default' => $this->channel('general', 30),
        ]);
        $policy->assertValid();
        self::assertSame(180, $policy->resolveDays('security'));
        self::assertSame(30, $policy->resolveDays('default'));
    }

    public function testAboveMinIsAccepted(): void
    {
        $policy = LogRetentionPolicy::fromArray([
            'security' => $this->channel('security', 365),
            'default' => $this->channel('general', 90),
        ]);
        $policy->assertValid();
        self::assertSame(365, $policy->resolveDays('security'));
        self::assertSame(90, $policy->resolveDays('default'));
    }

    public function testMissingRetentionDefaultsToGeneral180(): void
    {
        $policy = LogRetentionPolicy::fromArray([
            'default' => ['handlers' => [['constructor' => ['/tmp/x.log', 7]]]],
        ]);
        $policy->assertValid();
        self::assertSame(180, $policy->resolveDays('default'));
    }

    public function testUnknownKindIsRejected(): void
    {
        $policy = LogRetentionPolicy::fromArray(['default' => $this->channel('audit', 365)]);
        $this->expectException(LogRetentionException::class);
        $policy->assertValid();
    }

    public function testResolveDaysRejectsUnknownChannel(): void
    {
        $policy = LogRetentionPolicy::fromArray(['default' => $this->channel('general', 60)]);
        $this->expectException(LogRetentionException::class);
        $policy->resolveDays('nope');
    }

    public function testListFileTargetsCollectsHandlerPaths(): void
    {
        $policy = LogRetentionPolicy::fromArray([
            'default' => $this->channel('general', 60, '/tmp/a.log'),
            'security' => [
                'retention' => ['kind' => 'security', 'days' => 200],
                'handlers' => [
                    ['constructor' => ['/tmp/b.log', 7]],
                    // 非文件型 handler（首位参数非字符串）不产出清理目标
                    ['constructor' => [['level' => 'info']]],
                ],
            ],
        ]);
        $targets = $policy->listFileTargets();
        self::assertCount(2, $targets);
        self::assertSame(
            ['channel' => 'default', 'days' => 60, 'path' => '/tmp/a.log'],
            $targets[0],
        );
        self::assertSame(
            ['channel' => 'security', 'days' => 200, 'path' => '/tmp/b.log'],
            $targets[1],
        );
    }
}
