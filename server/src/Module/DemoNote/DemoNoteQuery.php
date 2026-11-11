<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\DemoNote;

use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\ListQuery;

/**
 * 列表查询参数对象的演示：分页与排序走基类，具名筛选参数在此显式声明。
 *
 * 对应接口参数：keyword（title 前缀模糊）、status（等值）、
 * create_time_from / create_time_to（时间范围，含端点）。
 */
final readonly class DemoNoteQuery extends ListQuery
{
    /**
     * @param list<\Sandadmin\Infra\Db\Query\Sort> $sort
     */
    public function __construct(
        int $page = 1,
        int $pageSize = ListQuery::DEFAULT_PAGE_SIZE,
        array $sort = [],
        public ?string $keyword = null,
        public ?int $status = null,
        public ?\DateTimeImmutable $createTimeFrom = null,
        public ?\DateTimeImmutable $createTimeTo = null,
    ) {
        parent::__construct($page, $pageSize, $sort);
    }

    /** @param array<mixed> $query 原始查询参数（如控制器传入的请求 query 数组） */
    public static function fromArray(array $query): self
    {
        $base = ListQuery::fromArray($query);

        return new self(
            $base->page,
            $base->pageSize,
            $base->sort,
            keyword: self::parseString($query['keyword'] ?? null, 'keyword'),
            status: self::parseInt($query['status'] ?? null, 'status'),
            createTimeFrom: self::parseDate($query['create_time_from'] ?? null, 'create_time_from'),
            createTimeTo: self::parseDate($query['create_time_to'] ?? null, 'create_time_to'),
        );
    }

    private static function parseString(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidQueryException("{$name} 必须为字符串", [$name => 'string']);
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function parseInt(mixed $value, string $name): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d{1,9}$/', $value) === 1) {
            return (int) $value;
        }
        throw new InvalidQueryException("{$name} 必须为整数", [$name => 'integer']);
    }

    private static function parseDate(mixed $value, string $name): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidQueryException("{$name} 必须为 ISO 8601 时间", [$name => 'datetime']);
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidQueryException("{$name} 必须为 ISO 8601 时间", [$name => 'datetime']);
        }
    }
}
