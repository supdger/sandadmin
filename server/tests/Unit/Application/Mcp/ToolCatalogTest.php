<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Mcp\ToolCatalog;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;
use Sandadmin\Domain\Mcp\ToolSpec;
use Sandadmin\Domain\Mcp\ToolTier;

/** 登记表：显式清单、重名即拒。 */
final class ToolCatalogTest extends TestCase
{
    public function testDuplicateNameRejected(): void
    {
        $runner = new StubRunner();
        $this->expectException(\InvalidArgumentException::class);
        new ToolCatalog([
            new ToolSpec('same', '', '', ToolTier::Read, 'mcp:dev:a', '', ['type' => 'object'], $runner),
            new ToolSpec('same', '', '', ToolTier::Read, 'mcp:dev:b', '', ['type' => 'object'], $runner),
        ]);
    }

    public function testFindAndAll(): void
    {
        $runner = new StubRunner();
        $catalog = new ToolCatalog([
            new ToolSpec('one', '', '', ToolTier::Read, 'mcp:dev:a', '', ['type' => 'object'], $runner),
        ]);

        self::assertNotNull($catalog->find('one'));
        self::assertNull($catalog->find('two'));
        self::assertCount(1, $catalog->all());
    }

    public function testBadNameRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ToolSpec('a;b', '', '', ToolTier::Read, 'mcp:dev:a', '', ['type' => 'object'], new StubRunner());
    }
}

final class StubRunner implements ToolRunner
{
    public function run(McpIdentity $identity, array $arguments, ?CallContext $context = null): ToolOutcome
    {
        return ToolOutcome::ok(null);
    }
}
