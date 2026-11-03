<?php

/**
 * 数据库库名、用户名、密码不提供默认值，只能来自环境变量（.env 由安装向导写入）。
 *
 * - 变量已提供：直接使用。
 * - 变量缺失且 .env 已存在（已安装）：加载配置即抛出明确错误，不静默回退到固定账号。
 * - 变量缺失且 .env 不存在（未安装）：返回空值。此时安装向导需要正常打开，且不会发起任何
 *   数据库连接；配置在 worker 启动时即被加载，不能在这里抛错。
 */
$resolve = static function (string $name, mixed $value, bool $allowEmpty = false): string {
    if (is_string($value) && ($allowEmpty || $value !== '')) {
        return $value;
    }
    if (is_file(base_path() . DIRECTORY_SEPARATOR . '.env')) {
        throw new \RuntimeException("缺少必需的环境变量 {$name}：请在宿主根目录 .env 中设置，不存在默认值。");
    }

    return '';
};

return [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => [
            // 数据库类型
            'type' => env('DB_TYPE', 'pgsql'),
            // 服务器地址
            'hostname' => env('DB_HOST', '127.0.0.1'),
            // 数据库名
            'database' => $resolve('DB_NAME', env('DB_NAME')),
            // 数据库用户名
            'username' => $resolve('DB_USER', env('DB_USER')),
            // 数据库密码
            'password' => $resolve('DB_PASSWORD', env('DB_PASSWORD'), true),
            // 数据库连接端口
            'hostport' => env('DB_PORT', 5432),
            // 数据库连接参数
            'params' => [
                // 连接超时3秒
                \PDO::ATTR_TIMEOUT => 3,
            ],
            // 数据库编码默认采用utf8
            'charset' => env('DB_CHARSET', 'utf8'),
            // 数据库表前缀
            'prefix' => env('DB_PREFIX', ''),
            // 断线重连
            'break_reconnect' => true,
            // 自定义分页类
            'bootstrap' =>  '',
            // 连接池配置
            'pool' => [
                'max_connections' => 5, // 最大连接数
                'min_connections' => 1, // 最小连接数
                'wait_timeout' => 3,    // 从连接池获取连接等待超时时间
                'idle_timeout' => 60,   // 连接最大空闲时间，超过该时间会被回收
                'heartbeat_interval' => 50, // 心跳检测间隔，需要小于60秒
            ],
        ],
    ],
];
