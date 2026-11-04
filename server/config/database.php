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
            'driver' => env('DB_TYPE', 'pgsql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => $resolve('DB_NAME', env('DB_NAME')),
            'username' => $resolve('DB_USER', env('DB_USER')),
            'password' => $resolve('DB_PASSWORD', env('DB_PASSWORD'), true),
            'charset' => env('DB_CHARSET', 'utf8'),
            'collation' => env('DB_COLLATION', 'utf8'),
            'prefix' => env('DB_PREFIX', ''),
            'strict' => true,
            'engine' => null,
            // libpq GSSAPI 协商默认由 PGGSSENCMODE 环境变量控制（见 support/gssencmode.php）。
            // illuminate/database 的 PostgresConnector 只按白名单拼 DSN，不支持自定义
            // 'dsn' 或任意连接参数，故本路径不提供 gssencmode 配置键，由环境变量统一生效。
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
