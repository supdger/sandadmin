<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Sandadmin\Contract\KernelApi\KernelApiVersion;

/**
 * 发行包 manifest.json 里安装闸消费的字段。menus/locales/frontend 等
 * 宿主消费段不在这里校验，由 PluginManifestLoader 各自容错读取。
 * requires 的区间写法是「min,max」两个语义化版本，与 kernel_api
 * 区间同一口径：min ≤ max 且不跨主版本。
 */
final class PluginPackageManifest
{
    /**
     * @param list<string> $databases
     * @param array<string, string> $requires
     */
    public function __construct(
        public readonly string $app,
        public readonly string $version,
        public readonly string $kernelApiMin,
        public readonly string $kernelApiMax,
        public readonly array $databases,
        public readonly array $requires,
    ) {
    }

    /**
     * 逐字段校验并攒问题清单，不抛异常；有问题时 manifest 为 null。
     *
     * @return array{manifest: ?self, problems: list<string>}
     */
    public static function parse(string $json): array
    {
        $problems = [];
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['manifest' => null, 'problems' => ['manifest.json 不是合法的 JSON 对象']];
        }

        $app = $data['app'] ?? null;
        if (!is_string($app) || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $app) !== 1) {
            $problems[] = 'manifest.app 缺失或不是连字符小写标识';
            $app = '';
        }

        $version = $data['version'] ?? null;
        if (!is_string($version) || !KernelApiVersion::isSemver($version)) {
            $problems[] = 'manifest.version 缺失或不是语义化版本';
            $version = '0.0.0';
        }

        $min = $data['kernel_api_min'] ?? null;
        $max = $data['kernel_api_max'] ?? null;
        if (!is_string($min) || !KernelApiVersion::isSemver($min)) {
            $problems[] = 'manifest.kernel_api_min 缺失或不是语义化版本';
            $min = '0.0.0';
        }
        if (!is_string($max) || !KernelApiVersion::isSemver($max)) {
            $problems[] = 'manifest.kernel_api_max 缺失或不是语义化版本';
            $max = '0.0.0';
        }
        if (KernelApiVersion::isSemver($min) && KernelApiVersion::isSemver($max)
            && !self::validRange($min, $max)) {
            $problems[] = 'manifest.kernel_api 区间非法：须 min ≤ max 且不跨主版本';
        }

        $databases = [];
        $rawDatabases = $data['databases'] ?? null;
        if (!is_array($rawDatabases) || !array_is_list($rawDatabases) || $rawDatabases === []) {
            $problems[] = 'manifest.databases 缺失或不是非空列表';
        } else {
            foreach ($rawDatabases as $item) {
                if (!is_string($item) || !in_array($item, ['pgsql', 'mysql'], true)) {
                    $problems[] = 'manifest.databases 只允许 pgsql/mysql 子集';
                    continue;
                }
                $databases[] = $item;
            }
        }

        $requires = [];
        $rawRequires = $data['requires'] ?? null;
        if ($rawRequires !== null) {
            if (!is_array($rawRequires) || array_is_list($rawRequires)) {
                $problems[] = 'manifest.requires 必须是 {插件标识: "min,max"} 对象';
            } else {
                foreach ($rawRequires as $dep => $range) {
                    if (!is_string($dep) || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $dep) !== 1) {
                        $problems[] = 'manifest.requires 的插件标识非法';
                        continue;
                    }
                    if (!is_string($range) || !self::validRangeText($range)) {
                        $problems[] = 'manifest.requires[' . $dep . '] 区间非法：写法是 "min,max"，min ≤ max 且不跨主版本';
                        continue;
                    }
                    $requires[$dep] = $range;
                }
            }
        }

        if ($problems !== []) {
            return ['manifest' => null, 'problems' => $problems];
        }

        return [
            'manifest' => new self($app, $version, $min, $max, array_values(array_unique($databases)), $requires),
            'problems' => [],
        ];
    }

    /** 区间两个端点都是合法语义化版本、min ≤ max 且同主版本。 */
    private static function validRange(string $min, string $max): bool
    {
        return KernelApiVersion::compare($min, $max) <= 0
            && explode('.', $min)[0] === explode('.', $max)[0];
    }

    private static function validRangeText(string $range): bool
    {
        $ends = explode(',', $range);
        if (count($ends) !== 2) {
            return false;
        }
        $min = trim($ends[0]);
        $max = trim($ends[1]);

        return KernelApiVersion::isSemver($min) && KernelApiVersion::isSemver($max)
            && self::validRange($min, $max);
    }
}
