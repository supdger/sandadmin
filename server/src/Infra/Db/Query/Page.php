<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db\Query;

/**
 * 分页结果。JSON 形状：{items, total, page, page_size}。
 *
 * @template T of object
 */
final readonly class Page implements \JsonSerializable
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {
    }

    public function pageCount(): int
    {
        return $this->total === 0 ? 0 : intdiv($this->total + $this->pageSize - 1, $this->pageSize);
    }

    /** @return array{items: list<T>, total: int, page: int, page_size: int} */
    public function jsonSerialize(): array
    {
        return ['items' => $this->items, 'total' => $this->total, 'page' => $this->page, 'page_size' => $this->pageSize];
    }
}