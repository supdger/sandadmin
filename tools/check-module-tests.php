#!/usr/bin/env php
<?php

declare(strict_types=1);

// 模块测试目录门禁（M-11/S-15：模块＝src/Module/<名> + 对应测试目录，缺即失败）。
//   php tools/check-module-tests.php [--json]
// 判定：每个模块目录必须同时存在 `server/tests/Unit/Module/<名>` 与
// `server/tests/Integration/Module/<名>`，且其中至少一个 *Test.php。
// 退出码：0 齐备，1 有缺口，2 用法/环境错误；--json 出机器可读报告（M-09）。

$json = in_array('--json', $argv, true);
foreach ($argv as $arg) {
    if ($arg === $argv[0] || $arg === '--json') {
        continue;
    }
    fwrite(STDERR, "用法：php {$argv[0]} [--json]\n");
    exit(2);
}

$root = dirname(__DIR__);
$moduleRoot = $root . '/server/src/Module';
if (!is_dir($moduleRoot)) {
    fwrite(STDERR, "找不到模块根目录：{$moduleRoot}\n");
    exit(2);
}

/** 测试套件根：每个模块目录在各套件下都要有同名测试目录。 */
const SUITES = [
    'unit' => 'server/tests/Unit/Module',
    'integration' => 'server/tests/Integration/Module',
];

$modules = [];
foreach (scandir($moduleRoot) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    if (is_dir($moduleRoot . '/' . $entry)) {
        $modules[] = $entry;
    }
}
sort($modules);

$gaps = [];
foreach ($modules as $module) {
    foreach (SUITES as $suite => $suiteDir) {
        $dir = $root . '/' . $suiteDir . '/' . $module;
        if (!is_dir($dir)) {
            $gaps[] = [
                'module' => $module,
                'suite' => $suite,
                'expect' => $suiteDir . '/' . $module,
                'reason' => 'missing_dir',
            ];
            continue;
        }
        $tests = glob($dir . '/*Test.php') ?: [];
        if ($tests === []) {
            $gaps[] = [
                'module' => $module,
                'suite' => $suite,
                'expect' => $suiteDir . '/' . $module,
                'reason' => 'empty_dir',
            ];
        }
    }
}

$report = [
    'check' => 'module-tests',
    'ok' => $gaps === [],
    'modules' => $modules,
    'gaps' => $gaps,
];

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
} else {
    echo '模块测试目录检查：' . count($modules) . ' 个模块' . "\n";
    if ($gaps === []) {
        echo "全部模块在 Unit 与 Integration 套件下均有测试目录\n";
    } else {
        foreach ($gaps as $gap) {
            $why = $gap['reason'] === 'empty_dir' ? '目录存在但无 *Test.php' : '目录缺失';
            echo "缺口 {$gap['module']} [{$gap['suite']}] {$gap['expect']}（{$why}）\n";
        }
        echo '共 ' . count($gaps) . " 处缺口\n";
    }
}

exit($gaps === [] ? 0 : 1);
