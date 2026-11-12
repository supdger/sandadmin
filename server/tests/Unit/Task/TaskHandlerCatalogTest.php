<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sandadmin\Task\Handler\NoopTaskHandler;
use Sandadmin\Task\InvalidTaskException;
use Sandadmin\Task\MappedTaskHandlerFactory;
use Sandadmin\Task\TaskHandlerCatalog;
use Sandadmin\Task\UnknownTaskTypeException;

final class TaskHandlerCatalogTest extends TestCase
{
    public function testHostCatalogProvidesNoop(): void
    {
        $handlers = TaskHandlerCatalog::host();
        self::assertArrayHasKey('noop', $handlers);
        self::assertInstanceOf(NoopTaskHandler::class, $handlers['noop']);
    }

    public function testFactoryResolvesAndRejectsUnknown(): void
    {
        $factory = new MappedTaskHandlerFactory(TaskHandlerCatalog::host());
        self::assertInstanceOf(NoopTaskHandler::class, $factory->forType('noop'));
        $this->expectException(UnknownTaskTypeException::class);
        $factory->forType('unregistered');
    }

    public function testFactoryRejectsBadTypeName(): void
    {
        $this->expectException(InvalidTaskException::class);
        new MappedTaskHandlerFactory(['BAD TYPE' => new NoopTaskHandler()]);
    }
}
