<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Id;

/** 发号器配置非法（节点号、号段、回拨阈值缺失或越界）。由启动自检转成拒绝启动。 */
final class SnowflakeConfigException extends \RuntimeException
{
}
