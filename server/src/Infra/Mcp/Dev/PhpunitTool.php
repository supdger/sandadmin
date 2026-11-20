<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;

/**
 * 开发工具：只跑白名单 PHPUnit 目标。
 * target 接受两种形状——tests/ 下的相对路径（必须真实存在且在 tests 根内），
 * 或 Sandadmin\Tests\* 全限定类名（映射回同路径再校验）；其余一律拒绝。
 * argv 是固定前缀 + 校验过的单一路径参数，不经 shell，无注入面。
 */
final class PhpunitTool implements ToolRunner
{
    public const DEFAULT_TIMEOUT = 120;

    private const CLASS_PREFIX = 'Sandadmin\\Tests\\';

    /** @var \Closure(list<string>, string, int): SubprocessOutcome */
    private readonly \Closure $runner;

    /** @param null|callable(list<string>, string, int): SubprocessOutcome $runner 测试注入；缺省用真实 Subprocess */
    public function __construct(
        private readonly string $serverRoot,
        private readonly int $timeoutSeconds = self::DEFAULT_TIMEOUT,
        ?callable $runner = null,
    ) {
        $this->runner = $runner === null
            ? (new Subprocess())->run(...)
            : $runner(...);
    }

    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments, ?CallContext $context = null): ToolOutcome
    {
        $target = $arguments['target'] ?? '';
        if (!is_string($target) || trim($target) === '') {
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_BAD_ARGUMENT, ['message' => 'target 必填：tests/ 下相对路径或 Sandadmin\\Tests\\* 类名']);
        }
        $relative = $this->resolveTarget(trim($target));
        if ($relative === null) {
            return ToolOutcome::fail(ErrorCodeRegistry::MCP_TARGET_REJECTED, [
                'message' => 'target 不在白名单：只接受 tests/ 下已存在的 .php 文件或对应的 Sandadmin\\Tests\\* 类名',
            ]);
        }
        $outcome = ($this->runner)(
            [PHP_BINARY, 'vendor/bin/phpunit', $relative],
            $this->serverRoot,
            $this->timeoutSeconds,
        );

        return ToolOutcome::ok([
            'target' => $relative,
            'passed' => $outcome->exitCode === 0 && !$outcome->timedOut,
            'exit_code' => $outcome->exitCode,
            'timed_out' => $outcome->timedOut,
            'duration_ms' => $outcome->durationMs,
            'output' => LogRedactor::redact($outcome->output),
        ]);
    }

    private function resolveTarget(string $target): ?string
    {
        if (str_starts_with($target, self::CLASS_PREFIX)) {
            $relative = 'tests/' . str_replace('\\', '/', substr($target, strlen(self::CLASS_PREFIX))) . '.php';
        } else {
            $relative = $target;
        }
        if (!preg_match('#^tests/[A-Za-z0-9_\-/]+\.php$#', $relative)) {
            return null;
        }
        $root = realpath($this->serverRoot . '/tests');
        $full = realpath($this->serverRoot . '/' . $relative);
        if ($root === false || $full === false || !is_file($full)) {
            return null;
        }
        if (!str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $relative;
    }
}
