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

return [
    // 必须最先执行：生产环境开启 debug 时拒绝启动（安全 P0-F4）。
    app\bootstrap\ProductionDebugGuard::class,
    support\bootstrap\Session::class,
    Webman\ThinkOrm\ThinkOrm::class,
];
