<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;

/**
 * 开发工具：跑 tools/pre-merge.sh --fast 快速门禁。
 * 命令是整条固定 argv，不接任何调用方参数；脚本缺失时报未就绪。
 */
final class PreMergeTool implements ToolRunner
{
    public const DEFAULT_TIMEOUT = 180;

    /** @var \Closure(list<string>, string, int): SubprocessOutcome */
    private readonly \Closure $runner;

    /** @param null|callable(list<string>, string, int): SubprocessOutcome $runner 测试注入；缺省用真实 Subprocess */
    public function __construct(
        private readonly string $repoRoot,
        private readonly int $timeoutSeconds = self::DEFAULT_TIMEOUT,
        ?callable $runner = null,
    ) {
        $this->runner = $runner === null
            ? (new Subprocess())->run(...)
            : $runner(...);
    }

    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments): ToolOutcome
    {
        $script = $this->repoRoot . '/tools/pre-merge.sh';
        if (!is_file($script)) {
            return ToolOutcome::fail('MCP_GATE_MISSING', ['message' => 'tools/pre-merge.sh 不存在']);
        }
        $outcome = ($this->runner)(
            ['bash', 'tools/pre-merge.sh', '--fast'],
            $this->repoRoot,
            $this->timeoutSeconds,
        );

        return ToolOutcome::ok([
            'passed' => $outcome->exitCode === 0 && !$outcome->timedOut,
            'exit_code' => $outcome->exitCode,
            'timed_out' => $outcome->timedOut,
            'duration_ms' => $outcome->durationMs,
            'output' => LogRedactor::redact($outcome->output),
        ]);
    }
}
