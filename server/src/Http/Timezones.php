<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Domain\Exception\InvalidRequestException;

/** 请求头时区优先，否则用用户时区。只接受 IANA 时区名。 */
final class Timezones
{
    public const DEFAULT = 'UTC';

    public function resolve(?string $header, string $userZone): \DateTimeZone
    {
        $name = $header ?? $userZone;
        try {
            return new \DateTimeZone($name);
        } catch (\Exception $e) {
            throw new InvalidRequestException('时区无效', ['timezone' => 'timezone'], $e);
        }
    }
}
