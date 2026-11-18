<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamEvent;

/**
 * SSE（text/event-stream）帧编码。id/event 字段不允许换行（剥离），
 * data 按行拆成多条 data: 行；心跳是单行注释（防代理按空闲断连）。
 */
final class SseFraming
{
    public static function frame(StreamEvent $event): string
    {
        $frame = '';
        if ($event->id !== null) {
            $frame .= 'id: ' . self::inline($event->id) . "\n";
        }
        if ($event->name !== null) {
            $frame .= 'event: ' . self::inline($event->name) . "\n";
        }
        $lines = preg_split('/\r\n|\r|\n/', $event->data);
        foreach ($lines === false ? [$event->data] : $lines as $line) {
            $frame .= 'data: ' . $line . "\n";
        }

        return $frame . "\n";
    }

    public static function heartbeat(): string
    {
        return ": heartbeat\n\n";
    }

    public static function error(string $code, string $message): string
    {
        $payload = json_encode(
            ['code' => $code, 'message' => $message],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return self::frame(new StreamEvent(null, 'error', $payload));
    }

    private static function inline(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
