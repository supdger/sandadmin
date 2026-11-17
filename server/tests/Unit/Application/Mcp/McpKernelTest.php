<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Mcp\McpKernel;
use Sandadmin\Domain\Mcp\AuditSink;
use Sandadmin\Domain\Mcp\McpCall;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\PendingState;
use Sandadmin\Domain\Mcp\RateLimiter;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;
use Sandadmin\Domain\Mcp\ToolSpec;
use Sandadmin\Domain\Mcp\ToolTier;

/** MCP 内核收口：三档判定、幂等键门槛、按工具限流、权限码、审计落账。 */
final class McpKernelTest extends TestCase
{
    public function testReadTierRunsRunner(): void
    {
        $kernel = $this->kernel(new AllowLimiter(), $sink = new CollectSink());
        $spec = $this->spec(ToolTier::Read, 'read_tool', new EchoRunner());
        $outcome = $kernel->call($spec, ['a' => 1]);

        self::assertTrue($outcome->succeeded);
        self::assertSame(['a' => 1], $outcome->payload);
        self::assertCount(1, $sink->calls);
        self::assertSame('read', $sink->calls[0]->tier->value);
        self::assertSame('succeeded', $sink->calls[0]->outcome);
    }

    public function testWriteTierRequiresIdempotencyKey(): void
    {
        $runner = new EchoRunner();
        $kernel = $this->kernel(new AllowLimiter(), new CollectSink());
        $outcome = $kernel->call($this->spec(ToolTier::Write, 'write_tool', $runner), []);

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_IDEMPOTENCY_REQUIRED', $outcome->errorCode);
        self::assertSame(0, $runner->runs);
    }

    public function testWriteTierWithKeyRuns(): void
    {
        $kernel = $this->kernel(new AllowLimiter(), new CollectSink());
        $outcome = $kernel->call(
            $this->spec(ToolTier::Write, 'write_tool', new EchoRunner()),
            ['idempotency_key' => 'k-1', 'x' => 2],
        );

        self::assertTrue($outcome->succeeded);
        self::assertSame(['idempotency_key' => 'k-1', 'x' => 2], $outcome->payload);
    }

    public function testWriteTierDryRunDoesNotExecute(): void
    {
        $runner = new EchoRunner();
        $kernel = $this->kernel(new AllowLimiter(), new CollectSink());
        $outcome = $kernel->call(
            $this->spec(ToolTier::Write, 'write_tool', $runner),
            ['idempotency_key' => 'k-2', 'dry_run' => true, 'x' => 3],
        );

        self::assertTrue($outcome->succeeded);
        self::assertSame(0, $runner->runs);
        self::assertIsArray($outcome->payload);
        self::assertTrue($outcome->payload['dry_run']);
    }

    public function testHighRiskNeverExecutes(): void
    {
        $runner = new EchoRunner();
        $kernel = $this->kernel(new AllowLimiter(), new CollectSink());
        $outcome = $kernel->call(
            $this->spec(ToolTier::HighRisk, 'danger_tool', $runner),
            ['idempotency_key' => 'k-3'],
        );

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_HIGH_RISK_NOT_OPEN', $outcome->errorCode);
        self::assertSame(0, $runner->runs);
        self::assertIsArray($outcome->payload);
        $pending = $outcome->payload['pending_request'] ?? null;
        self::assertInstanceOf(\Sandadmin\Domain\Mcp\PendingRequest::class, $pending);
        self::assertSame(PendingState::Pending, $pending->state);
        self::assertSame('danger_tool', $pending->tool);
    }

    public function testPermissionDenied(): void
    {
        $kernel = new McpKernel(
            new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['mcp:dev:routes']),
            new AllowLimiter(),
            new CollectSink(),
            'req-1',
            '127.0.0.1',
            30,
            60,
        );
        $outcome = $kernel->call($this->spec(ToolTier::Read, 'read_tool', new EchoRunner()), []);

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_FORBIDDEN', $outcome->errorCode);
    }

    public function testToolRateLimit(): void
    {
        $limiter = new CountLimiter();
        $kernel = $this->kernel($limiter, new CollectSink(), 1);
        $spec = $this->spec(ToolTier::Read, 'read_tool', new EchoRunner());

        self::assertTrue($kernel->call($spec, [])->succeeded);
        $second = $kernel->call($spec, []);
        self::assertFalse($second->succeeded);
        self::assertSame('MCP_RATE_LIMITED', $second->errorCode);
        // 另一个工具是另一个桶，不受牵连
        self::assertTrue($kernel->call($this->spec(ToolTier::Read, 'other_tool', new EchoRunner()), [])->succeeded);
    }

    public function testRunnerThrowableBecomesToolFailed(): void
    {
        $kernel = $this->kernel(new AllowLimiter(), new CollectSink());
        $spec = $this->spec(ToolTier::Read, 'boom', new class implements ToolRunner {
            public function run(McpIdentity $identity, array $arguments): ToolOutcome
            {
                throw new \RuntimeException('内部错误');
            }
        });
        $outcome = $kernel->call($spec, []);

        self::assertFalse($outcome->succeeded);
        self::assertSame('MCP_TOOL_FAILED', $outcome->errorCode);
    }

    private function kernel(RateLimiter $limiter, AuditSink $sink, int $toolLimit = 30): McpKernel
    {
        return new McpKernel(
            new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']),
            $limiter,
            $sink,
            'req-1',
            '127.0.0.1',
            $toolLimit,
            60,
        );
    }

    private function spec(ToolTier $tier, string $name, ToolRunner $runner): ToolSpec
    {
        return new ToolSpec($name, '', '', $tier, 'mcp:dev:any', '', ['type' => 'object'], $runner);
    }
}

final class AllowLimiter implements RateLimiter
{
    public function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        return true;
    }
}

/** 简单计数限流：到额即拒，用于内核分桶行为验证。 */
final class CountLimiter implements RateLimiter
{
    /** @var array<string, int> */
    private array $hits = [];

    public function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        $count = $this->hits[$bucket] ?? 0;
        if ($count >= $limit) {
            return false;
        }
        $this->hits[$bucket] = $count + 1;

        return true;
    }
}

final class CollectSink implements AuditSink
{
    /** @var list<McpCall> */
    public array $calls = [];

    public function record(McpCall $call): void
    {
        $this->calls[] = $call;
    }
}

final class EchoRunner implements ToolRunner
{
    public int $runs = 0;

    public function run(McpIdentity $identity, array $arguments): ToolOutcome
    {
        $this->runs++;

        return ToolOutcome::ok($arguments);
    }
}
