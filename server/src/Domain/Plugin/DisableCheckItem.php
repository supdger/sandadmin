<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 用户停用/删除前的待处理事项，对应 GET /users/{user_id}/disable-check 的返回元素。
 * 由各插件「用户停用前检查」回调产出后由核心汇总；空数组表示无阻碍。
 */
final readonly class DisableCheckItem implements \JsonSerializable
{
    public string $source;
    public string $kind;
    public int $count;
    public string $message;

    public function __construct(string $source, string $kind, int $count, string $message)
    {
        $this->source = ExtensionRules::pluginCode($source);
        $this->kind = ExtensionRules::key($kind);
        if ($count < 0) {
            throw new InvalidExtensionException('count 不能为负', ['count' => 'min:0']);
        }
        $this->count = $count;
        $this->message = ExtensionRules::description($message);
    }

    /** @return array{source: string, kind: string, count: int, message: string} */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source,
            'kind' => $this->kind,
            'count' => $this->count,
            'message' => $this->message,
        ];
    }
}
