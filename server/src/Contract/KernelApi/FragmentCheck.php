<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\KernelApi;

use Sandadmin\Contract\Build\ConflictCheck;
use Sandadmin\Contract\Build\ContractBuild;
use Sandadmin\Contract\Build\ContractDoc;

/**
 * kernel_api 契约检查：对单个插件包或其契约片段做存在性、合法性、
 * 命名空间隔离与错误码声明校验，外加运行时对齐检查（RuntimeCheck）。
 * KernelApiTest 与 tools/contract-build --kernel-api 共用这个入口，
 * 结果零冲突才可装。
 */
final class FragmentCheck
{
    /**
     * 校验单个插件目录：<pluginDir>/contract/openapi.yaml。
     * 传入宿主契约路径时把宿主目录表并入冲突检查（插件码可引用宿主已登记码）。
     * @return list<string> 问题清单；空数组表示通过
     */
    public static function check(string $pluginDir, ?string $hostContractFile = null): array
    {
        $app = basename($pluginDir);
        $file = $pluginDir . '/contract/openapi.yaml';
        if (!is_file($file)) {
            return ["插件 {$app} 缺少契约片段：{$file}"];
        }

        $doc = ContractDoc::parse($file, $app);
        $docs = [$app => $doc];
        $stems = [$app => ContractBuild::pluginStem($app)];

        if ($hostContractFile !== null && is_file($hostContractFile)) {
            $host = ContractDoc::parse($hostContractFile, 'host');
            // 宿主排在插件前，冲突归属描述以宿主为既有方
            $docs = ['host' => $host, $app => $doc];
        }

        $problems = $doc['problems'];
        foreach (ConflictCheck::check($docs, $stems) as $conflict) {
            $problems[] = $conflict;
        }
        foreach (RuntimeCheck::check($pluginDir, $doc) as $problem) {
            $problems[] = $problem;
        }

        return $problems;
    }
}
