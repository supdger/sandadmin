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
    // 必须最先执行：生产环境开启 debug 时拒绝启动（安全 P0-F4）。
    app\bootstrap\ProductionDebugGuard::class,
    // 契约生成物与源一致性守卫：不一致 fail-closed 拒启。
    app\bootstrap\ContractIntegrityGuard::class,
    // 弱密码命中表：进程启动载入一次 + 生产缺失拒启自检（C-18）。
    app\bootstrap\WeakPasswordGuard::class,
    // 日志留存下限自检：通道 retention.days 低于类别下限即拒绝启动（T-4/N9）。
    app\bootstrap\LogRetentionGuard::class,
    // A-5：生产环境未配置 security.site_origin 即拒绝启动。
    app\bootstrap\SiteOriginGuard::class,
    // 发号器节点配置自检：节点号不在部署号段内或回拨阈值缺失即拒绝启动（v4 J-3）。
    app\bootstrap\IdGeneratorGuard::class,
    // 常驻进程运行配置 schema 自检 + 内存水位回收定时器（3i P2）。
    app\bootstrap\RuntimeConfigGuard::class,
    support\bootstrap\Session::class,
    Webman\ThinkOrm\ThinkOrm::class,
];
