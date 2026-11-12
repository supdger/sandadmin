<?php
/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

/**
 * 手动执行一轮任务认领（调试/运维用；正式环境由 task_worker 进程周期执行）。
 * 有任务执行完输出其终态 JSON，无可认领任务输出 null。
 */
declare(strict_types=1);

require __DIR__ . '/task-cli-autoload.php';

$boot = \Sandadmin\Task\TaskCli::boot(dirname(__DIR__));
if (isset($boot['error'])) {
    fwrite(STDERR, '启动失败: ' . $boot['error'] . "\n");
    exit(1);
}

$task = $boot['runtime']->executor->tick();
if ($task === null) {
    echo "null\n";
    exit(0);
}

echo json_encode(\Sandadmin\Task\TaskHttp::task($task), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);
