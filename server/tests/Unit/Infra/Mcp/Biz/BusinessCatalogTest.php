<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Biz;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Mcp\ToolCatalog;
use Sandadmin\Domain\Mcp\BizGateway;
use Sandadmin\Domain\Mcp\BizReply;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolTier;
use Sandadmin\Infra\Mcp\Biz\BizDispatch;
use Sandadmin\Infra\Mcp\Biz\BizToolRunner;
use Sandadmin\Infra\Mcp\Biz\BusinessCatalog;

/** 业务工具登记表：档位、权限码、操作映射与 input schema 的机械约束。 */
final class BusinessCatalogTest extends TestCase
{
    public function testEveryEntryRegistersSpecWithRunner(): void
    {
        $specs = BusinessCatalog::specs(new FakeBizGateway());
        self::assertCount(count(BusinessCatalog::entries()), $specs);
        foreach ($specs as $spec) {
            self::assertInstanceOf(BizToolRunner::class, $spec->runner);
            self::assertMatchesRegularExpression('/^system:[a-z0-9_-]+(:[a-z0-9_-]+)+$/', $spec->permission, $spec->name);
            self::assertSame('object', $spec->inputSchema['type'] ?? null, $spec->name);
        }
        // ToolCatalog 构造本身查重名，重复即抛
        self::assertCount(count($specs), (new ToolCatalog($specs))->all());
    }

    public function testReadTierIsGetAndClosedInput(): void
    {
        foreach (BusinessCatalog::entries() as $entry) {
            if ($entry['tier'] !== ToolTier::Read) {
                continue;
            }
            self::assertSame('GET', $entry['method'], $entry['name']);
            self::assertSame(false, $entry['input']['additionalProperties'], $entry['name']);
        }
    }

    public function testWriteTierDeclaresIdempotencyKeyAndDryRun(): void
    {
        $writes = 0;
        foreach (BusinessCatalog::entries() as $entry) {
            if ($entry['tier'] === ToolTier::Read) {
                continue;
            }
            $properties = $entry['input']['properties'] ?? [];
            if (!is_array($properties)) {
                $properties = [];
            }
            self::assertArrayHasKey('idempotency_key', $properties, $entry['name']);
            self::assertArrayHasKey('dry_run', $properties, $entry['name']);
            self::assertTrue($entry['input']['additionalProperties'], $entry['name']);
            if ($entry['tier'] === ToolTier::Write) {
                $writes++;
            }
        }
        self::assertGreaterThan(0, $writes);
    }

    public function testEveryExecutableOperationHasDispatchPermission(): void
    {
        $dispatched = BizDispatch::permissions();
        foreach (BusinessCatalog::entries() as $entry) {
            if ($entry['tier'] === ToolTier::HighRisk) {
                // 高危档在内核短路、不进派发表
                self::assertArrayNotHasKey($entry['operation'], $dispatched, $entry['name']);
                continue;
            }
            self::assertArrayHasKey($entry['operation'], $dispatched, $entry['name']);
            self::assertSame($entry['permission'], $dispatched[$entry['operation']], $entry['name']);
        }
    }

    public function testHighRiskToolsAreN4ListOnly(): void
    {
        $expected = ['users_export', 'users_reset_password', 'users_assign_roles', 'roles_save_permissions'];
        $got = [];
        foreach (BusinessCatalog::entries() as $entry) {
            if ($entry['tier'] === ToolTier::HighRisk) {
                $got[] = $entry['operation'];
            }
        }
        sort($got);
        sort($expected);
        self::assertSame($expected, $got);
    }
}

final class FakeBizGateway implements BizGateway
{
    /** @param array<string, mixed> $arguments */
    public function call(McpIdentity $identity, string $operation, array $arguments, CallContext $context): BizReply
    {
        return BizReply::ok(['operation' => $operation]);
    }
}
