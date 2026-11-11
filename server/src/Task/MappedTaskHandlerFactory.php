<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 按字面量映射表分发处理器。处理器定义只来自代码内的静态清单，
 * 不从数据库读取可执行目标。
 */
final class MappedTaskHandlerFactory implements TaskHandlerFactory
{
    /**
     * @param array<string, TaskHandler> $handlers
     */
    public function __construct(private readonly array $handlers)
    {
        foreach ($handlers as $type => $handler) {
            if (preg_match('/^[a-z][a-z0-9_.]{0,63}$/', (string) $type) !== 1) {
                throw new InvalidTaskException('任务类型标识不正确');
            }
        }
    }

    public function forType(string $type): TaskHandler
    {
        if (!array_key_exists($type, $this->handlers)) {
            throw new UnknownTaskTypeException($type);
        }

        return $this->handlers[$type];
    }
}
