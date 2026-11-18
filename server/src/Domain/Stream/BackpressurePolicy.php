<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 发送缓冲触顶（workerman onBufferFull）时的取舍：Pause 挂起生产、
 * 排空（onBufferDrain）续发；Drop 丢事件保内存，调用方按游标补段。
 */
enum BackpressurePolicy: string
{
    case Pause = 'pause';
    case Drop = 'drop';

    /** 配置值不合法时落 Pause：暂停只慢不坏，比静默丢事件安全。 */
    public static function fromName(string $name): self
    {
        return self::tryFrom($name) ?? self::Pause;
    }
}
