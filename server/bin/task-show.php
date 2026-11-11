<?php
/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

/**
 * 按 public_id 查看任务详情（系统视角，可见全部）。
 *
 * 用法：php bin/task-show.php <task_id>
 */
declare(strict_types=1);

require __DIR__ . '/task-cli-autoload.php';

$taskId = $argv[1] ?? '';
if ($taskId === '') {
    fwrite(STDERR, "用法：php bin/task-show.php <task_id>\n");
    exit(2);
}

$boot = \Sandadmin\Task\TaskCli::boot(dirname(__DIR__));
if (isset($boot['error'])) {
    fwrite(STDERR, '启动失败: ' . $boot['error'] . "\n");
    exit(1);
}

$outcome = \Sandadmin\Task\TaskHttp::show(
    $boot['runtime']->store,
    \Sandadmin\Task\TaskActor::system(),
    $taskId,
    'cli',
);

http_response_code($outcome['status']);
echo json_encode($outcome['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit($outcome['status'] === 200 ? 0 : 1);
