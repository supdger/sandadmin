<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

use Sandadmin\Task\Handler\NoopTaskHandler;

/**
 * 宿主内置任务处理器清单（签名静态清单的代码形态，拍板 15：
 * 库里不存可执行目标，定义只来自代码）。插件任务处理器将来经
 * 扩展位注册合并进来，本类不识别插件。
 */
final class TaskHandlerCatalog
{
    /**
     * @return array<string, TaskHandler>
     */
    public static function host(): array
    {
        return [
            'noop' => new NoopTaskHandler(),
        ];
    }
}
