<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Biz;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\BizGateway;
use Sandadmin\Domain\Mcp\BizReply;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Infra\Mcp\Biz\BizToolRunner;

/** 业务工具执行器：网关回包与 ToolOutcome 的映射。 */
final class BizToolRunnerTest extends TestCase
{
    public function testOkReplyBecomesSuccess(): void
    {
        $gateway = new RecordingGateway(BizReply::ok(['items' => [1, 2]]));
        $runner = new BizToolRunner($gateway, 'depts_tree');
        $outcome = $runner->run($this->identity(), [], new CallContext('req_a', '127.0.0.1', 'mcp'));

        self::assertTrue($outcome->succeeded);
        self::assertSame(['items' => [1, 2]], $outcome->payload);
        self::assertSame('depts_tree', $gateway->operation);
        self::assertSame('req_a', $gateway->context?->requestId);
    }

    public function testFailReplyKeepsContractCode(): void
    {
        $gateway = new RecordingGateway(BizReply::fail(403, 'PERMISSION_DENIED', '你没有权限做这个操作', ['scope' => ['越权']]));
        $outcome = (new BizToolRunner($gateway, 'depts_create'))->run($this->identity(), [], new CallContext('req_b', null, 'mcp'));

        self::assertFalse($outcome->succeeded);
        self::assertSame('PERMISSION_DENIED', $outcome->errorCode);
        $payload = is_array($outcome->payload) ? $outcome->payload : [];
        self::assertSame('你没有权限做这个操作', $payload['message'] ?? null);
        self::assertSame(['scope' => ['越权']], $payload['fields'] ?? null);
    }

    public function testMissingContextFallsBackSynthetic(): void
    {
        // 绕过内核的直调允许缺省上下文，runner 自己补一条 request_id
        $gateway = new RecordingGateway(BizReply::ok([]));
        $outcome = (new BizToolRunner($gateway, 'depts_tree'))->run($this->identity(), []);

        self::assertTrue($outcome->succeeded);
        self::assertNotNull($gateway->context);
        self::assertStringStartsWith('req_', $gateway->context->requestId);
    }

    private function identity(): McpIdentity
    {
        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']);
    }
}

final class RecordingGateway implements BizGateway
{
    public ?string $operation = null;

    public ?CallContext $context = null;

    public function __construct(private readonly BizReply $reply)
    {
    }

    /** @param array<string, mixed> $arguments */
    public function call(McpIdentity $identity, string $operation, array $arguments, CallContext $context): BizReply
    {
        $this->operation = $operation;
        $this->context = $context;

        return $this->reply;
    }
}
