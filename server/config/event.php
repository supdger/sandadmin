<?php

/**
 * 宿主事件监听。user.login 由登录链（插件 SystemUserLogic 与开发登录）
 * 发出，插件自己的监听器写的是旧表形状；这里按 1.x 的
 * sand_system_login_log 列补写一条（M2）。
 */

use Sandadmin\Module\LoginLog\LoginLogWriter;

return [
    'user.login' => [
        [LoginLogWriter::class, 'record'],
    ],
];
