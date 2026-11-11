<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\UserDisableCheck;
use Sandadmin\Domain\Plugin\UserTransfer;

/**
 * 插件在编译产物装载阶段注册的用户生命周期回调。
 * 进程内可变容器；核心只按插件编码归组与汇总，不识别具体插件。
 */
final class ExtensionCallbacks
{
    /** @var array<string, UserDisableCheck> */
    private array $disableChecks = [];

    /** @var array<string, UserTransfer> */
    private array $userTransfers = [];

    public function addDisableCheck(string $pluginCode, UserDisableCheck $check): void
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        if (isset($this->disableChecks[$pluginCode])) {
            throw new InvalidExtensionException('停用前检查回调重复注册', ['plugin_code' => 'unique']);
        }
        $this->disableChecks[$pluginCode] = $check;
    }

    public function addUserTransfer(string $pluginCode, UserTransfer $transfer): void
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        if (isset($this->userTransfers[$pluginCode])) {
            throw new InvalidExtensionException('转交回调重复注册', ['plugin_code' => 'unique']);
        }
        $this->userTransfers[$pluginCode] = $transfer;
    }

    /** @return array<string, UserDisableCheck> */
    public function disableChecks(): array
    {
        ksort($this->disableChecks);

        return $this->disableChecks;
    }

    /** @return array<string, UserTransfer> */
    public function userTransfers(): array
    {
        ksort($this->userTransfers);

        return $this->userTransfers;
    }
}
