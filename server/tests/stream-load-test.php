#!/usr/bin/env php
<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

/**
 * 流式基础层开发期压测（不进生产包，tests 目录整体剔除）。
 *
 * 验证两件事（J-5）：
 *   1. 单 web 进程同时挂 N 路 SSE（默认 100），路由层不阻塞分发；
 *   2. 慢上游（默认 30 秒静默窗口）挂着期间，普通快请求延迟不退化。
 *
 * 用法：
 *   php tests/stream-load-test.php [--streams=100] [--slow-ms=30000]
 *                                  [--port=18977] [--external=BASE_URL]
 *                                  [--samples=12] [--baseline=10]
 * 默认自己起一台单 worker 的 dev 服务（webman start -d），跑完停掉；
 * --external 指向已跑起来的实例则只测不管生命周期。
 * 退出码 0 = 全部断言过；数字结果打印在末尾 REPORT 段。
 */

$options = getopt('', ['streams::', 'slow-ms::', 'port::', 'external::', 'samples::', 'baseline::']);
$streams = max(1, (int) ($options['streams'] ?? 100));
$slowMs = max(1000, (int) ($options['slow-ms'] ?? 30_000));
$port = (int) ($options['port'] ?? 18977);
$samples = max(2, (int) ($options['samples'] ?? 12));
$baselineCount = max(2, (int) ($options['baseline'] ?? 10));
$external = $options['external'] ?? null;
$base = is_string($external) ? rtrim($external, '/') : "http://127.0.0.1:{$port}";
$serverRoot = dirname(__DIR__);

$startedHere = false;

/** @return array{0: int, 1: float, 2: string} [http code, 毫秒, body] */
function ping(string $base): array
{
    $started = microtime(true);
    $context = stream_context_create(['http' => ['timeout' => 5.0, 'ignore_errors' => true]]);
    $body = file_get_contents($base . '/api/dev/stream/ping', false, $context);
    $elapsed = (microtime(true) - $started) * 1000.0;
    $code = 0;
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m) === 1) {
            $code = (int) $m[1];
        }
    }

    return [$code, $elapsed, is_string($body) ? $body : ''];
}

function pingStreams(string $base): ?int
{
    [$code, , $body] = ping($base);
    if ($code !== 200) {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }
    $inner = $data['data'] ?? null;
    if (!is_array($inner)) {
        return null;
    }
    $streams = $inner['streams'] ?? null;

    return is_int($streams) ? $streams : null;
}

/**
 * @param list<float> $latencies
 * @return array{p50: float, p95: float, max: float}
 */
function stats(array $latencies): array
{
    sort($latencies);
    $count = count($latencies);
    $pick = static fn (float $rank): float => $latencies[min($count - 1, (int) floor($rank * $count))];

    return ['p50' => $pick(0.5), 'p95' => $pick(0.95), 'max' => $latencies[$count - 1]];
}

function fail(string $message): never
{
    fwrite(STDERR, "FAIL {$message}\n");
    exit(1);
}

if ($external === null) {
    // 单 worker + 放宽挂路数/空闲上限：压测要 100 路与 30 秒静默窗
    $env = 'SANDADMIN_SERVER_PORT=' . $port
        . ' SANDADMIN_WEB_WORKERS=1'
        . ' SANDADMIN_STREAM_LIMIT=' . ($streams + 20)
        . ' SANDADMIN_STREAM_IDLE_SECONDS=' . ((int) ceil($slowMs / 1000) + 20);
    $cmd = "cd " . escapeshellarg($serverRoot) . " && {$env} php webman start -d 2>&1";
    exec($cmd, $startOut, $startCode);
    if ($startCode !== 0) {
        fail('dev 服务启动失败：' . implode("\n", $startOut));
    }
    $startedHere = true;
    $code = 0;
    $deadline = microtime(true) + 15;
    while (microtime(true) < $deadline) {
        [$code] = ping($base);
        if ($code === 200) {
            break;
        }
        usleep(200_000);
    }
    if ($code !== 200) {
        fail("dev 服务 15 秒内未就绪（{$base}）");
    }
    echo "dev 服务就绪 {$base}（单 worker，流上限 " . ($streams + 20) . "）\n";
} else {
    echo "外接实例 {$base}\n";
}

$stop = static function () use ($serverRoot, $startedHere): void {
    if ($startedHere) {
        exec('cd ' . escapeshellarg($serverRoot) . ' && php webman stop 2>&1');
    }
};
register_shutdown_function($stop);

// ── 基线：无流时 ping 延迟 ─────────────────────────────────
$baseline = [];
for ($i = 0; $i < $baselineCount; $i++) {
    [$code, $ms] = ping($base);
    if ($code !== 200) {
        fail('基线 ping 失败 code=' . $code);
    }
    $baseline[] = $ms;
    usleep(50_000);
}

// ── 开 N 路慢上游 SSE ─────────────────────────────────────
$sockets = [];
$host = parse_url($base, PHP_URL_HOST);
$targetPort = (int) (parse_url($base, PHP_URL_PORT) ?? 80);
for ($i = 0; $i < $streams; $i++) {
    $socket = stream_socket_client("tcp://{$host}:{$targetPort}", $errno, $errstr, 3.0);
    if ($socket === false) {
        fail("第 {$i} 路连接失败：{$errstr}");
    }
    $query = '/api/dev/stream/sse?interval_ms=1000&slow_ms=' . $slowMs;
    $written = fwrite($socket, "GET {$query} HTTP/1.1\r\nHost: {$host}\r\nAccept: text/event-stream\r\n\r\n");
    if ($written === false) {
        fail("第 {$i} 路写入失败");
    }
    stream_set_blocking($socket, false);
    $sockets[] = $socket;
}

$held = 0;
$deadline = microtime(true) + 10;
while (microtime(true) < $deadline) {
    $held = pingStreams($base) ?? 0;
    if ($held >= $streams) {
        break;
    }
    usleep(200_000);
}
if ($held < $streams) {
    fail("挂路数不到位：期望 {$streams}，实际 {$held}");
}
echo "已挂 {$held}/{$streams} 路慢上游流\n";

// ── 慢上游窗口内：普通快请求延迟 ───────────────────────────
$loaded = [];
$windowStart = microtime(true);
while (microtime(true) - $windowStart < $slowMs / 1000 - 3) {
    [$code, $ms] = ping($base);
    if ($code === 200) {
        $loaded[] = $ms;
    }
    usleep(800_000);
}
$heldAfter = pingStreams($base) ?? 0;

// ── 慢上游窗口结束后：确认事件真的流出来 ───────────────────
$waitUntil = microtime(true) + 6;
$streamBytes = '';
while (microtime(true) < $waitUntil) {
    foreach ($sockets as $socket) {
        $chunk = fread($socket, 8192);
        if (is_string($chunk)) {
            $streamBytes .= $chunk;
        }
    }
    if (str_contains($streamBytes, 'event: demo')) {
        break;
    }
    usleep(200_000);
}
$firstEventAt = str_contains($streamBytes, 'event: demo') ? '有 demo 事件落流' : '未见 demo 事件';

foreach ($sockets as $socket) {
    fclose($socket);
}

$baseStats = stats($baseline);
$loadStats = stats($loaded);

echo "\nREPORT\n";
echo "  streams_held_peak={$held} streams_held_after_window={$heldAfter}\n";
echo '  slow_ms=' . $slowMs . ' slow_upstream_evidence=' . $firstEventAt . "\n";
printf("  baseline_ping_ms p50=%.1f p95=%.1f max=%.1f (n=%d)\n", $baseStats['p50'], $baseStats['p95'], $baseStats['max'], count($baseline));
printf("  loaded_ping_ms   p50=%.1f p95=%.1f max=%.1f (n=%d)\n", $loadStats['p50'], $loadStats['p95'], $loadStats['max'], count($loaded));

$verdict = $held >= $streams && $heldAfter >= $streams && str_contains($firstEventAt, '有') && $loadStats['p95'] < $baseStats['p95'] * 3 + 20;
echo $verdict ? "PASS\n" : "FAIL\n";
exit($verdict ? 0 : 1);
