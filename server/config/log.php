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

return [
    'default' => [
        // 留存策略（T-4/N9）：security 类下限 183 天、general 类下限 30 天，
        // days 缺省 183；低于下限由 LogRetentionGuard 拒绝启动。库表日志
        // （审计、登录日志表）清理同样从 LogRetentionPolicy 取天数。
        'retention' => ['kind' => 'general', 'days' => 183],
        // RequestScope processor：请求/任务执行期把 request_id（任务另带
        // task_id、attempt）注入 record.extra，无绑定时不产生字段、不报错。
        'processors' => [
            ['class' => Sandadmin\Infra\Log\RequestScope::class, 'constructor' => []],
        ],
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/webman.log',
                    // 滚动份数对齐留存天数作兜底，真正的按天清理由 LogRetentionWorker 执行
                    181, //$maxFiles
                    Monolog\Logger::DEBUG,
                ],
                // 单行 JSON（time/level/channel/message/context/request_id…），
                // 供日志平台直接检索；契约见 docs/observability/。
                'formatter' => [
                    'class' => Sandadmin\Infra\Log\JsonLogFormatter::class,
                    'constructor' => [],
                ],
            ]
        ],
    ],
    // 安全类日志通道：登录、操作、审计等安全事件写这里（下限 183 天）
    'security' => [
        'retention' => ['kind' => 'security', 'days' => 183],
        'processors' => [
            ['class' => Sandadmin\Infra\Log\RequestScope::class, 'constructor' => []],
        ],
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/security.log',
                    181, //$maxFiles
                    Monolog\Logger::INFO,
                ],
                'formatter' => [
                    'class' => Sandadmin\Infra\Log\JsonLogFormatter::class,
                    'constructor' => [],
                ],
            ]
        ],
    ],
];
