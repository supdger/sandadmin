<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 「用户停用前检查」扩展点：插件实现本接口并向 ExtensionRegistry 注册。
 * 核心在停用/删除用户前调用 pendingItems 汇总待处理事项，不识别具体插件。
 */
interface UserDisableCheck
{
    /**
     * 返回该用户名下需要先处理的事项；空数组表示无阻碍。
     * source 填本插件编码（或宿主标识），由核心原样汇总进 disable-check 结果。
     *
     * @return list<DisableCheckItem>
     */
    public function pendingItems(int $userId): array;
}
