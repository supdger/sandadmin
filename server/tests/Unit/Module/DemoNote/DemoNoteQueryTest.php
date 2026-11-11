<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\DemoNote;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\SortDirection;
use Sandadmin\Module\DemoNote\DemoNoteQuery;

final class DemoNoteQueryTest extends TestCase
{
    public function testParsesNamedParams(): void
    {
        $query = DemoNoteQuery::fromArray([
            'page' => '2', 'page_size' => '5', 'sort' => 'id', 'order' => 'desc',
            'keyword' => '  abc  ', 'status' => '2',
            'create_time_from' => '2026-01-01T00:00:00+08:00',
            'create_time_to' => '2026-01-31T23:59:59+08:00',
        ]);
        self::assertSame([2, 5, 5], [$query->page, $query->pageSize, $query->offset()]);
        self::assertSame(SortDirection::Desc, $query->sort[0]->direction);
        self::assertSame('abc', $query->keyword);
        self::assertSame(2, $query->status);
        self::assertSame('2026-01-01', $query->createTimeFrom?->format('Y-m-d'));
        self::assertSame('2026-01-31', $query->createTimeTo?->format('Y-m-d'));
    }

    public function testBlankParamsBecomeNull(): void
    {
        $query = DemoNoteQuery::fromArray(['keyword' => '   ', 'status' => '']);
        self::assertNull($query->keyword);
        self::assertNull($query->status);
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
            DemoNoteQuery::fromArray($input);
            self::fail('应抛出 InvalidQueryException');
        } catch (InvalidQueryException $e) {
            self::assertArrayHasKey($field, $e->fields);
        }
    }
}
