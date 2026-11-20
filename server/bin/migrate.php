<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

/**
 * 统一迁移执行器入口：升级、回退、查账本，只走 CLI。
 * 用法见 php bin/migrate.php（无参数打印）。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

exit(\Sandadmin\Infra\Migration\MigrationCommand::main($argv, dirname(__DIR__)));
