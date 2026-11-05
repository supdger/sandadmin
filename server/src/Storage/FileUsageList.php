<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

/**
 * @implements \IteratorAggregate<int, FileUsageRecord>
 */
final readonly class FileUsageList implements \IteratorAggregate, \Countable
{
    /**
     * @param list<FileUsageRecord> $items
     */
    public function __construct(public array $items)
    {
    }

    /**
     * @return \Traversable<int, FileUsageRecord>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
