<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace plugin\sandhello\app;

// 注解形状对齐 sand-core 的 plugin\sandadmin\service\Permission；
// 夹具自持一份，kernel_api 校验只按文本提取码字面量，不做反射。
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class Permission
{
    public function __construct(
        public ?string $title = null,
        public ?string $slug = null,
    ) {
    }
}
