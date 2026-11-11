<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db\Query;

use Sandadmin\Infra\Db\Exception\InvalidQueryException;

/**
 * 列表分页与排序参数：page、page_size、sort、order。
 *
 * HTTP 约定（GET query）：page=1&page_size=20&sort=create_time&order=desc。
 * 具名筛选参数（keyword、status、create_time_from 等）由各模块的列表查询对象
 * 单独声明，不在这里解析；出现 filters/filter 动态筛选语法直接拒绝。
 * sort/order 只做形状校验，字段白名单在 Repository::fieldMap() 中校验。
 */
readonly class ListQuery
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;

    /**
     * @param list<Sort> $sort
     */
    public function __construct(
        public int $page = 1,
        public int $pageSize = self::DEFAULT_PAGE_SIZE,
        public array $sort = [],
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

    /** @param array<mixed> $query 原始查询参数（如控制器传入的请求 query 数组） */
    public static function fromArray(array $query): self
    {
        if (array_key_exists('filters', $query) || array_key_exists('filter', $query)) {
            throw new InvalidQueryException('不支持动态筛选参数', ['filters' => 'use_named_params']);
        }

        $page = self::toInt($query['page'] ?? 1, 'page');
        $pageSize = self::toInt($query['page_size'] ?? self::DEFAULT_PAGE_SIZE, 'page_size');

        return new self($page, $pageSize, self::parseSort($query));
    }

    /**
     * @param array<mixed> $query
     *
     * @return list<Sort>
     */
    private static function parseSort(array $query): array
    {
        $rawSort = $query['sort'] ?? '';
        if (!is_string($rawSort)) {
            throw new InvalidQueryException('sort 必须为字符串', ['sort' => 'string']);
        }
        $field = trim($rawSort);
        if ($field === '') {
            return [];
        }

        return [new Sort($field, self::parseOrder($query['order'] ?? null))];
    }

    private static function parseOrder(mixed $raw): SortDirection
    {
        if ($raw === null) {
            return SortDirection::Asc;
        }
        if (!is_string($raw)) {
            throw new InvalidQueryException('order 必须为字符串', ['order' => 'string']);
        }

        return match (strtolower($raw)) {
            'asc' => SortDirection::Asc,
            'desc' => SortDirection::Desc,
            default => throw new InvalidQueryException('order 只能是 asc 或 desc', ['order' => 'enum:asc,desc']),
        };
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
}
