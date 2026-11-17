<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

/**
 * 固定命令子进程执行器：argv 数组形式交给 proc_open，不经过 shell，
 * 参数不可拼接；工作目录、超时、输出截尾都在这里收口。
 * 调用方只允许传白名单出来的固定 argv（见 PhpunitTool / PreMergeTool）。
 * 该文件已在 tests/security/scan-dangerous-functions.php 白名单中登记。
 */
final class Subprocess
{
    private const OUTPUT_MAX_BYTES = 64 * 1024;

    /**
     * @param list<string> $argv 固定命令行；任何一段都不许含调用方原文
     *
     * @throws \RuntimeException 进程拉起失败
     */
    public function run(array $argv, string $cwd, int $timeoutSeconds): SubprocessOutcome
    {
        if ($argv === [] || $timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('argv 不能为空且超时必须为正数');
        }
        $started = microtime(true);
        $deadline = $started + $timeoutSeconds;
        // 数组形式绕过 shell：没有引号注入面
        $process = proc_open(
            $argv,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('子进程拉起失败');
        }
        /** @var array<int, resource> $pipes */
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $timedOut = false;
        while (true) {
            $output .= (string) stream_get_contents($pipes[1]);
            $output .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                usleep(300_000);
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                break;
            }
            usleep(50_000);
        }
        $output .= (string) stream_get_contents($pipes[1]);
        $output .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($timedOut) {
            $exitCode = null;
            $output .= "\n[超时中止：超过 {$timeoutSeconds}s]";
        }

        return new SubprocessOutcome(
            $exitCode,
            mb_strlen($output) > self::OUTPUT_MAX_BYTES
                ? mb_substr($output, -self::OUTPUT_MAX_BYTES)
                : $output,
            $timedOut,
            (int) round((microtime(true) - $started) * 1000),
        );
    }
}
