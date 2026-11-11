<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\FileUsageAuthorizer;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\UserDisableCheck;
use Sandadmin\Domain\Plugin\UserTransfer;

/**
 * 插件在编译产物装载阶段注册的扩展点回调。
 * 进程内可变容器；核心只按键归组与汇总，不识别具体插件。
 */
final class ExtensionCallbacks
{
    /** @var array<string, UserDisableCheck> */
    private array $disableChecks = [];

    /** @var array<string, UserTransfer> */
    private array $userTransfers = [];

    /** @var array<string, FileUsageAuthorizer> */
    private array $fileUsageAuthorizers = [];

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

    /** 按 sand_system_file_usage.subject_type 注册；同一 subject_type 只允许一个鉴权方。 */
    public function addFileUsageAuthorizer(string $subjectType, FileUsageAuthorizer $authorizer): void
    {
        $subjectType = ExtensionRules::subjectType($subjectType);
        if (isset($this->fileUsageAuthorizers[$subjectType])) {
            throw new InvalidExtensionException('文件引用鉴权回调重复注册', ['subject_type' => 'unique']);
        }
        $this->fileUsageAuthorizers[$subjectType] = $authorizer;
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

    /** @return array<string, FileUsageAuthorizer> */
    public function fileUsageAuthorizers(): array
    {
        ksort($this->fileUsageAuthorizers);

        return $this->fileUsageAuthorizers;
    }

    public function fileUsageAuthorizer(string $subjectType): ?FileUsageAuthorizer
    {
        return $this->fileUsageAuthorizers[$subjectType] ?? null;
    }
}
