<?php
// 插件自带的 event.php 把 user.login 绑到旧表形状的写入器，1.x 表下必然失败；
// 插件配置后加载且按键合并，删不掉它的监听器键。app.enable 标记让
// Webman\Event\BootStrap 整节跳过，接线改由 config/event.php 承担。
return [
    'app' => [
        'enable' => true,
    ],
];
