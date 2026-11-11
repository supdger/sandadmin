<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\DemoNote;

/**
 * 仓储基类的演示值对象（B1 证明用，无业务含义）。
 * 对应测试专用表 sand_demo_note，不进生产迁移。
 */
final readonly class DemoNote implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $title,
        public int $status,
        public \DateTimeImmutable $createTime,
    ) {
    }

    /** @return array{id: int, title: string, status: int, create_time: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'create_time' => $this->createTime->format(DATE_ATOM),
        ];
    }
}
