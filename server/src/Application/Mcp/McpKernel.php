<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Mcp;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Domain\Mcp\AuditSink;
use Sandadmin\Domain\Mcp\McpCall;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\PendingRequest;
use Sandadmin\Domain\Mcp\PendingState;
use Sandadmin\Domain\Mcp\RateLimiter;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolSpec;
use Sandadmin\Domain\Mcp\ToolTier;

/**
 * 每次 MCP 工具调用的收口：权限码 → 三档 → 按工具限流 → 执行 → 审计。
 * 普通写档本期只立门槛：幂等键必查、dry_run 透传给工具自己处理；
 * 与 v1 写接口同一套幂等存储的接通在 sand_system_api_token 落地时做。
 */
final class McpKernel
{
    private const IDEMPOTENCY_KEY_MAX = 255;

    public function __construct(
        private readonly McpIdentity $identity,
        private readonly RateLimiter $limiter,
        private readonly AuditSink $sink,
        private readonly string $requestId,
        private readonly ?string $ip,
        private readonly int $toolLimit,
        private readonly int $windowSeconds,
    ) {
    }

    /** @param array<string, mixed> $arguments */
    public function call(ToolSpec $spec, array $arguments): ToolOutcome
    {
        $started = microtime(true);
        $idempotencyKey = $this->idempotencyKey($arguments);
        $outcome = $this->invoke($spec, $arguments, $idempotencyKey);
        $this->sink->record(new McpCall(
            $this->requestId,
            $this->identity->subject,
            $this->identity->credential,
            $spec->name,
            $spec->tier,
            $idempotencyKey,
            McpCall::digestOf($arguments),
            $outcome->succeeded ? 'succeeded' : 'failed:' . $outcome->errorCode,
            (int) round((microtime(true) - $started) * 1000),
            $this->ip,
        ));

        return $outcome;
    }

    /** @param array<string, mixed> $arguments */
    private function invoke(ToolSpec $spec, array $arguments, ?string $idempotencyKey): ToolOutcome
    {
        if (!$this->identity->can($spec->permission)) {
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_FORBIDDEN, ['message' => '该凭据没有这个工具需要的权限码', 'permission' => $spec->permission]);
        }
        if ($spec->tier === ToolTier::HighRisk) {
            // 高危写永不直接执行（M-02）：只给「待确认请求」形状，本期未开放。
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_HIGH_RISK_NOT_OPEN, [
                'message' => '高危写工具本期未开放，只能生成待确认请求',
                'pending_request' => new PendingRequest($spec->name, McpCall::digestOf($arguments), PendingState::Pending),
            ]);
        }
        if ($spec->tier === ToolTier::Write && $idempotencyKey === null) {
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_IDEMPOTENCY_REQUIRED, ['message' => '普通写工具必须带幂等键 idempotency_key']);
        }
        $bucket = 'mcp:tool:' . $this->identity->credential . ':' . $spec->name;
        if (!$this->limiter->hit($bucket, $this->toolLimit, $this->windowSeconds)) {
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_RATE_LIMITED, ['message' => '这个工具的调用频率超限，稍后再试']);
        }
        $dryRun = ($arguments['dry_run'] ?? false) === true;
        if ($spec->tier === ToolTier::Write && $dryRun) {
            unset($arguments['idempotency_key'], $arguments['dry_run']);

            return ToolOutcome::ok(['dry_run' => true, 'tool' => $spec->name, 'arguments' => $arguments]);
        }
        try {
            return $spec->runner->run($this->identity, $arguments);
        } catch (\Throwable) {
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_TOOL_FAILED, ['message' => '工具执行失败，详情见服务端日志']);
        }
    }

    /** @param array<string, mixed> $arguments */
    private function idempotencyKey(array $arguments): ?string
    {
        $key = $arguments['idempotency_key'] ?? null;
        if (!is_string($key)) {
            return null;
        }
        $key = trim($key);
        if ($key === '' || mb_strlen($key) > self::IDEMPOTENCY_KEY_MAX) {
            return null;
        }

        return $key;
    }
}
