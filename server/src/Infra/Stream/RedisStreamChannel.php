<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamChannelInterface;
use Sandadmin\Domain\Stream\StreamEvent;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Redis pub/sub 扇出通道（J-5：任务进程写增量，流式进程只订阅扇出）。
 * 发布走 support\Redis 池化连接（一次短命令，无阻塞风险）；
 * 订阅走独立的 AsyncTcpConnection 自实现 RESP 协议——ext-redis 的
 * subscribe 是阻塞调用，放进事件循环会卡死整个 worker。
 * 断线按固定间隔重连并按订阅表全量补订；事件载荷是 StreamEvent 的 JSON。
 */
final class RedisStreamChannel implements StreamChannelInterface
{
    /** @var array<string, list<\Closure(StreamEvent): void>> */
    private array $subscribers = [];

    private ?AsyncTcpConnection $subscriber = null;

    private string $buffer = '';

    private ?string $lastError = null;

    /** @var null|\Closure(string, string): void */
    private readonly ?\Closure $publishTo;

    /**
     * @param null|\Closure(string, string): void $publishTo 发布出口替换点，
     *        默认走 support\Redis::publish；测试注入后完全不碰 Redis。
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly ?string $password = null,
        private readonly int $database = 0,
        private readonly string $keyPrefix = 'sand:stream:',
        ?\Closure $publishTo = null,
        private readonly float $reconnectSeconds = 3.0,
    ) {
        $this->publishTo = $publishTo;
    }

    public function publish(string $channel, StreamEvent $event): void
    {
        $key = $this->keyPrefix . $channel;
        $payload = $event->toPayload();
        if ($this->publishTo !== null) {
            ($this->publishTo)($key, $payload);

            return;
        }
        if (!class_exists(\support\Redis::class)) {
            throw new \RuntimeException('RedisStreamChannel 发布需要 support\\Redis，或注入 publishTo');
        }
        \support\Redis::publish($key, $payload);
    }

    public function subscribe(string $channel, \Closure $onEvent): void
    {
        $this->subscribers[$channel][] = $onEvent;
        if (count($this->subscribers[$channel]) > 1) {
            return;
        }
        $this->connect();
        if ($this->subscriber !== null) {
            $this->subscriber->send(self::command('SUBSCRIBE', $this->keyPrefix . $channel));
        }
    }

    public function unsubscribe(string $channel): void
    {
        if (!isset($this->subscribers[$channel])) {
            return;
        }
        unset($this->subscribers[$channel]);
        if ($this->subscriber !== null) {
            $this->subscriber->send(self::command('UNSUBSCRIBE', $this->keyPrefix . $channel));
        }
    }

    /** 最近一次 -ERR 帧内容；无错误为 null。观测用，不影响判定。 */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function connect(): void
    {
        if ($this->subscriber !== null) {
            return;
        }
        if (!class_exists(AsyncTcpConnection::class) || Worker::getAllWorkers() === []) {
            throw new \RuntimeException('RedisStreamChannel 订阅需要 Workerman 运行环境');
        }
        $conn = new AsyncTcpConnection('tcp://' . $this->host . ':' . $this->port);
        $conn->onConnect = function (AsyncTcpConnection $conn): void {
            if ($this->password !== null && $this->password !== '') {
                $conn->send(self::command('AUTH', $this->password));
            }
            if ($this->database > 0) {
                $conn->send(self::command('SELECT', (string) $this->database));
            }
            foreach (array_keys($this->subscribers) as $channel) {
                $conn->send(self::command('SUBSCRIBE', $this->keyPrefix . $channel));
            }
        };
        $conn->onMessage = function (AsyncTcpConnection $conn, string $data): void {
            $this->buffer .= $data;
            while (true) {
                try {
                    $frame = self::decode($this->buffer, 0);
                } catch (\RuntimeException) {
                    // 帧解析失同步无法恢复，断线由 onClose 重连重订阅
                    $conn->close();

                    return;
                }
                if ($frame === null) {
                    return;
                }
                $this->buffer = substr($this->buffer, $frame['bytes']);
                $this->dispatch($frame['value']);
            }
        };
        $conn->onClose = function (): void {
            $this->subscriber = null;
            $this->buffer = '';
            if ($this->subscribers !== []) {
                Timer::add($this->reconnectSeconds, function (): void {
                    $this->connect();
                }, [], false);
            }
        };
        $conn->connect();
        $this->subscriber = $conn;
    }

    private function dispatch(mixed $frame): void
    {
        if (is_array($frame) && isset($frame['error'])) {
            $this->lastError = is_string($frame['error']) ? $frame['error'] : 'redis error';

            return;
        }
        if (!is_array($frame) || ($frame[0] ?? null) !== 'message') {
            return;
        }
        $channel = $frame[1] ?? null;
        $payload = $frame[2] ?? null;
        if (!is_string($channel) || !is_string($payload) || !str_starts_with($channel, $this->keyPrefix)) {
            return;
        }
        $local = substr($channel, strlen($this->keyPrefix));
        $event = StreamEvent::fromPayload($payload);
        if ($event === null) {
            return;
        }
        foreach ($this->subscribers[$local] ?? [] as $onEvent) {
            $onEvent($event);
        }
    }

    private static function command(string ...$parts): string
    {
        $frame = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $frame .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        }

        return $frame;
    }

    /**
     * RESP2 增量解码：不足一整帧返回 null；不可识别帧型抛异常（由调用方决定重连）。
     *
     * @return null|array{value: mixed, bytes: int}
     */
    private static function decode(string $buffer, int $offset): ?array
    {
        $type = substr($buffer, $offset, 1);
        $lineEnd = strpos($buffer, "\r\n", $offset);
        if ($lineEnd === false) {
            return null;
        }
        $line = substr($buffer, $offset + 1, $lineEnd - $offset - 1);

        return match ($type) {
            '+' => ['value' => $line, 'bytes' => $lineEnd + 2 - $offset],
            '-' => ['value' => ['error' => $line], 'bytes' => $lineEnd + 2 - $offset],
            ':' => ['value' => (int) $line, 'bytes' => $lineEnd + 2 - $offset],
            '$' => self::decodeBulk($buffer, $offset, (int) $line, $lineEnd),
            '*' => self::decodeList($buffer, $offset, (int) $line, $lineEnd),
            default => throw new \RuntimeException('RESP 帧类型不可识别'),
        };
    }

    /** @return null|array{value: mixed, bytes: int} */
    private static function decodeBulk(string $buffer, int $offset, int $length, int $lineEnd): ?array
    {
        if ($length === -1) {
            return ['value' => null, 'bytes' => $lineEnd + 2 - $offset];
        }
        $start = $lineEnd + 2;
        if (strlen($buffer) < $start + $length + 2) {
            return null;
        }

        return ['value' => substr($buffer, $start, $length), 'bytes' => $start + $length + 2 - $offset];
    }

    /** @return null|array{value: mixed, bytes: int} */
    private static function decodeList(string $buffer, int $offset, int $count, int $lineEnd): ?array
    {
        $values = [];
        $cursor = $lineEnd + 2;
        for ($index = 0; $index < $count; $index++) {
            $item = self::decode($buffer, $cursor);
            if ($item === null) {
                return null;
            }
            $values[] = $item['value'];
            $cursor += $item['bytes'];
        }

        return ['value' => $values, 'bytes' => $cursor - $offset];
    }
}
