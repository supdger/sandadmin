<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/** 探测的 HTTP 出站抽象：实现方只允许把请求打到本机回环，测试用替身注入。 */
interface ProbeTransport
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function get(string $url, array $headers): array;
}
