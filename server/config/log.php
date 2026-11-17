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
        // 留存策略（T-4/N9）：security 类下限 180 天、general 类下限 30 天，
        // days 缺省 180；低于下限由 LogRetentionGuard 拒绝启动。库表日志
        // （审计、登录日志表）清理同样从 LogRetentionPolicy 取天数。
        'retention' => ['kind' => 'general', 'days' => 180],
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/webman.log',
                    // 滚动份数对齐留存天数作兜底，真正的按天清理由 LogRetentionWorker 执行
                    181, //$maxFiles
                    Monolog\Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
    // 安全类日志通道：登录、操作、审计等安全事件写这里（下限 180 天）
    'security' => [
        'retention' => ['kind' => 'security', 'days' => 180],
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/security.log',
                    181, //$maxFiles
                    Monolog\Logger::INFO,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
];
