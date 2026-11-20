<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace plugin\sandhello\app\controller;

use plugin\sandhello\app\Permission;

// 夹具控制器：只承载 kernel_api 校验需要的注解与方法壳，不挂真实业务。
final class NoteController
{
    #[Permission('示例记录', 'sand_hello:page:read')]
    public function index(): void
    {
    }

    #[Permission('示例记录详情', 'sand_hello:page:read')]
    public function show(): void
    {
    }
}
