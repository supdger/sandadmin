<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Biz;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\BizReply;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpActorSource;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Http\BizKernel;
use Sandadmin\Infra\Mcp\Biz\KernelBizGateway;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;

/** 业务网关：权限内闸、操作登记、错误码与 v1 同一口径。 */
final class KernelBizGatewayTest extends TestCase
{
    public function testUnknownOperationFailsClosed(): void
    {
        $gateway = $this->gateway(fn () => null, new FixedActorSource(null));
        $reply = $gateway->call($this->identity(), 'not_registered', [], $this->context());
        self::assertFalse($reply->succeeded());
        self::assertSame('MCP_TOOL_FAILED', $reply->code);
    }

    public function testMissingKernelReportsInternalError(): void
    {
        $gateway = $this->gateway(fn () => null, new FixedActorSource($this->actor(['system:dept:index'])));
        $reply = $gateway->call($this->identity(), 'depts_tree', [], $this->context());
        self::assertSame(500, $reply->status);
        self::assertSame('INTERNAL_ERROR', $reply->code);
    }

    public function testMissingActorReportsUnauthenticated(): void
    {
        $gateway = $this->gateway($this->kernelFactory(), new FixedActorSource(null));
        $reply = $gateway->call($this->identity(), 'depts_tree', [], $this->context());
        self::assertSame(401, $reply->status);
        self::assertSame('UNAUTHENTICATED', $reply->code);
    }

    public function testActorWithoutPermissionDenied(): void
    {
        $gateway = $this->gateway($this->kernelFactory(), new FixedActorSource($this->actor(['system:dict:index'])));
        $reply = $gateway->call($this->identity(), 'depts_tree', [], $this->context());
        self::assertSame(403, $reply->status);
        self::assertSame('PERMISSION_DENIED', $reply->code);
    }

    public function testPermittedCallReachesDispatcher(): void
    {
        $context = $this->context();
        $received = null;
        $dispatcher = function (BizKernel $kernel, OrgActor $actor, string $operation, array $arguments, CallContext $call) use (&$received): BizReply {
            $received = [$actor->userId, $operation, $arguments, $call->requestId];

            return BizReply::ok(['items' => []]);
        };
        $gateway = $this->gateway($this->kernelFactory(), new FixedActorSource($this->actor(['system:dept:index'])), $dispatcher);
        $reply = $gateway->call($this->identity(), 'depts_tree', ['keyword' => '门店'], $context);

        self::assertTrue($reply->succeeded());
        self::assertSame(['items' => []], $reply->data);
        self::assertSame([9, 'depts_tree', ['keyword' => '门店'], $context->requestId], $received);
    }

    public function testOrgExceptionMapsToContractReply(): void
    {
        $dispatcher = function (): BizReply {
            throw OrgException::invalid(['status' => ['状态不正确']]);
        };
        $gateway = $this->gateway($this->kernelFactory(), new FixedActorSource($this->actor(['system:dept:index'])), $dispatcher);
        $reply = $gateway->call($this->identity(), 'depts_tree', [], $this->context());

        self::assertSame(422, $reply->status);
        self::assertSame('VALIDATION_FAILED', $reply->code);
        self::assertSame(['status' => ['状态不正确']], $reply->fields);
    }

    public function testThrowableBecomesInternalError(): void
    {
        $dispatcher = function (): BizReply {
            throw new \RuntimeException('boom');
        };
        $gateway = $this->gateway($this->kernelFactory(), new FixedActorSource($this->actor(['system:dept:index'])), $dispatcher);
        $reply = $gateway->call($this->identity(), 'depts_tree', [], $this->context());

        self::assertSame(500, $reply->status);
        self::assertSame('INTERNAL_ERROR', $reply->code);
    }

    /** @param list<string> $permissions */
    private function actor(array $permissions): OrgActor
    {
        return new OrgActor(9, '测试账号', null, false, $permissions);
    }

    /** @return \Closure(): ?BizKernel */
    private function kernelFactory(): \Closure
    {
        $connection = $this->createMock(Connection::class);

        return static fn (): BizKernel => BizKernel::fromConnection($connection);
    }

    /**
     * @param \Closure(): ?BizKernel $kernelFactory
     * @param null|\Closure          $dispatcher
     */
    private function gateway(\Closure $kernelFactory, McpActorSource $actors, ?\Closure $dispatcher = null): KernelBizGateway
    {
        return new KernelBizGateway($kernelFactory, $actors, $dispatcher);
    }

    private function identity(): McpIdentity
    {
        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']);
    }

    private function context(): CallContext
    {
        return new CallContext('req_test', '127.0.0.1', 'mcp');
    }
}

final class FixedActorSource implements McpActorSource
{
    public function __construct(private readonly ?OrgActor $actor)
    {
    }

    public function resolve(McpIdentity $identity, Connection $connection): ?OrgActor
    {
        return $this->actor;
    }
}
