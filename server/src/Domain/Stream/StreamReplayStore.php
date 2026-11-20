<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 断线补发存储（J-5：查库只用于断线后按游标补发）。本轮只定接口
 * 与内存实现；库表实现等编号表定稿后另做，业务代码不动。
 * after() 的游标对应事件 id（Last-Event-ID）。
 */
interface StreamReplayStore
{
    /** 追加一条事件；id 缺省时实现补一个通道内单调游标。容量到顶丢最旧。 */
    public function append(string $channel, StreamEvent $event): void;

    /**
     * 游标之后的事件，按追加顺序返回。
     * cursor 为 null 或查无此游标时返回通道内现存全部（保守补发）。
     *
     * @return list<StreamEvent>
     */
    public function after(string $channel, ?string $cursor): array;
}
