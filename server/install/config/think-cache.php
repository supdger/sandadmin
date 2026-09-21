<?php

return [
    'default' => env('CACHE_MODE', 'file'),
    'stores' => [
        'redis' => [
            'type' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', ''),
            'select' => env('REDIS_DB', 0),
            'prefix' => 'cache:',
            'expire' => 0,
            'tag_expire' => 86400 * 30,
            'tag_prefix' => 'tag:',
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
        'file' => [
            'type' => \plugin\sandadmin\app\cache\driver\File::class,
            'path' => runtime_path() . '/file/',
        ],
    ],
];
