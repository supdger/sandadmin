<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Demo;

/** 示例 VO（B1 仓储证明用，无业务含义）。 */
final readonly class DemoNote implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $title,
        public int $status,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array{id: int, title: string, status: int, created_at: string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'status' => $this->status, 'created_at' => $this->createdAt->format(DATE_ATOM)];
    }
}