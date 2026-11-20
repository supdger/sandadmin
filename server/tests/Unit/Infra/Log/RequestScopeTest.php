<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Log;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Log\RequestScope;

final class RequestScopeTest extends TestCase
{
    private RequestScope $scope;

    protected function setUp(): void
    {
        $this->scope = new RequestScope();
        $this->scope->clear();
    }

    protected function tearDown(): void
    {
        $this->scope->clear();
    }

    public function testFieldsEmptyWithoutBind(): void
    {
        self::assertSame([], $this->scope->fields());
    }

    public function testBindSetsRequestTaskAndAttempt(): void
    {
        $this->scope->bind('req-abc12345', 'task-1', 2);

        self::assertSame(
            ['request_id' => 'req-abc12345', 'task_id' => 'task-1', 'attempt' => 2],
            $this->scope->fields(),
        );
    }

    public function testBindSkipsNullAndEmptyValues(): void
    {
        $this->scope->bind(null, '', null);

        self::assertSame([], $this->scope->fields());
    }

    public function testInvokeMergesFieldsIntoExtra(): void
    {
        $this->scope->bind('req-abc12345');
        $record = ($this->scope)(['message' => 'hi', 'extra' => ['other' => 'keep']]);
        $extra = $record['extra'] ?? null;
        self::assertIsArray($extra);

        self::assertSame('req-abc12345', $extra['request_id']);
        self::assertSame('keep', $extra['other']);
    }

    public function testInvokeScopeFieldsWinOverCallerExtra(): void
    {
        $this->scope->bind('req-real0000');
        $record = ($this->scope)(['message' => 'hi', 'extra' => ['request_id' => 'req-fake0000']]);
        $extra = $record['extra'] ?? null;
        self::assertIsArray($extra);

        self::assertSame('req-real0000', $extra['request_id']);
    }

    public function testInvokeWithoutBindLeavesRecordUntouched(): void
    {
        $input = ['message' => 'hi', 'extra' => []];

        self::assertSame($input, ($this->scope)($input));
    }

    public function testClearRemovesBoundFields(): void
    {
        $this->scope->bind('req-abc12345', 'task-1', 1);
        $this->scope->clear();

        self::assertSame([], $this->scope->fields());
    }
}
