<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

/**
 * 本地假 HTTP 服务：fork 子进程在 127.0.0.1:19100-19199 段监听，
 * 逐连接读请求、记日志到文件、按选项回固定响应。支持延迟响应、
 * 分块体、大响应体、前 N 个连接直接断开（制造传输错误）、
 * hang 模式（收完请求不回应，用于读/总超时用例）。
 * 仅供单测使用；父进程通过 requests() 读子进程落盘的请求记录。
 */
final class FakeHttpServer
{
    private int $pid = 0;

    private string $stopFile = '';

    private string $logFile = '';

    private function __construct(public readonly int $port)
    {
    }

    /**
     * @param array<string, mixed> $options 选项键：status(int|list<int>)、reason(array<int,string>)、
     *   body(string)、body_size(int)、delay(float 秒)、headers(array<string,string>)、
     *   chunked(bool)、close_empty(int 前 N 个连接直接断开)、hang(bool 收完请求不应答)
     */
    public static function start(array $options = []): self
    {
        $socket = null;
        $port = 0;
        for ($candidate = 19100; $candidate <= 19199; $candidate++) {
            $socket = @stream_socket_server('tcp://127.0.0.1:' . $candidate, $errno, $errstr);
            if ($socket !== false) {
                $port = $candidate;
                break;
            }
        }
        if ($socket === false) {
            throw new \RuntimeException('FakeHttpServer 无法在 19100-19199 段绑定端口');
        }

        $dir = sys_get_temp_dir();
        $stopFile = $dir . '/fake-http-' . bin2hex(random_bytes(6)) . '.stop';
        $logFile = $dir . '/fake-http-' . bin2hex(random_bytes(6)) . '.log';
        file_put_contents($logFile, '');

        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($socket);
            throw new \RuntimeException('FakeHttpServer fork 失败');
        }
        if ($pid === 0) {
            self::serve($socket, $options, $logFile, $stopFile);
            exit(0);
        }
        fclose($socket);

        $server = new self($port);
        $server->pid = $pid;
        $server->stopFile = $stopFile;
        $server->logFile = $logFile;

        return $server;
    }

    /**
     * 子进程：阻塞 accept 循环，stop 文件出现或 accept 空闲计数耗尽即退出。
     *
     * @param resource $socket
     * @param array<string, mixed> $options
     */
    private static function serve($socket, array $options, string $logFile, string $stopFile): void
    {
        $statuses = self::statusList($options['status'] ?? 200);
        $reasons = self::reasonMap($options['reason'] ?? null);
        $bodySize = isset($options['body_size']) && is_int($options['body_size']) ? $options['body_size'] : null;
        $rawBody = $options['body'] ?? null;
        $body = $bodySize !== null
            ? str_repeat('x', $bodySize)
            : (is_string($rawBody) ? $rawBody : 'ok');
        $delay = isset($options['delay']) && is_numeric($options['delay']) ? (float) $options['delay'] : 0.0;
        $headers = self::headerMap($options['headers'] ?? null);
        $chunked = (bool) ($options['chunked'] ?? false);
        $closeEmpty = isset($options['close_empty']) && is_int($options['close_empty']) ? $options['close_empty'] : 0;
        $hang = (bool) ($options['hang'] ?? false);

        $served = 0;
        $closed = 0;
        $idle = 0;
        while ($idle < 300 && !file_exists($stopFile)) {
            $conn = @stream_socket_accept($socket, 1);
            if ($conn === false) {
                $idle++;
                continue;
            }
            $idle = 0;
            stream_set_timeout($conn, 5);
            [$head, $payload] = self::readRequest($conn);
            file_put_contents(
                $logFile,
                "=== request ===\n" . $head . "\n-- body --\n" . $payload . "\n=== end ===\n",
                FILE_APPEND | LOCK_EX,
            );
            if ($closed < $closeEmpty) {
                $closed++;
                fclose($conn);
                continue;
            }
            if ($hang) {
                sleep(30);
                fclose($conn);
                continue;
            }
            if ($delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }
            $index = min($served, count($statuses) - 1);
            $served++;
            $status = $statuses[$index];
            self::writeResponse($conn, $status, $reasons[$index] ?? self::reasonOf($status), $headers, $body, $chunked);
            fclose($conn);
        }
        fclose($socket);
    }

    /**
     * @param resource $conn
     *
     * @return array{0: string, 1: string} 请求头原文与请求体
     */
    private static function readRequest($conn): array
    {
        $head = '';
        while (strlen($head) < 65536) {
            $chunk = fread($conn, 8192);
            if (!is_string($chunk) || $chunk === '') {
                break;
            }
            $head .= $chunk;
            if (str_contains($head, "\r\n\r\n")) {
                break;
            }
        }
        $payload = '';
        $split = strpos($head, "\r\n\r\n");
        if ($split !== false && preg_match('/content-length:\s*(\d+)/i', $head, $match) === 1) {
            $want = (int) $match[1];
            $payload = substr($head, $split + 4);
            $have = strlen($payload);
            while ($have < $want) {
                $chunk = fread($conn, max(1, min(8192, $want - $have)));
                if (!is_string($chunk) || $chunk === '') {
                    break;
                }
                $payload .= $chunk;
                $have += strlen($chunk);
            }
            $head = substr($head, 0, $split + 4);
        }

        return [$head, $payload];
    }

    /**
     * @param resource $conn
     * @param array<string, string> $headers
     */
    private static function writeResponse($conn, int $status, string $reason, array $headers, string $body, bool $chunked): void
    {
        $head = 'HTTP/1.1 ' . $status . ' ' . $reason . "\r\n";
        foreach ($headers as $name => $value) {
            $head .= $name . ': ' . $value . "\r\n";
        }
        if ($chunked) {
            $half = intdiv(strlen($body), 2);
            $first = substr($body, 0, $half);
            $second = substr($body, $half);
            $head .= "Transfer-Encoding: chunked\r\nConnection: close\r\n\r\n";
            fwrite($conn, $head);
            fwrite($conn, dechex(strlen($first)) . "\r\n" . $first . "\r\n");
            fwrite($conn, dechex(strlen($second)) . "\r\n" . $second . "\r\n0\r\n\r\n");

            return;
        }
        $head .= 'Content-Length: ' . strlen($body) . "\r\nConnection: close\r\n\r\n";
        fwrite($conn, $head . $body);
    }

    /** @return list<int> */
    private static function statusList(mixed $status): array
    {
        $list = is_array($status) ? $status : [$status];
        $out = [];
        foreach ($list as $item) {
            if (is_int($item)) {
                $out[] = $item;
            }
        }

        return $out === [] ? [200] : $out;
    }

    /** @return array<int, string> */
    private static function reasonMap(mixed $reasons): array
    {
        if (!is_array($reasons)) {
            return [];
        }
        $map = [];
        foreach ($reasons as $index => $reason) {
            if (is_int($index) && is_string($reason)) {
                $map[$index] = $reason;
            }
        }

        return $map;
    }

    /** @return array<string, string> */
    private static function headerMap(mixed $headers): array
    {
        if (!is_array($headers)) {
            return [];
        }
        $map = [];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $map[$name] = $value;
            }
        }

        return $map;
    }

    private static function reasonOf(int $status): string
    {
        return [
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            301 => 'Moved Permanently', 302 => 'Found', 304 => 'Not Modified',
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
            418 => 'Teapot', 429 => 'Too Many Requests',
            500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout',
        ][$status] ?? 'Status';
    }

    /** @return list<array{head: string, body: string}> 子进程记录的全部请求 */
    public function requests(): array
    {
        $raw = is_file($this->logFile) ? (string) file_get_contents($this->logFile) : '';
        $out = [];
        foreach (explode("=== request ===\n", $raw) as $block) {
            $end = strpos($block, '=== end ===');
            if ($end === false) {
                continue;
            }
            $block = substr($block, 0, $end);
            $parts = explode("\n-- body --\n", $block, 2);
            $body = $parts[1] ?? '';
            // 日志在 body 后固定补一个换行再接 === end ===，剥掉这一个分隔换行
            if (str_ends_with($body, "\n")) {
                $body = substr($body, 0, -1);
            }
            $out[] = [
                'head' => $parts[0],
                'body' => $body,
            ];
        }

        return $out;
    }

    public function requestCount(): int
    {
        return count($this->requests());
    }

    public function stop(): void
    {
        if ($this->stopFile !== '') {
            @touch($this->stopFile);
        }
        if ($this->pid > 0) {
            if (function_exists('posix_kill')) {
                @posix_kill($this->pid, SIGKILL);
            }
            pcntl_waitpid($this->pid, $status);
            $this->pid = 0;
        }
        foreach ([$this->stopFile, $this->logFile] as $file) {
            if ($file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
        $this->stopFile = '';
        $this->logFile = '';
    }

    public function __destruct()
    {
        $this->stop();
    }
}
