<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\File\FileActor;
use Sandadmin\Domain\File\FileException;

final class FileActorTest extends TestCase
{
    public function testCanMatchesExactPermission(): void
    {
        $actor = new FileActor(7, ['system:file:index', 'system:file:delete']);
        self::assertTrue($actor->can('system:file:index'));
        self::assertTrue($actor->can('system:file:delete'));
        self::assertFalse($actor->can('system:file:cleanup'));
    }

    public function testStarGrantsEverything(): void
    {
        $actor = new FileActor(1, ['*']);
        self::assertTrue($actor->can('system:file:cleanup'));
        self::assertTrue($actor->can('anything:at:all'));
    }

    public function testRejectsNonPositiveUserId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FileActor(0, []);
    }

    public function testExceptionCodesAreUpperSnake(): void
    {
        self::assertSame(413, FileException::tooLarge()->status);
        self::assertSame('FILE_TOO_LARGE', FileException::tooLarge()->errorCode);
        self::assertSame(422, FileException::typeNotAllowed()->status);
        self::assertSame('FILE_TYPE_NOT_ALLOWED', FileException::typeNotAllowed()->errorCode);
        self::assertSame(404, FileException::notFound()->status);
        self::assertSame('FILE_NOT_FOUND', FileException::notFound()->errorCode);
        self::assertSame(409, FileException::idempotencyReused()->status);
        self::assertSame('IDEMPOTENCY_KEY_REUSED', FileException::idempotencyReused()->errorCode);
        self::assertSame(401, FileException::unauthenticated()->status);
        self::assertSame('UNAUTHENTICATED', FileException::unauthenticated()->errorCode);
        self::assertSame(403, FileException::denied()->status);
        self::assertSame('PERMISSION_DENIED', FileException::denied()->errorCode);
    }
}
