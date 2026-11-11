<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Audit;

use Sandadmin\Domain\Plugin\InvalidExtensionException;

/** 操作日志功能筛选选项，形状对应契约 AuditModuleOption：{key, name}。 */
final readonly class AuditModuleOption implements \JsonSerializable
{
    public string $key;
    public string $name;

    public function __construct(string $key, string $name)
    {
        if ($key === '' || $name === '') {
            throw new InvalidExtensionException('功能选项不能为空', ['key' => 'required']);
        }
        $this->key = $key;
        $this->name = $name;
    }

    /** @return array{key: string, name: string} */
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'name' => $this->name];
    }
}
