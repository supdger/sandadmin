<?php
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

use Sandadmin\Http\Middleware\OperateLogMiddleware;

return [
    // '@' 对全部请求（含插件）生效且位于最外层：插件 SystemLog 在
    // 业务处理前 emit user.operateLog 只能打待记录标记，行结果要在
    // 这里拿到真实响应后落库。
    '@' => [
        OperateLogMiddleware::class,
    ],
];
