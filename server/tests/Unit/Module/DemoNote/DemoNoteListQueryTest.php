<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\DemoNote;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\SortDirection;
use Sandadmin\Module\DemoNote\DemoNoteListQuery;

final class DemoNoteListQueryTest extends TestCase
{
    public function testParsesNamedParams(): void
    {
        $q = DemoNoteListQuery::fromArray([
            'page' => '2', 'page_size' => '5', 'sort' => 'id', 'order' => 'desc',
            'keyword' => '  abc  ', 'status' => '2',
            'create_time_from' => '2026-01-01T00:00:00+08:00',
            'create_time_to' => '2026-01-31T23:59:59+08:00',
        ]);
        self::assertSame([2, 5, 5], [$q->page, $q->pageSize, $q->offset()]);
        self::assertSame(SortDirection::Desc, $q->sort[0]->direction);
        self::assertSame('abc', $q->keyword);
        self::assertSame(2, $q->status);
        self::assertSame('2026-01-01', $q->createTimeFrom?->format('Y-m-d'));
        self::assertSame('2026-01-31', $q->createTimeTo?->format('Y-m-d'));
    }

    public function testBlankParamsBecomeNull(): void
    {
        $q = DemoNoteListQuery::fromArray(['keyword' => '   ', 'status' => '']);
        self::assertNull($q->keyword);
        self::assertNull($q->status);
    }

    /** @return iterable<string, array{array<mixed>, string}> */
    public static function invalid(): iterable
    {
        yield 'keyword 非字符串' => [['keyword' => ['x']], 'keyword'];
        yield 'status 非整数' => [['status' => 'abc'], 'status'];
        yield 'status 负数' => [['status' => '-1'], 'status'];
        yield 'create_time_from 非时间' => [['create_time_from' => 'not-a-date'], 'create_time_from'];
        yield 'create_time_to 非字符串' => [['create_time_to' => ['x']], 'create_time_to'];
    }

    /** @param array<mixed> $input */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function testRejectsInvalid(array $input, string $field): void
    {
        try {
            DemoNoteListQuery::fromArray($input);
            self::fail('应抛出 InvalidQueryException');
        } catch (InvalidQueryException $e) {
            self::assertArrayHasKey($field, $e->fields);
        }
    }
}
