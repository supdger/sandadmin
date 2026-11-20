<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

// 跨进程唯一性测试的子进程入口：php snowflake-child.php <节点号> <发号个数>，
// 每行一个号打到 stdout，由父进程合并查重。告警走 STDERR，不依赖框架日志。

use Sandadmin\Infra\Id\SnowflakeGenerator;
use Sandadmin\Task\SystemClock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$nodeId = (int) $argv[1];
$count = (int) $argv[2];
$generator = new SnowflakeGenerator(
    $nodeId,
    new SystemClock(),
    10,
    null,
    static function (string $message): void {
        fwrite(STDERR, $message . PHP_EOL);
    },
);
for ($i = 0; $i < $count; $i++) {
    echo $generator->next(), PHP_EOL;
}
