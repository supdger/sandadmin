<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db\Query;

use Sandadmin\Infra\Db\Exception\InvalidQueryException;

/**
 * 统一列表参数：page, page_size, sort, filters[]。
 *
 * HTTP 约定（GET query）：
 *   page=1&page_size=20&sort=-created_at,id&filters[status][eq]=1&filters[title][like]=abc&filters[id][in]=1,2
 * sort：逗号分隔，前缀 "-" 为降序。filters：filters[字段][操作符]=值；in 用逗号分隔；null/notnull 值忽略。
 * 字段白名单在 Repository::fieldMap() 中校验，此处只做形状校验。
 */
final readonly class ListQuery
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 200;

    /**
     * @param list<Sort>   $sort
     * @param list<Filter> $filters
     */
    public function __construct(
        public int $page = 1,
        public int $pageSize = self::DEFAULT_PAGE_SIZE,
        public array $sort = [],
        public array $filters = [],
    ) {
        if ($page < 1) {
            throw new InvalidQueryException('page 必须 ≥ 1', ['page' => 'min:1']);
        }
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidQueryException('page_size 超出范围', ['page_size' => 'between:1,' . self::MAX_PAGE_SIZE]);
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    /** @param array<mixed> $query 原始 query 数组（如 $request->get()） */
    public static function fromArray(array $query): self
    {
        $page = self::toInt($query['page'] ?? 1, 'page');
        $pageSize = self::toInt($query['page_size'] ?? self::DEFAULT_PAGE_SIZE, 'page_size');

        $sort = [];
        $rawSort = $query['sort'] ?? '';
        if (!is_string($rawSort)) {
            throw new InvalidQueryException('sort 格式错误', ['sort' => 'string']);
        }
        foreach (array_filter(array_map('trim', explode(',', $rawSort)), static fn (string $s): bool => $s !== '') as $item) {
            $desc = str_starts_with($item, '-');
            $sort[] = new Sort($desc ? substr($item, 1) : $item, $desc ? SortDirection::Desc : SortDirection::Asc);
        }

        $filters = [];
        $rawFilters = $query['filters'] ?? [];
        if (!is_array($rawFilters)) {
            throw new InvalidQueryException('filters 格式错误', ['filters' => 'array']);
        }
        foreach ($rawFilters as $field => $ops) {
            if (!is_string($field) || !is_array($ops)) {
                throw new InvalidQueryException('filters 格式错误', ['filters' => 'filters[field][op]=value']);
            }
            foreach ($ops as $op => $value) {
                $operator = is_string($op) ? FilterOperator::tryFrom($op) : null;
                if ($operator === null) {
                    throw new InvalidQueryException('不支持的筛选操作符', ["filters.$field" => 'operator']);
                }
                $filters[] = new Filter($field, $operator, self::normalizeValue($operator, $value, $field));
            }
        }

        return new self($page, $pageSize, $sort, $filters);
    }

    private static function toInt(mixed $v, string $name): int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && preg_match('/^\d{1,9}$/', $v) === 1) {
            return (int) $v;
        }
        throw new InvalidQueryException("$name 必须为整数", [$name => 'integer']);
    }

    /** @return scalar|list<scalar>|null */
    private static function normalizeValue(FilterOperator $op, mixed $value, string $field): string|int|float|bool|array|null
    {
        if ($op === FilterOperator::IsNull || $op === FilterOperator::NotNull) {
            return null;
        }
        if ($op === FilterOperator::In) {
            $list = is_string($value) ? explode(',', $value) : $value;
            if (!is_array($list) || $list === []) {
                throw new InvalidQueryException('in 需要非空列表', ["filters.$field" => 'list']);
            }
            $out = [];
            foreach ($list as $item) {
                if (!is_scalar($item)) {
                    throw new InvalidQueryException('in 列表元素必须为标量', ["filters.$field" => 'scalar']);
                }
                $out[] = $item;
            }

            return $out;
        }
        if (!is_scalar($value)) {
            throw new InvalidQueryException('筛选值必须为标量', ["filters.$field" => 'scalar']);
        }

        return $value;
    }
}