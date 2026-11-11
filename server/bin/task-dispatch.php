<?php
/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

/**
 * 投递一个后台任务并打印 task_id（public_id）。
 *
 * 用法：php bin/task-dispatch.php <type> <created_by_user_id> [idempotency_key]
 * 例：  php bin/task-dispatch.php noop 1
 */
declare(strict_types=1);

require __DIR__ . '/task-cli-autoload.php';

$type = $argv[1] ?? '';
$createdBy = isset($argv[2]) ? (int) $argv[2] : 0;
$idempotencyKey = isset($argv[3]) && $argv[3] !== '' ? $argv[3] : null;

$boot = \Sandadmin\Task\TaskCli::boot(dirname(__DIR__));
if (isset($boot['error'])) {
    fwrite(STDERR, '启动失败: ' . $boot['error'] . "\n");
    exit(1);
}

try {
    $task = $boot['runtime']->dispatcher->dispatch($type, [], $createdBy, $idempotencyKey);
} catch (\Sandadmin\Task\InvalidTaskException $exception) {
    fwrite(STDERR, '参数不正确: ' . $exception->getMessage() . "\n");
    exit(2);
} catch (\Throwable $exception) {
    fwrite(STDERR, '投递失败: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo $task->publicId . "\n";
