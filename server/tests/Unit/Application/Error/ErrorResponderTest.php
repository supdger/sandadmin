<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Error;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Exception\DomainException;
use Sandadmin\Domain\Exception\UnauthenticatedException;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Tests\Support\RecordingErrorLogger;

final class ErrorResponderTest extends TestCase
{
    private RecordingErrorLogger $logger;

    private ErrorResponder $responder;

    protected function setUp(): void
    {
        $this->logger = new RecordingErrorLogger();
        $this->responder = new ErrorResponder($this->logger);
    }

    public function testMapsInvalidQueryFieldErrors(): void
    {
        $body = $this->responder->respond(
            new InvalidQueryException('page 必须 ≥ 1', ['page' => 'min:1', 'sort' => 'string']),
            'req-12345678',
        );

        self::assertSame(422, $body->status);
        self::assertSame('invalid_query', $body->code);
        self::assertSame('page 必须 ≥ 1', $body->message);
        self::assertSame(['page' => 'min:1', 'sort' => 'string'], $body->fields);
        self::assertSame('req-12345678', $body->requestId);
        self::assertSame([], $this->logger->entries);
    }

    public function testMapsDomainExceptionWithoutLoggingClientErrors(): void
    {
        $body = $this->responder->respond(new UnauthenticatedException(), 'req-12345678');

        self::assertSame(401, $body->status);
        self::assertSame('unauthenticated', $body->code);
        self::assertSame('未登录', $body->message);
        self::assertSame([], $body->fields);
        self::assertSame([], $this->logger->entries);
    }

    public function testHidesUnexpectedExceptionDetailsAndLogsThem(): void
    {
        $body = $this->responder->respond(new \RuntimeException('db password is secret'), 'req-12345678');

        self::assertSame(500, $body->status);
        self::assertSame('internal_error', $body->code);
        self::assertSame('服务器内部错误', $body->message);
        self::assertSame([], $body->fields);
        self::assertSame([
            [
                'requestId' => 'req-12345678',
                'class' => \RuntimeException::class,
                'message' => 'db password is secret',
            ],
        ], $this->logger->entries);
    }

    public function testLogsServerSideDomainErrorsButKeepsTheirMessage(): void
    {
        $error = new class ('依赖不可用') extends DomainException {
            public function __construct(string $message)
            {
                parent::__construct($message, 'unavailable', [], 503);
            }
        };
        $body = $this->responder->respond($error, 'req-12345678');

        self::assertSame(503, $body->status);
        self::assertSame('unavailable', $body->code);
        self::assertSame('依赖不可用', $body->message);
        self::assertCount(1, $this->logger->entries);
    }
}
