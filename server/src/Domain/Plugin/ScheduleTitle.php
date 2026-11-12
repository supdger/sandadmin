<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 定时任务保存成功后返回清单里的中文名。
 * 宿主任务写在这里；插件任务取签名清单 cron_tasks 的 title。
 */
final class ScheduleTitle
{
    /** @var array<string, string> */
    private const HOST = [
        'host.log-cleanup' => '日志清理',
        'host.message-cleanup' => '消息清理',
        'host.backup' => '数据备份',
    ];

    public static function of(string $code, ?ExtensionCatalog $catalog = null): string
    {
        if (isset(self::HOST[$code])) {
            return self::HOST[$code];
        }
        if ($catalog === null) {
            return $code;
        }
        $dot = strpos($code, '.');
        if ($dot === false) {
            return $code;
        }
        $pluginCode = substr($code, 0, $dot);
        $key = substr($code, $dot + 1);
        $plugin = $catalog->plugin($pluginCode);
        if ($plugin === null) {
            return $code;
        }
        foreach ($plugin->extensions as $draft) {
            if ($draft->kind === ExtensionKind::CronTask && $draft->key === $key) {
                return $draft->title;
            }
        }

        return $code;
    }
}
