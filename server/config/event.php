<?php

/**
 * 宿主事件监听。插件自带的 user.login / user.operateLog 监听器都写
 * 0.x 旧表形状（sand_system_login_log 旧形状、sand_system_oper_log
 * 整表在 1.x 不存在），已被 config/plugin/sandadmin/event.php 的
 * 标记整节停掉；这里接管到宿主 LoginLogWriter / OperateLogWriter。
 * operateLog 事件在业务处理前发出拿不到结果，markPending 只打待
 * 记录标记，行由 OperateLogMiddleware 在响应后按真实结果落库。
 */

use Sandadmin\Module\AuditLog\OperateLogWriter;
use Sandadmin\Module\LoginLog\LoginLogWriter;

return [
    'user.login' => [
        [LoginLogWriter::class, 'record'],
    ],
    'user.operateLog' => [
        [OperateLogWriter::class, 'markPending'],
    ],
];
