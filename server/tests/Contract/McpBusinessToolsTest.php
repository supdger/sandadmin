<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\ToolTier;
use Sandadmin\Infra\Mcp\Biz\BizDispatch;
use Sandadmin\Infra\Mcp\Biz\BusinessCatalog;

/**
 * 业务 MCP 工具登记表 ↔ contract/openapi.yaml 的机械对齐。
 * 登记表手写（契约 x-mcp 未落地前），本测试保证逐行不漂：
 * route+method 必须命中契约里的真实接口，权限码必须等于该接口的
 * 单一 x-permission 码，派发表权限与登记权限一致。
 */
final class McpBusinessToolsTest extends TestCase
{
    public function testHostContractParsesClean(): void
    {
        $doc = ContractAssertions::parseHostDoc();
        self::assertSame([], $doc['problems'], implode("\n", $doc['problems']));
    }

    public function testEveryToolMapsToContractOperation(): void
    {
        $doc = ContractAssertions::parseHostDoc();
        $operations = [];
        foreach ($doc['operations'] as $operation) {
            $operations[$operation['method'] . ' ' . $operation['path']] = $operation;
        }

        $dispatched = BizDispatch::permissions();
        foreach (BusinessCatalog::entries() as $entry) {
            $key = $entry['method'] . ' ' . $entry['route'];
            self::assertArrayHasKey($key, $operations, $entry['name'] . ' 的 ' . $key . ' 不在契约里');
            $operation = $operations[$key];
            self::assertSame('single', $operation['permission_kind'], $entry['name'] . ' 对应接口权限不是单码');
            self::assertSame([$entry['permission']], $operation['permission_codes'], $entry['name'] . ' 权限码与契约不一致');
            if ($entry['tier'] === ToolTier::Read) {
                self::assertSame('GET', $entry['method'], $entry['name'] . ' 只读档必须映射 GET');
            }
            if ($entry['tier'] === ToolTier::HighRisk) {
                self::assertNotSame('GET', $entry['method'], $entry['name'] . ' 高危档不能映射只读接口');
                self::assertArrayNotHasKey($entry['operation'], $dispatched, $entry['name'] . ' 高危档不得进派发表');
            } else {
                self::assertSame($entry['permission'], $dispatched[$entry['operation']] ?? null, $entry['name'] . ' 派发权限不一致');
            }
        }
    }

    public function testToolNamesDoNotCollideWithContractTools(): void
    {
        $doc = ContractAssertions::parseHostDoc();
        $declared = [];
        foreach ($doc['mcp_tools'] as $tool) {
            $declared[$tool['name']] = true;
        }
        foreach (BusinessCatalog::entries() as $entry) {
            self::assertArrayNotHasKey($entry['name'], $declared, $entry['name'] . ' 与契约 x-mcp-tools 撞名');
        }
    }
}
