#!/usr/bin/env php
<?php

declare(strict_types=1);

// 改动行覆盖率门禁（M-11：只对 diff 改动行算覆盖率阈值）。
//   php tools/check-diff-coverage.php [--base REF] [--suite NAME] [--threshold N] [--json]
// 改动行：`git diff -U0 <base> -- server/src` 新增行 + 未跟踪 server/src 文件全行；
// <base> 取 --base、SAND_COVERAGE_BASE、`git merge-base HEAD origin/main`。
// 覆盖：PHPUnit --coverage-clover 行覆盖（pcov 优先、xdebug 兜底、都没有退出 3）；
// 分母只算可执行改动行。阈值见 tools/test-gate.json。退出码：0 达标、1 未达标、2 环境错误。

$options = [
    'base' => null,
    'suite' => 'unit',
    'threshold' => null,
    'json' => false,
];
$rest = array_slice($argv, 1);
$argCount = count($rest);
for ($i = 0; $i < $argCount; $i++) {
    switch ($rest[$i]) {
        case '--base':
            $options['base'] = $rest[++$i] ?? null;
            break;
        case '--suite':
            $options['suite'] = $rest[++$i] ?? null;
            break;
        case '--threshold':
            $options['threshold'] = $rest[++$i] ?? null;
            break;
        case '--json':
            $options['json'] = true;
            break;
        default:
            fwrite(STDERR, "用法：php {$argv[0]} [--base REF] [--suite NAME] [--threshold N] [--json]\n");
            exit(2);
    }
}
if ($options['base'] === null && getenv('SAND_COVERAGE_BASE') !== false) {
    $options['base'] = getenv('SAND_COVERAGE_BASE');
}

$root = dirname(__DIR__);
$serverDir = $root . '/server';
$phpunit = $serverDir . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
    fwrite(STDERR, "找不到 {$phpunit}，先在 server/ 下 composer install\n");
    exit(2);
}

function runGit(string $root, string $args): string
{
    $cmd = 'git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null';
    $out = shell_exec($cmd);
    return is_string($out) ? $out : '';
}

$base = $options['base'];
if ($base === null) {
    $base = trim(runGit($root, 'merge-base HEAD origin/main'));
    if ($base === '') {
        fwrite(STDERR, "无法确定 diff 基准：未给 --base、无 SAND_COVERAGE_BASE、merge-base 失败\n");
        exit(2);
    }
}
$base = trim($base);

/**
 * 收集改动行：文件 repo 相对路径 => 新增行行号集合。
 *
 * @return array<string, array<int, true>>
 */
function changedLines(string $root, string $base): array
{
    $diff = runGit($root, 'diff --unified=0 --no-color ' . escapeshellarg($base) . ' -- server/src');
    $lines = [];
    $file = null;
    foreach (explode("\n", $diff) as $row) {
        if (str_starts_with($row, '+++ b/')) {
            $file = substr($row, 6);
            continue;
        }
        if (str_starts_with($row, '+++ ')) {
            $file = null;
            continue;
        }
        if ($file !== null && preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $row, $matches) === 1) {
            $start = (int) $matches[1];
            $count = isset($matches[2]) ? (int) $matches[2] : 1;
            for ($lineNo = $start; $lineNo < $start + $count; $lineNo++) {
                $lines[$file][$lineNo] = true;
            }
        }
    }
    $untracked = runGit($root, 'ls-files --others --exclude-standard -- server/src');
    foreach (explode("\n", $untracked) as $path) {
        $path = trim($path);
        if ($path === '' || !str_ends_with($path, '.php')) {
            continue;
        }
        $total = count(file($root . '/' . $path) ?: []);
        for ($lineNo = 1; $lineNo <= $total; $lineNo++) {
            $lines[$path][$lineNo] = true;
        }
    }
    return $lines;
}

$changed = changedLines($root, $base);
$changedFiles = array_keys($changed);
sort($changedFiles);

$report = [
    'check' => 'diff-coverage',
    'ok' => false,
    'base' => $base,
    'suite' => $options['suite'],
    'threshold' => null,
    'driver' => null,
    'percent' => null,
    'files' => [],
    'note' => null,
];

$finish = function (int $code) use (&$report, $options): never {
    if ($options['json']) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    }
    exit($code);
};

if ($changedFiles === []) {
    $report['ok'] = true;
    $report['note'] = 'server/src 无改动行';
    if (!$options['json']) {
        echo "改动行覆盖率：server/src 相对 {$base} 无改动，跳过\n";
    }
    $finish(0);
}

// 覆盖率驱动：pcov 优先，xdebug 兜底。
$loaded = shell_exec('php -m 2>/dev/null');
$driverFlags = '';
if (is_string($loaded) && stripos($loaded, 'pcov') !== false) {
    $driverFlags = '-d pcov.enabled=1 -d pcov.directory=' . escapeshellarg($serverDir . '/src');
    $report['driver'] = 'pcov';
} elseif (is_string($loaded) && stripos($loaded, 'xdebug') !== false) {
    $driverFlags = '-d xdebug.mode=coverage';
    $report['driver'] = 'xdebug';
} else {
    $report['note'] = '无覆盖率驱动（需要 ext-pcov 或 ext-xdebug）';
    if (!$options['json']) {
        fwrite(STDERR, "改动行覆盖率：本机无 pcov/xdebug 扩展，无法计算覆盖率\n");
    }
    $finish(3);
}

$configFile = $root . '/tools/test-gate.json';
$config = is_file($configFile) ? json_decode((string) file_get_contents($configFile), true) : null;
$threshold = $options['threshold'] !== null
    ? (float) $options['threshold']
    : (is_array($config) && isset($config['diff_line_coverage_min'])
        ? (float) $config['diff_line_coverage_min']
        : 80.0);
$report['threshold'] = $threshold;

$cloverFile = tempnam(sys_get_temp_dir(), 'sand-clover-');
if ($cloverFile === false) {
    fwrite(STDERR, "无法创建临时 clover 文件\n");
    exit(2);
}
$cmd = 'cd ' . escapeshellarg($serverDir)
    . ' && php ' . $driverFlags
    . ' vendor/bin/phpunit --testsuite ' . escapeshellarg($options['suite'])
    . ' --coverage-clover ' . escapeshellarg($cloverFile)
    . ' --no-progress 2>&1';
exec($cmd, $runOut, $runCode);
if ($runCode !== 0) {
    fwrite(STDERR, "phpunit 覆盖率运行失败（退出 {$runCode}）：\n" . implode("\n", array_slice($runOut, -20)) . "\n");
    @unlink($cloverFile);
    exit(2);
}

/**
 * clover 行覆盖：绝对路径 => 行号 => 命中次数（仅 stmt 行参与行覆盖）。
 *
 * @return array<string, array<int, int>>
 */
function cloverLines(string $cloverFile): array
{
    $xml = simplexml_load_file($cloverFile);
    if ($xml === false) {
        return [];
    }
    $map = [];
    foreach ($xml->xpath('//file') ?: [] as $fileNode) {
        $name = (string) $fileNode['name'];
        foreach ($fileNode->line as $lineNode) {
            if ((string) $lineNode['type'] !== 'stmt') {
                continue;
            }
            $map[$name][(int) $lineNode['num']] = (int) $lineNode['count'];
        }
    }
    return $map;
}

$coverage = cloverLines($cloverFile);
@unlink($cloverFile);
// clover 写绝对路径；转成 repo 相对路径以便和 diff 口径对齐。
$relCoverage = [];
foreach ($coverage as $abs => $lines) {
    $rel = str_starts_with($abs, $root . '/') ? substr($abs, strlen($root) + 1) : $abs;
    $relCoverage[$rel] = $lines;
}

$totalCoverable = 0;
$totalCovered = 0;
foreach ($changedFiles as $path) {
    $fileLines = $relCoverage[$path] ?? [];
    $coverable = 0;
    $covered = 0;
    $missed = [];
    foreach (array_keys($changed[$path]) as $lineNo) {
        if (!array_key_exists($lineNo, $fileLines)) {
            continue;
        }
        $coverable++;
        if ($fileLines[$lineNo] > 0) {
            $covered++;
        } else {
            $missed[] = $lineNo;
        }
    }
    $totalCoverable += $coverable;
    $totalCovered += $covered;
    $report['files'][] = [
        'path' => $path,
        'changed' => count($changed[$path]),
        'coverable' => $coverable,
        'covered' => $covered,
        'missed_lines' => $missed,
    ];
}

$percent = $totalCoverable === 0 ? 100.0 : round($totalCovered / $totalCoverable * 100, 2);
$report['percent'] = $percent;
$report['ok'] = $percent >= $threshold;
if ($totalCoverable === 0) {
    $report['note'] = '改动行中无可执行语句（无可覆盖行）';
}

if (!$options['json']) {
    echo "改动行覆盖率（{$report['driver']}，基准 {$base}，套件 {$options['suite']}）\n";
    foreach ($report['files'] as $fileReport) {
        $miss = $fileReport['missed_lines'] === [] ? '' : ' 未覆盖行: ' . implode(',', array_slice($fileReport['missed_lines'], 0, 20))
            . (count($fileReport['missed_lines']) > 20 ? '…' : '');
        echo "  {$fileReport['path']}: {$fileReport['covered']}/{$fileReport['coverable']}{$miss}\n";
    }
    echo "合计 {$totalCovered}/{$totalCoverable} = {$percent}%（阈值 {$threshold}%）"
        . ($report['ok'] ? " 通过\n" : " 未达标\n");
}

$finish($report['ok'] ? 0 : 1);
