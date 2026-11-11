<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Db\Query;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\ListQuery;
use Sandadmin\Infra\Db\Query\Page;
use Sandadmin\Infra\Db\Query\SortDirection;

final class ListQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $query = ListQuery::fromArray([]);
        self::assertSame([1, 20, 0, []], [$query->page, $query->pageSize, $query->offset(), $query->sort]);
    }

    public function testParsesPageSortOrder(): void
    {
        $query = ListQuery::fromArray([
            'page' => '3', 'page_size' => '10', 'sort' => 'create_time', 'order' => 'desc',
            'keyword' => 'abc', 'status' => '1',
        ]);
        self::assertSame(20, $query->offset());
        self::assertCount(1, $query->sort);
        self::assertSame('create_time', $query->sort[0]->field);
        self::assertSame(SortDirection::Desc, $query->sort[0]->direction);
    }

    public function testSortDefaultsAscAndBlankSortIgnored(): void
    {
        $query = ListQuery::fromArray(['sort' => 'title']);
        self::assertSame(SortDirection::Asc, $query->sort[0]->direction);
        self::assertSame([], ListQuery::fromArray(['sort' => ''])->sort);
        self::assertSame([], ListQuery::fromArray(['sort' => '   '])->sort);
    }

    public function testPageSizeBoundary(): void
    {
        self::assertSame(100, ListQuery::fromArray(['page_size' => '100'])->pageSize);
        self::assertSame(1, ListQuery::fromArray(['page_size' => '1'])->pageSize);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalid(): iterable
    {
        yield 'page 0' => [['page' => '0']];
        yield 'page 非数字' => [['page' => '1abc']];
        yield 'page_size 0' => [['page_size' => '0']];
        yield 'page_size 超过上限' => [['page_size' => '101']];
        yield 'sort 非字符串' => [['sort' => ['x']]];
        yield 'order 非法' => [['sort' => 'id', 'order' => 'sideways']];
        yield 'order 非字符串' => [['sort' => 'id', 'order' => ['desc']]];
        yield '动态筛选 filters' => [['filters' => ['status' => ['eq' => '1']]]];
        yield '动态筛选 filter' => [['filter' => 'status']];
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
        $page = new Page([], 41, 2, 20);
        self::assertSame(3, $page->pageCount());
        self::assertSame(['items' => [], 'total' => 41, 'page' => 2, 'page_size' => 20], $page->jsonSerialize());
        self::assertSame(0, (new Page([], 0, 1, 20))->pageCount());
    }
}
