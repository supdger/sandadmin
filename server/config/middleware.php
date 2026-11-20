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
use Sandadmin\Http\Middleware\RuntimeGuardMiddleware;
use Sandadmin\Http\Middleware\SecurityHeadersMiddleware;

// '' 键 = 宿主全局：列表顺序即执行顺序（先列者最外层）。
// RuntimeGuard 最外：请求收尾的上下文残留断言与回收计数要包住
// 一切短路响应；SecurityHeaders 仍在其内，保证 403/204 也带安全头；
// Cors 在 Csrf 之前，OPTIONS 预检独立应答、不进入 CSRF 校验。
return [
    '' => [
        RuntimeGuardMiddleware::class,
        SecurityHeadersMiddleware::class,
        CorsMiddleware::class,
        CsrfMiddleware::class,
    ],
];
