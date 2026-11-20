<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 常驻进程安全规则（3i §8.3 / S10）：src/ 与 app/ 下的业务代码禁止
 * 可变静态属性与 $GLOBALS——请求/用户/租户/事务态跨请求串数据的
 * 头号来源。tests/、tools/ 不在规则作用域（测试夹具与一次性脚本
 * 不进常驻进程）。
 *
 * 例外只允许 ALLOWLIST 里逐条登记的「启动期挂载一次的进程级资源」：
 * 词表、发号器、流式监督器注册表这类绑定后不再换绑的对象；
 * 每条都写明理由，新增条目需评审。除此之外一律判红。
 *
 * @implements Rule<Node>
 */
final class ResidentStateRule implements Rule
{
    /**
     * 类全名 => 属性名 => 豁免理由。只收「挂载后不再换绑」的进程级资源，
     * 请求态、计数器、缓存一律不许进。
     */
    private const ALLOWLIST = [
        // 弱密码词表：worker 启动时 mount 一次，之后只读查询（C-18）
        'Sandadmin\\Infra\\Auth\\WeakPasswordRuntime' => [
            'list' => '启动期挂载一次的只读词表',
        ],
        // 发号器：worker 启动时 mount 一次，全进程共享同一实例（v4 J-3）
        'Sandadmin\\Infra\\Id\\SnowflakeRuntime' => [
            'generator' => '启动期挂载一次的发号器实例',
        ],
        // 流式监督器：每进程一份的活动会话注册表，口径按进程计（J-5）
        'Sandadmin\\Application\\Stream\\StreamRuntime' => [
            'supervisors' => '进程级流式监督器注册表',
            'metrics' => '进程级流式指标 sink',
        ],
        // SSE 演示端点的进程内共享通道：整目录 dev-only，生产包剔除
        'Sandadmin\\Module\\DemoNote\\SseDemoController' => [
            'sharedChannel' => 'dev-only 演示通道，生产制品不含此目录',
        ],
        // 演练账本的 flock 资源登记：跨实例共享同一把锁，防不可重入自锁
        'Sandadmin\\Module\\Package\\MemoryPluginRegistry' => [
            'locks' => '进程级文件锁资源登记',
        ],
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->inScope($scope)) {
            return [];
        }
        if ($node instanceof Class_) {
            return $this->checkClass($node, $scope);
        }
        if ($node instanceof Variable && $node->name === 'GLOBALS') {
            return [
                RuleErrorBuilder::message('禁止使用 $GLOBALS：请求/任务态必须走 ContextStoreInterface')
                    ->line($node->getLine())
                    ->build(),
            ];
        }

        return [];
    }

    /** @return list<\PHPStan\Rules\RuleError> */
    private function checkClass(Class_ $node, Scope $scope): array
    {
        $name = $node->namespacedName?->toString();
        if ($name === null) {
            return [];
        }
        $errors = [];
        foreach ($node->stmts as $statement) {
            if (!$statement instanceof Property || !$statement->isStatic()) {
                continue;
            }
            foreach ($statement->props as $property) {
                $propName = $property->name->toString();
                if (isset(self::ALLOWLIST[$name][$propName])) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(sprintf(
                    '禁止可变静态属性 %s::$%s：请求/任务态请走 ContextStoreInterface，进程级资源需在 ResidentStateRule::ALLOWLIST 登记理由',
                    $name,
                    $propName,
                ))->line($statement->getLine())->build();
            }
        }

        return $errors;
    }

    /** 规则只管常驻业务代码：src/ 与 app/；tests/、tools/ 的夹具与脚本不进常驻进程。 */
    private function inScope(Scope $scope): bool
    {
        $file = str_replace('\\', '/', $scope->getFile());

        return preg_match('~(?:^|/)src/|(?:^|/)app/~', $file) === 1;
    }
}
