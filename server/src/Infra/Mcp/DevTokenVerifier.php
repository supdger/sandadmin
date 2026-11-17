<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp;

use Sandadmin\Domain\Mcp\CredentialVerifier;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Infra\DevBuild;

/**
 * 开发构建本机令牌校验：环境变量 SAND_MCP_DEV_TOKEN + .sand-dev-build 标记
 * 同时成立才放行。权限取开发账号全量（['*']），不超过当前开发账号本身
 * （U-3/M-01：凭据能力不得越账号权限）。正式机器令牌走另一个实现。
 */
final class DevTokenVerifier implements CredentialVerifier
{
    public const ENV_NAME = 'SAND_MCP_DEV_TOKEN';

    /**
     * @param null|bool $devBuild 默认读 SAND_DEV_BUILD 标记；测试可注入 false
     *                            模拟无标记的生产制品，避免依赖进程内常量。
     */
    public function __construct(private readonly ?bool $devBuild = null)
    {
    }

    public function verify(string $token): ?McpIdentity
    {
        $expected = self::expected($this->devBuild);
        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            return null;
        }

        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], ['*']);
    }

    /** 端点是否应当挂出：标记与环境变量缺一即整条链不存在。 */
    public static function configured(?bool $devBuild = null): bool
    {
        return self::expected($devBuild) !== null;
    }

    private static function expected(?bool $devBuild): ?string
    {
        $enabled = $devBuild ?? DevBuild::enabled();
        if (!$enabled) {
            return null;
        }
        $value = getenv(self::ENV_NAME);
        if ($value === false) {
            return null;
        }
        $value = trim($value);
        // 令牌至少 16 位，防弱口令当令牌用
        if (mb_strlen($value) < 16) {
            return null;
        }

        return $value;
    }
}
