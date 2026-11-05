<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

interface MimeSniffer
{
    /**
     * @param resource $stream 读取后复位到起点
     */
    public function sniff(mixed $stream): string;
}
