<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Db\Query;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\FilterOperator;
use Sandadmin\Infra\Db\Query\ListQuery;
use Sandadmin\Infra\Db\Query\Page;
use Sandadmin\Infra\Db\Query\SortDirection;

final class ListQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $q = ListQuery::fromArray([]);
        self::assertSame([1, 20, 0, [], []], [$q->page, $q->pageSize, $q->offset(), $q->sort, $q->filters]);
    }

    public function testParsesFullQuery(): void
    {
        $q = ListQuery::fromArray([
            'page' => '3', 'page_size' => '10', 'sort' => '-created_at, id',
            'filters' => ['status' => ['eq' => '1'], 'id' => ['in' => '1,2'], 'title' => ['like' => 'a', 'notnull' => '']],
        ]);
        self::assertSame(20, $q->offset());
        self::assertSame('created_at', $q->sort[0]->field);
        self::assertSame(SortDirection::Desc, $q->sort[0]->direction);
        self::assertSame(SortDirection::Asc, $q->sort[1]->direction);
        self::assertCount(4, $q->filters);
        self::assertSame(['1', '2'], $q->filters[1]->value);
        self::assertSame(FilterOperator::NotNull, $q->filters[3]->operator);
        self::assertNull($q->filters[3]->value);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalid(): iterable
    {
        yield 'page 0' => [['page' => '0']];
        yield 'page 非数字' => [['page' => '1abc']];
        yield 'page_size 过大' => [['page_size' => '201']];
        yield 'sort 非字符串' => [['sort' => ['x']]];
        yield 'filters 非数组' => [['filters' => 'x']];
        yield '未知操作符' => [['filters' => ['a' => ['drop' => '1']]]];
        yield 'in 空' => [['filters' => ['a' => ['in' => []]]]];
        yield '值非标量' => [['filters' => ['a' => ['eq' => ['x']]]]];
    }

    /** @param array<mixed> $input */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function testRejectsInvalid(array $input): void
    {
        $this->expectException(InvalidQueryException::class);
        ListQuery::fromArray($input);
    }

    public function testPageJsonShape(): void
    {
        $p = new Page([], 41, 2, 20);
        self::assertSame(3, $p->pageCount());
        self::assertSame(['items' => [], 'total' => 41, 'page' => 2, 'page_size' => 20], $p->jsonSerialize());
        self::assertSame(0, (new Page([], 0, 1, 20))->pageCount());
    }
}