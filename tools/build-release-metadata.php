#!/usr/bin/env php
<?php

declare(strict_types=1);

// 发版元数据：由 tools/build-release.sh 在自检通过后、收尾前调用。
//   php tools/build-release-metadata.php --src <剥离副本根> --dist <产物目录>
//                                        --version <版本> [--artifact <tar.gz 路径>]
// 产出两份文件（文件名随版本号）：
//   sandadmin-<版本>.sbom.json        CycloneDX 1.5；components = 剥离副本
//                                   server/composer.lock 的 packages（发版
//                                   --no-dev，dev 包不进制品也就不进清单）
//                                   + web/pnpm-lock.yaml importers['.']
//                                   解析出的已固定版本；lock 解析失败退回
//                                   web/package.json 声明区间（口径记录在
//                                   metadata.component 的 description）。
//   sandadmin-<版本>.provenance.json  SLSA-lite：builder.id、git commit/分支、
//                                   构建时间、php/node/pnpm/composer 版本、
//                                   构建入口与 subjects（制品 sha256 列表；
//                                   --self-check 无 tar.gz 时 subjects 只含 sbom）。

$options = [
    'src' => null,
    'dist' => null,
    'version' => null,
    'artifact' => null,
];
$argCount = count($argv);
for ($i = 1; $i < $argCount; $i++) {
    switch ($argv[$i]) {
        case '--src':
            $options['src'] = $argv[++$i] ?? null;
            break;
        case '--dist':
            $options['dist'] = $argv[++$i] ?? null;
            break;
        case '--version':
            $options['version'] = $argv[++$i] ?? null;
            break;
        case '--artifact':
            $options['artifact'] = $argv[++$i] ?? null;
            break;
        default:
            fwrite(STDERR, "未知参数：{$argv[$i]}\n");
            exit(2);
    }
}
foreach (['src', 'dist', 'version'] as $required) {
    if (!is_string($options[$required]) || $options[$required] === '') {
        fwrite(STDERR, "缺少 --{$required}\n");
        exit(2);
    }
}

$src = rtrim((string) $options['src'], '/');
$dist = rtrim((string) $options['dist'], '/');
$version = (string) $options['version'];
$root = dirname(__DIR__);

if (!is_dir($dist) && !mkdir($dist, 0755, true) && !is_dir($dist)) {
    fwrite(STDERR, "无法创建产物目录：{$dist}\n");
    exit(1);
}

/**
 * @return list<array<string, mixed>>
 */
function composerComponents(string $lockFile): array
{
    $lock = json_decode((string) file_get_contents($lockFile), true);
    if (!is_array($lock) || !isset($lock['packages']) || !is_array($lock['packages'])) {
        fwrite(STDERR, "composer.lock 无法解析：{$lockFile}\n");
        exit(1);
    }
    $components = [];
    foreach ($lock['packages'] as $package) {
        if (!is_array($package) || !isset($package['name'], $package['version'])) {
            continue;
        }
        $name = (string) $package['name'];
        $version = (string) $package['version'];
        $components[] = [
            'type' => 'library',
            'bom-ref' => "pkg:composer/{$name}@{$version}",
            'name' => $name,
            'version' => $version,
            'purl' => "pkg:composer/{$name}@{$version}",
            'scope' => 'required',
        ];
    }

    return $components;
}

/**
 * 前端组件优先取 pnpm-lock.yaml 的已固定版本；解析不了退回 package.json
 * 声明区间并在返回结构里带出口径说明。
 *
 * @return array{0:list<array<string, mixed>>,1:string}
 */
function frontendComponents(string $webDir, string $autoload): array
{
    $note = 'web/pnpm-lock.yaml 已固定版本';
    $resolved = [];
    $lockFile = $webDir . '/pnpm-lock.yaml';
    if (is_file($lockFile) && is_file($autoload)) {
        require_once $autoload;
        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                $lock = \Symfony\Component\Yaml\Yaml::parseFile($lockFile);
                $importer = is_array($lock) ? ($lock['importers']['.'] ?? null) : null;
                foreach (['dependencies' => 'required', 'devDependencies' => 'excluded'] as $section => $scope) {
                    $deps = is_array($importer) ? ($importer[$section] ?? []) : [];
                    if (!is_array($deps)) {
                        continue;
                    }
                    foreach ($deps as $name => $entry) {
                        $ver = is_array($entry) ? ($entry['version'] ?? null) : null;
                        if (!is_string($ver) || $ver === '') {
                            continue;
                        }
                        // pnpm 在版本后括注 peer 依赖，如 1.2.3(react@19.0.0)，purl 只留版本本体
                        $verParts = preg_split('/[(\s]/', (string) $ver);
                        $resolved[$name] = [
                            'version' => is_array($verParts) && $verParts[0] !== '' ? $verParts[0] : $ver,
                            'scope' => $scope,
                        ];
                    }
                }
            } catch (\Throwable) {
                $resolved = [];
            }
        }
    }

    $packageFile = $webDir . '/package.json';
    $package = json_decode((string) file_get_contents($packageFile), true);
    if (!is_array($package)) {
        fwrite(STDERR, "package.json 无法解析：{$packageFile}\n");
        exit(1);
    }
    if ($resolved === []) {
        $note = 'web/package.json 声明区间（pnpm-lock.yaml 未解析）';
    }

    $components = [];
    foreach (['dependencies' => 'required', 'devDependencies' => 'excluded'] as $section => $scope) {
        $deps = $package[$section] ?? [];
        if (!is_array($deps)) {
            continue;
        }
        foreach ($deps as $name => $declared) {
            $name = (string) $name;
            $entry = $resolved[$name] ?? null;
            $ver = $entry !== null ? $entry['version'] : (is_string($declared) ? $declared : '');
            $component = [
                'type' => 'library',
                'bom-ref' => "pkg:npm/{$name}@{$ver}",
                'name' => $name,
                'version' => $ver,
                'purl' => "pkg:npm/{$name}@{$ver}",
                'scope' => $entry !== null ? $entry['scope'] : $scope,
            ];
            $components[] = $component;
        }
    }

    return [$components, $note];
}

function git(string $root, string $args): string
{
    $out = shell_exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null');

    return is_string($out) ? trim($out) : '';
}

function toolVersion(string $command): ?string
{
    $out = shell_exec($command . ' 2>/dev/null');
    if (!is_string($out) || trim($out) === '') {
        return null;
    }

    return trim(explode("\n", trim($out))[0]);
}

function writeJson(string $file, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || file_put_contents($file, $json . "\n") === false) {
        fwrite(STDERR, "写入失败：{$file}\n");
        exit(1);
    }
}

$pkg = "sandadmin-{$version}";
[$npmComponents, $npmNote] = frontendComponents($src . '/web', $src . '/server/vendor/autoload.php');

$sbom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'serialNumber' => 'urn:uuid:' . vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
    'version' => 1,
    'metadata' => [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'tools' => [
            ['vendor' => 'sandadmin', 'name' => 'tools/build-release-metadata.php'],
        ],
        'component' => [
            'type' => 'application',
            'bom-ref' => "pkg:generic/{$pkg}",
            'name' => 'sandadmin',
            'version' => $version,
            'description' => "后端组件取剥离副本 server/composer.lock（--no-dev 口径）；前端组件口径：{$npmNote}",
        ],
    ],
    'components' => array_merge(
        composerComponents($src . '/server/composer.lock'),
        $npmComponents,
    ),
];

$sbomFile = "{$dist}/{$pkg}.sbom.json";
writeJson($sbomFile, $sbom);

$subjects = [];
$artifact = $options['artifact'];
if (is_string($artifact) && is_file($artifact)) {
    $subjects[] = [
        'name' => basename($artifact),
        'digest' => ['sha256' => hash_file('sha256', $artifact)],
    ];
}
$subjects[] = [
    'name' => basename($sbomFile),
    'digest' => ['sha256' => hash_file('sha256', $sbomFile)],
];

$builderId = getenv('GITHUB_ACTIONS') === 'true'
    ? 'github-actions/' . (string) getenv('GITHUB_WORKFLOW') . '/' . (string) getenv('GITHUB_RUN_ID')
    : 'local/' . (string) gethostname();

$repository = getenv('GITHUB_ACTIONS') === 'true'
    ? (string) getenv('GITHUB_SERVER_URL') . '/' . (string) getenv('GITHUB_REPOSITORY')
    : git($root, 'config --get remote.origin.url');

$provenance = [
    'builder' => ['id' => $builderId],
    'source' => [
        'commit' => git($root, 'rev-parse HEAD'),
        'branch' => git($root, 'rev-parse --abbrev-ref HEAD'),
        'repository' => $repository,
    ],
    'builtAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'toolchain' => [
        'php' => PHP_VERSION,
        'node' => toolVersion('node -v'),
        'pnpm' => toolVersion('pnpm -v'),
        'composer' => toolVersion('composer --version'),
    ],
    'invocation' => [
        'script' => 'tools/build-release.sh',
        'selfCheckOnly' => $artifact === null,
    ],
    'subjects' => $subjects,
];

$provenanceFile = "{$dist}/{$pkg}.provenance.json";
writeJson($provenanceFile, $provenance);

echo "OK {$sbomFile}\n";
echo "OK {$provenanceFile}\n";
