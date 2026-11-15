<?php
// Config::loadFromDir 只读取同目录下带 app.php 且 enable 为真的配置文件，
// 这个文件只承担让 config/plugin/sandadmin/ 下其余配置生效的门槛作用。
return [
    'enable' => true,
];
