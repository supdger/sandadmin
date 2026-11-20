<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Http;

use Sandadmin\Domain\Http\OutboundException;
use Sandadmin\Domain\Http\OutboundHttpClientInterface;
use Sandadmin\Domain\Http\OutboundRequest;
use Sandadmin\Domain\Http\OutboundResponse;
use Sandadmin\Domain\Http\TraceContext;
use Sandadmin\Domain\Http\TraceContextResolver;

/**
 * 统一出站 HTTP 客户端（curl 单句柄同步实现）。
 * 唯一允许触碰 socket 原语的位置：超时三段分开（connect / 读停滞 / total）、
 * 不跟随重定向、响应体按 max_body_bytes 截断标记、SSRF 闸门先行且
 * DNS 结果用 CURLOPT_RESOLVE 钉住防 rebinding。重试只覆盖传输错误与
 * 配置的 retry_statuses，非幂等方法默认不重试。
 */
final class CurlOutboundClient implements OutboundHttpClientInterface
{
    private const DEFAULT_MAX_BODY_BYTES = 10 * 1024 * 1024;

    private readonly OutboundAddressGate $gate;

    private readonly TraceContextResolver $traces;

    /** @var \Closure(int): void */
    private readonly \Closure $sleeper;

    /** @var \Closure(int): int */
    private readonly \Closure $jitter;

    private readonly int $connectTimeoutMs;

    private readonly int $readTimeoutMs;

    private readonly int $totalTimeoutMs;

    private readonly int $maxAttempts;

    private readonly int $backoffBaseMs;

    private readonly int $backoffMaxMs;

    private readonly int $maxBodyBytes;

    /** @var list<int> */
    private readonly array $retryStatuses;

    /**
     * @param array<string, mixed> $config outbound.php 配置数组，键缺失回落默认值
     * @param null|\Closure(int): void $sleeper 重试退避睡眠替身（微秒入参），测试注入
     * @param null|\Closure(int): int $jitter 抖动替身：入参上限毫秒，返回 [0, 上限]
     */
    public function __construct(
        array $config = [],
        ?OutboundAddressGate $gate = null,
        ?TraceContextResolver $traces = null,
        ?\Closure $sleeper = null,
        ?\Closure $jitter = null,
    ) {
        $this->gate = $gate ?? new OutboundAddressGate(self::stringList($config, 'allow_private_hosts'));
        $this->traces = $traces ?? new RequestTraceResolver();
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
        $this->jitter = $jitter ?? static function (int $max): int {
            return $max > 0 ? random_int(0, $max) : 0;
        };
        $this->connectTimeoutMs = self::intOption($config, 'connect_timeout_ms', 3000);
        $this->readTimeoutMs = self::intOption($config, 'read_timeout_ms', 10000);
        $this->totalTimeoutMs = self::intOption($config, 'total_timeout_ms', 15000);
        $this->maxAttempts = max(1, self::intOption($config, 'max_attempts', 3));
        $this->backoffBaseMs = max(0, self::intOption($config, 'backoff_base_ms', 200));
        $this->backoffMaxMs = max($this->backoffBaseMs, self::intOption($config, 'backoff_max_ms', 2000));
        $this->maxBodyBytes = max(1, self::intOption($config, 'max_body_bytes', self::DEFAULT_MAX_BODY_BYTES));
        $this->retryStatuses = self::intList($config, 'retry_statuses', [502, 503, 504]);
    }

    /**
     * 生产默认装配：读 outbound 配置，config() 不可用（CLI 裸跑）时用内置默认。
     *
     * @param array<string, mixed> $overrides 覆盖配置项（如探测专用的超时口径）
     */
    public static function fromConfig(array $overrides = []): self
    {
        $raw = function_exists('config') ? config('outbound') : null;
        /** @var array<string, mixed> $config */
        $config = is_array($raw) ? $raw : [];
        foreach ($overrides as $key => $value) {
            $config[$key] = $value;
        }

        return new self($config);
    }

    public function request(OutboundRequest $request): OutboundResponse
    {
        // SSRF 闸门先于一切重试：每个 attempt 共用同一份钉住地址
        $ips = $this->gate->resolve($request->url);
        $attempts = max(1, $request->maxAttempts ?? $this->maxAttempts);
        $allowRetry = $request->retry ?? $request->isIdempotent();
        $last = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->perform($request, $ips);
            } catch (OutboundException $error) {
                $last = $error;
                if (!$allowRetry || $attempt >= $attempts) {
                    break;
                }
                $this->pause($this->backoffUs($attempt - 1));
                continue;
            }
            if ($allowRetry && $attempt < $attempts
                && !$response->truncated
                && in_array($response->status, $this->retryStatuses, true)) {
                $this->pause($this->backoffUs($attempt - 1));
                continue;
            }

            return $response;
        }

        throw $last ?? new OutboundException('出站请求失败', 'transport');
    }

    /**
     * @param list<string> $ips 闸门解析出的地址，用于 CURLOPT_RESOLVE 钉住
     */
    private function perform(OutboundRequest $request, array $ips): OutboundResponse
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new OutboundException('无法初始化出站请求', 'transport');
        }

        $status = 0;
        /** @var array<string, list<string>> $headers */
        $headers = [];
        $body = '';
        $received = 0;
        $overflow = false;
        $maxBody = $this->maxBodyBytes;

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $this->headerLines($request),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $this->totalTimeoutMs,
            // 读超时按「停滞」判定：任意方向低于 1B/s 持续 read_timeout 即中止。
            // LOW_SPEED_TIME 只接受整秒，毫秒值向上取整；<=0 时关闭停滞判定。
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$status, &$headers): int {
                return self::collectHeader($line, $status, $headers);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $data) use (&$body, &$received, &$overflow, $maxBody): int {
                $length = strlen($data);
                if ($received + $length > $maxBody) {
                    $overflow = true;

                    return 0;
                }
                $body .= $data;
                $received += $length;

                return $length;
            },
        ];
        if ($this->readTimeoutMs > 0) {
            $options[CURLOPT_LOW_SPEED_LIMIT] = 1;
            $options[CURLOPT_LOW_SPEED_TIME] = max(1, (int) ceil($this->readTimeoutMs / 1000));
        }
        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }
        $resolve = $this->resolveEntries($request->url, $ips);
        if ($resolve !== null) {
            $options[CURLOPT_RESOLVE] = [$resolve];
        }
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        foreach ($options as $option => $value) {
            curl_setopt($handle, $option, $value);
        }
        $ok = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($overflow) {
            // 体超限主动中止（CURLE_WRITE_ERROR）：状态与头已收齐，按截断响应返回
            return new OutboundResponse($status, $headers, $body, true);
        }
        if ($ok === false || $errno !== 0) {
            $kind = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'transport';
            throw new OutboundException(
                '出站请求失败：' . ($error !== '' ? $error : 'curl errno ' . $errno),
                $kind,
            );
        }

        return new OutboundResponse($status, $headers, $body);
    }

    /** @return list<string> "Name: value" 形式的请求头，调用方显式头覆盖追踪默认头 */
    private function headerLines(OutboundRequest $request): array
    {
        $trace = $this->traces->resolve();
        if ($trace === null) {
            $trace = TraceContext::generate($this->traces->requestId());
        }
        $merged = ['traceparent' => $trace->traceparent(), 'x-request-id' => $trace->requestId];
        if ($trace->tracestate !== null) {
            $merged['tracestate'] = $trace->tracestate;
        }
        foreach ($request->headers as $name => $value) {
            foreach (array_keys($merged) as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    unset($merged[$existing]);
                }
            }
            $merged[$name] = $value;
        }
        $lines = [];
        foreach ($merged as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    /**
     * 主机名请求把闸门解析到的地址钉进 CURLOPT_RESOLVE，
     * 让 curl 不再自行解析，堵住检查与连接之间的 DNS rebinding。
     * IP 字面量不需要钉（本来就不解析）。
     *
     * @param list<string> $ips
     */
    private function resolveEntries(string $url, array $ips): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!is_string($host) || $host === '' || filter_var(self::unbracket($host), FILTER_VALIDATE_IP) !== false) {
            return null;
        }
        if (!is_int($port)) {
            $port = $scheme === 'https' ? 443 : 80;
        }
        $addresses = [];
        foreach ($ips as $ip) {
            $addresses[] = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        }

        return self::unbracket($host) . ':' . $port . ':' . implode(',', $addresses);
    }

    private static function unbracket(string $host): string
    {
        return strlen($host) > 2 && $host[0] === '[' && str_ends_with($host, ']')
            ? substr($host, 1, -1)
            : $host;
    }

    /** 退避毫秒 = min(base * 2^retryIndex, max) + [0, base] 抖动，返回微秒。 */
    private function backoffUs(int $retryIndex): int
    {
        $delayMs = $this->backoffBaseMs * (2 ** min($retryIndex, 10));
        if ($delayMs > $this->backoffMaxMs) {
            $delayMs = $this->backoffMaxMs;
        }
        $delayMs += ($this->jitter)($this->backoffBaseMs);

        return $delayMs * 1000;
    }

    private function pause(int $microseconds): void
    {
        if ($microseconds > 0) {
            ($this->sleeper)($microseconds);
        }
    }

    /**
     * HEADERFUNCTION 收一个头部行：状态行取最后一个 HTTP 状态码，
     * 普通头按小写名聚合成 list；行尾 \r\n 由 curl 保证。
     *
     * @param array<string, list<string>> $headers
     */
    private static function collectHeader(string $line, int &$status, array &$headers): int
    {
        $length = strlen($line);
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            return $length;
        }
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];

            return $length;
        }
        $pos = strpos($line, ':');
        if ($pos === false || $pos === 0) {
            return $length;
        }
        $name = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));
        $headers[$name][] = $value;

        return $length;
    }

    /** @param array<string, mixed> $config */
    private static function intOption(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<int> $default
     *
     * @return list<int>
     */
    private static function intList(array $config, string $key, array $default): array
    {
        $value = $config[$key] ?? null;
        if (!is_array($value)) {
            return $default;
        }
        $list = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $list[] = $item;
            } elseif (is_string($item) && is_numeric($item)) {
                $list[] = (int) $item;
            }
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private static function stringList(array $config, string $key): array
    {
        $value = $config[$key] ?? null;
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = trim($item);
            }
        }

        return $list;
    }
}
