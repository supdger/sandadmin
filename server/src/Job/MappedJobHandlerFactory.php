<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class MappedJobHandlerFactory implements JobHandlerFactory
{
    /**
     * @param array<string, JobHandler> $handlers
     */
    public function __construct(private readonly array $handlers)
    {
        foreach ($handlers as $type => $handler) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $type) !== 1) {
                throw new InvalidJobException('任务处理器名称不正确');
            }
            if (!$handler instanceof JobHandler) {
                throw new InvalidJobException('任务处理器必须实现 JobHandler');
            }
        }
    }

    public function forType(string $type): JobHandler
    {
        if (!array_key_exists($type, $this->handlers)) {
            throw new UnknownJobTypeException($type);
        }

        return $this->handlers[$type];
    }
}
