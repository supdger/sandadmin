<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Stream\RedisStreamChannel;

/** RESP 解码的长度与层数上限：对端发超大或畸形帧时抛异常，由调用方断线重连。 */
final class RedisStreamChannelDecodeTest extends TestCase
{
    /** @return null|array{value: mixed, bytes: int} */
    private function decode(string $buffer): ?array
    {
        $method = new \ReflectionMethod(RedisStreamChannel::class, 'decode');

        /** @var null|array{value: mixed, bytes: int} */
        return $method->invoke(null, $buffer, 0);
    }

    public function testDecodesSubscribeMessage(): void
    {
        $frame = $this->decode("*3\r\n\$7\r\nmessage\r\n\$3\r\nabc\r\n\$2\r\nhi\r\n");
        self::assertNotNull($frame);
        self::assertSame(['message', 'abc', 'hi'], $frame['value']);
    }

    public function testIncompleteFrameReturnsNull(): void
    {
        self::assertNull($this->decode("\$5\r\nab"));
    }

    public function testOversizedBulkThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->decode('$' . (RedisStreamChannel::MAX_BULK_BYTES + 1) . "\r\n");
    }

    public function testNegativeBulkLengthThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->decode("\$-5\r\n");
    }

    public function testOversizedListThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->decode('*' . (RedisStreamChannel::MAX_LIST_ITEMS + 1) . "\r\n");
    }

    public function testTooDeepNestingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->decode(str_repeat("*1\r\n", RedisStreamChannel::MAX_DEPTH + 2) . ":1\r\n");
    }
}
