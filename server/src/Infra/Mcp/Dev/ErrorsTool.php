<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;

/**
 * 开发工具：最近错误与慢请求摘读。
 * 扫指定日志目录下最新的几个 .log 文件尾部，按类别过滤、脱敏后返回；
 * 每行、每文件、总量都有上限，原始日志不出本机之外的边界。
 */
final class ErrorsTool implements ToolRunner
{
    private const SCAN_FILES_MAX = 5;

    private const FILE_TAIL_BYTES = 262144;

    private const LINE_MAX = 500;

    private const KINDS = ['error', 'slow', 'all'];

    private const ERROR_PATTERN = '/error|exception|fatal|critical|panic|traceback/i';

    private const SLOW_PATTERN = '/slow|duration|elapsed|timeout|took/i';

    public function __construct(private readonly string $logDir)
    {
    }

    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments): ToolOutcome
    {
        $kind = $arguments['kind'] ?? 'all';
        if (!is_string($kind) || !in_array($kind, self::KINDS, true)) {
            return ToolOutcome::fail('MCP_BAD_ARGUMENT', ['message' => 'kind 只能是 error / slow / all']);
        }
        $lines = $arguments['lines'] ?? 50;
        if (!is_int($lines) || $lines < 1 || $lines > 200) {
            return ToolOutcome::fail('MCP_BAD_ARGUMENT', ['message' => 'lines 必须是 1-200 的整数']);
        }

        return ToolOutcome::ok([
            'entries' => $this->collect($kind, $lines),
            'log_dir' => $this->logDir,
        ]);
    }

    /** @return list<array{file: string, line: string}> */
    private function collect(string $kind, int $maxLines): array
    {
        $entries = [];
        foreach ($this->newestLogs() as $file) {
            foreach ($this->tailLines($file) as $line) {
                if (count($entries) >= $maxLines) {
                    return $entries;
                }
                if (!$this->matches($kind, $line)) {
                    continue;
                }
                $entries[] = [
                    'file' => basename($file),
                    'line' => LogRedactor::redact(mb_substr($line, 0, self::LINE_MAX)),
                ];
            }
        }

        return $entries;
    }

    /** @return list<string> */
    private function newestLogs(): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }
        $files = glob(rtrim($this->logDir, '/') . '/*.log') ?: [];
        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return array_slice($files, 0, self::SCAN_FILES_MAX);
    }

    /** @return list<string> 文件尾部按时间顺序的行 */
    private function tailLines(string $file): array
    {
        $size = filesize($file);
        if (!is_int($size) || $size <= 0) {
            return [];
        }
        $offset = max(0, $size - self::FILE_TAIL_BYTES);
        $content = file_get_contents($file, false, null, $offset);
        if ($content === false) {
            return [];
        }

        return preg_split('/\r?\n/', $content) ?: [];
    }

    private function matches(string $kind, string $line): bool
    {
        if (trim($line) === '') {
            return false;
        }
        $isError = preg_match(self::ERROR_PATTERN, $line) === 1;
        $isSlow = preg_match(self::SLOW_PATTERN, $line) === 1;

        return match ($kind) {
            'error' => $isError,
            'slow' => $isSlow,
            default => $isError || $isSlow,
        };
    }
}
