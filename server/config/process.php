<?php

declare(strict_types=1);

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Log;
use support\Request;
use app\process\Http;

global $argv;

return [
    'webman' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:' . env('SANDADMIN_SERVER_PORT', 8787),
        // 压测/开发可经 SANDADMIN_WEB_WORKERS 固定为 1，验证「每进程挂路数」口径
        'count' => max(1, (int) env('SANDADMIN_WEB_WORKERS', cpu_count() * 4)),
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    // File update detection and automatic reload
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // Monitor these directories
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/src',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env'
            ],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ]
        ]
    ],
    // Custom process: 认领 sand_system_task_run 中可执行的任务并回写进度；
    // 轮询、租约、并发等参数统一在 config/task.php。
    'task_worker' => [
        'handler' => Sandadmin\Task\TaskWorker::class,
        'count' => max(1, (int) env('SANDADMIN_TASK_WORKERS', 1)),
        'constructor' => [],
    ],
    // Custom process: 按 config/log.php 各通道 retention 每日清理过期日志
    // 文件（T-4/N9）；库表日志清理落地后复用同一 LogRetentionPolicy。
    'log_retention' => [
        'handler' => Sandadmin\Infra\Log\LogRetentionWorker::class,
        'count' => 1,
        'constructor' => [],
    ],
];
