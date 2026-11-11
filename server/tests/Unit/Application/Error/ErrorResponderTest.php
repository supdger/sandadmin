<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Error;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Exception\DomainException;
use Sandadmin\Domain\Exception\SessionExpiredException;
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

    public function testMapsInvalidQueryFieldErrorsToValidationFailed(): void
    {
        $body = $this->responder->respond(
            new InvalidQueryException('page 必须 ≥ 1', ['page' => '不能小于 1', 'sort' => '只能是登记过的字段']),
            'req-12345678',
        );

        self::assertSame(422, $body->status);
        self::assertSame('VALIDATION_FAILED', $body->code);
        self::assertSame('page 必须 ≥ 1', $body->message);
        self::assertSame(['page' => ['不能小于 1'], 'sort' => ['只能是登记过的字段']], $body->fields);
        self::assertSame('req-12345678', $body->requestId);
        self::assertSame([], $this->logger->entries);
    }

    public function testMapsDomainExceptionWithoutLoggingClientErrors(): void
    {
        $body = $this->responder->respond(new UnauthenticatedException(), 'req-12345678');

        self::assertSame(401, $body->status);
        self::assertSame('UNAUTHENTICATED', $body->code);
        self::assertSame('请先登录', $body->message);
        self::assertSame([], $body->fields);
        self::assertSame([], $this->logger->entries);
    }

    public function testMapsExpiredSessionWithoutLogging(): void
    {
        $body = $this->responder->respond(new SessionExpiredException(), 'req-12345678');

        self::assertSame(401, $body->status);
        self::assertSame('SESSION_EXPIRED', $body->code);
        self::assertSame('登录已过期，请重新登录', $body->message);
        self::assertSame([], $this->logger->entries);
    }

    public function testHidesUnexpectedExceptionDetailsAndLogsThem(): void
    {
        $body = $this->responder->respond(new \RuntimeException('db password is secret'), 'req-12345678');

        self::assertSame(500, $body->status);
        self::assertSame('INTERNAL_ERROR', $body->code);
        self::assertSame('系统出了点问题，请稍后再试', $body->message);
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
        $error = new class ('更新源暂时不可用') extends DomainException {
            public function __construct(string $message)
            {
                parent::__construct($message, 'UPDATE_CHECK_FAILED', [], 500);
            }
        };
        $body = $this->responder->respond($error, 'req-12345678');

        self::assertSame(500, $body->status);
        self::assertSame('UPDATE_CHECK_FAILED', $body->code);
        self::assertSame('更新源暂时不可用', $body->message);
        self::assertCount(1, $this->logger->entries);
    }

    public function testRejectsLowercaseErrorCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new class ('x', 'invalid_query') extends DomainException {
        };
    }
}
