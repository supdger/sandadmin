<?php

/**
 * libpq GSSAPI 加密协商默认值。
 *
 * macOS 上 libpq 在建立 PostgreSQL 连接时会与 Kerberos/GSS 框架做 GSSAPI 加密
 * 协商；webman worker 由主进程 fork 而来，GSS 框架不是 fork 安全的，协商过程
 * 会导致 worker 以 SIGSEGV（exit status 11）崩溃。libpq 通过环境变量
 * PGGSSENCMODE 控制该协商，此处在用户未显式设置时默认置为 disable，跳过协商。
 *
 * 必须在 .env 加载之后、首个数据库连接与 worker fork 之前 require：phpdotenv
 * 的 load() 不覆盖已存在的环境变量，.env 中显式设置的 PGGSSENCMODE 与真实
 * 环境变量一样被视为用户显式设置，本文件一律尊重、不覆盖。
 *
 * SANDADMIN_PGGSSENCMODE_DEFAULTED 标记该值由本框架默认注入（而非用户显式
 * 设置），config/think-orm.php 据此决定是否把 gssencmode 追加进 DSN。
 *
 * 持久性说明：windows.php 与 webman 由 webman-framework / webman-console 的
 * Install 在每次 composer install/update 时按骨架强制重写，其中的注入会随之
 * 丢失；但所有实际触库路径——worker 的 onWorkerStart、非 start 类 console
 * 命令——都经由 support/bootstrap.php 中的 require 覆盖，该文件是项目源码，
 * 不会被骨架再生成。
 */

declare(strict_types=1);

if (getenv('PGGSSENCMODE') === false
    && !isset($_ENV['PGGSSENCMODE'])
    && !isset($_SERVER['PGGSSENCMODE'])
) {
    putenv('PGGSSENCMODE=disable');
    $_ENV['PGGSSENCMODE'] = 'disable';
    $_SERVER['PGGSSENCMODE'] = 'disable';
    if (!defined('SANDADMIN_PGGSSENCMODE_DEFAULTED')) {
        define('SANDADMIN_PGGSSENCMODE_DEFAULTED', true);
    }
}
