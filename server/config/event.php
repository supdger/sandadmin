<?php

/**
 * 宿主事件监听。user.login 由登录链（插件 SystemUserLogic 与开发登录）
 * 发出；插件自带的登录监听器写旧表形状，已被
 * config/plugin/sandadmin/event.php 的标记停掉，这里按 1.x 的
 * sand_system_login_log 列写唯一一行（M2）。user.operateLog 的插件
 * 监听器也因同一标记停掉，在这里补挂回去，行为与原来一致。
 */

use Sandadmin\Module\LoginLog\LoginLogWriter;

$events = [
    'user.login' => [
        [LoginLogWriter::class, 'record'],
    ],
];

// 插件卸载时类不存在，框架会对不可调用的监听器打启动告警
if (class_exists(\plugin\sandadmin\app\event\SystemUser::class)) {
    $events['user.operateLog'] = [
        [\plugin\sandadmin\app\event\SystemUser::class, 'operateLog'],
    ];
}

return $events;
