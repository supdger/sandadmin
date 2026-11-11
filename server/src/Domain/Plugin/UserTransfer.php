<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 「转交」扩展点：停用/删除用户且接口传入 transfer_to_user_id 时，
 * 核心调用各插件的 transfer 把事项转给接收人。核心不识别具体插件。
 */
interface UserTransfer
{
    /**
     * 把 $userId 名下需要转交的事项交给 $toUserId。
     * 仍有处理不了的阻碍时由「用户停用前检查」再次汇总体现。
     */
    public function transfer(int $userId, int $toUserId): void;
}
