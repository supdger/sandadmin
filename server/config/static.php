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

/**
 * Static file settings
 */
return [
    'enable' => true,
    'middleware' => [     // Static file Middleware
        // 静态资源响应同样输出 N3 安全头（后台页面验收口径）；静态链不走全局中间件。
        Sandadmin\Http\Middleware\SecurityHeadersMiddleware::class,
    ],
];