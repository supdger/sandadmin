<?php

declare(strict_types=1);

/**
 * Fail when workerman/* or webman/* are not Composer release tags.
 * Official tags only: this script does not modify framework source.
 */

$root = dirname(__DIR__, 2);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);

if (!is_array($lock) || !is_array($composer)) {
    fwrite(STDERR, "release tag check failed: composer.json or composer.lock is not readable JSON\n");
    exit(1);
}

$failures = [];

$stability = (string) ($composer['minimum-stability'] ?? 'stable');
if ($stability === 'dev') {
    $failures[] = 'composer.json minimum-stability must not be dev (that allows dev-master)';
}

$require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
$requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
if (isset($require['cweagans/composer-patches']) || isset($requireDev['cweagans/composer-patches'])) {
    $failures[] = 'cweagans/composer-patches is not allowed';
}
$extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
if (isset($extra['patches'])) {
    $failures[] = 'composer extra.patches is not allowed';
}

$expected = [
    'workerman/workerman' => '5.2.3',
    'workerman/webman-framework' => '2.2.5',
];
$officialSource = [
    'workerman/workerman' => 'github.com/walkor/workerman',
    'workerman/webman-framework' => 'github.com/walkor/webman-framework',
];

$seen = [];
foreach (['packages', 'packages-dev'] as $section) {
    $packages = $lock[$section] ?? [];
    if (!is_array($packages)) {
        continue;
    }
    foreach ($packages as $package) {
        if (!is_array($package)) {
            continue;
        }
        $name = (string) ($package['name'] ?? '');
        if (!str_starts_with($name, 'workerman/') && !str_starts_with($name, 'webman/')) {
            continue;
        }
        $version = (string) ($package['version'] ?? '');
        $seen[$name] = $version;
        if (!is_release_tag($version)) {
            $failures[] = $name . ' is not a release tag: ' . $version;
        }
        if (isset($expected[$name]) && ltrim($version, 'vV') !== $expected[$name]) {
            $failures[] = $name . ' must be ' . $expected[$name] . ', locked ' . $version;
        }
        if (isset($officialSource[$name])) {
            $source = is_array($package['source'] ?? null) ? $package['source'] : [];
            $url = (string) ($source['url'] ?? '');
            if (!str_contains($url, $officialSource[$name])) {
                $failures[] = $name . ' source is not the official repository: ' . $url;
            }
        }
    }
}

foreach (array_keys($expected) as $name) {
    if (!isset($seen[$name])) {
        $failures[] = $name . ' is missing from composer.lock';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "release tag check failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

ksort($seen);
fwrite(STDOUT, 'release tag check ok (' . count($seen) . " workerman/webman packages)\n");
foreach ($seen as $name => $version) {
    fwrite(STDOUT, '  ' . $name . ' ' . $version . "\n");
}

function is_release_tag(string $version): bool
{
    if ($version === '' || str_starts_with($version, 'dev-') || str_contains($version, '#')) {
        return false;
    }
    if (preg_match('/-dev(?:$|\.)/', $version) === 1) {
        return false;
    }

    return preg_match('/^v?\d+\.\d+\.\d+(?:\.\d+)?$/', $version) === 1;
}
