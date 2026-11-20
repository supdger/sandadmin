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

use Sandadmin\Http\Middleware\CorsMiddleware;
use Sandadmin\Http\Middleware\CsrfMiddleware;
use Sandadmin\Http\Middleware\SecurityHeadersMiddleware;

// '' 键 = 宿主全局：列表顺序即执行顺序（先列者最外层）。
// SecurityHeaders 最外，保证 403/204 等中间件短路响应也带安全头；
// Cors 在 Csrf 之前，OPTIONS 预检独立应答、不进入 CSRF 校验。
return [
    '' => [
        SecurityHeadersMiddleware::class,
        CorsMiddleware::class,
        CsrfMiddleware::class,
    ],
];
