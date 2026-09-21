<?php

declare(strict_types=1);

/**
 * HOST-202609-004: real multiprocess file-cache regression, without HTTP or a DB.
 * php server/tests/SandAdmin/FileCacheConcurrencyTest.php [--baseline] [--rounds=1000]
 * All files belong to a new temporary directory; no installed host is booted.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use plugin\sandadmin\app\cache\ReflectionCache;
use plugin\sandadmin\app\cache\driver\File;
use plugin\sandadmin\app\middleware\CheckAuth;
use plugin\sandadmin\service\Permission;
use Webman\ThinkCache\driver\File as ThinkFile;

final class NeutralCacheController
{
    protected array $noNeedLogin = ['publicAction'];

    #[Permission(title: 'User', slug: 'neutral:user')]
    public function user(): void {}

    #[Permission(title: 'Dictionary', slug: 'neutral:dict')]
    public function dictAll(): void {}

    #[Permission(title: 'Menu', slug: 'neutral:menu')]
    public function menu(): void {}

    public function publicAction(): void {}
}

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function reflectAndAuthorize(int $worker): void
{
    ensure(ReflectionCache::getNoNeedLogin(NeutralCacheController::class) === ['publicAction'], 'noNeedLogin mismatch');
    $check = new ReflectionMethod(CheckAuth::class, 'checkPermissions');
    foreach (['user' => ['User', 'neutral:user'], 'dictAll' => ['Dictionary', 'neutral:dict'], 'menu' => ['Menu', 'neutral:menu']] as $action => [$title, $slug]) {
        $attributes = ReflectionCache::getPermissionAttributes(NeutralCacheController::class, $action);
        ensure($attributes === ['title' => $title, 'slug' => $slug], 'Permission attributes missing or mixed');
        // Exercise the actual permission predicate with alternating, disjoint role fixtures.
        $role = ($worker % 2 === 0) ? ['neutral:user'] : ['neutral:dict', 'neutral:menu'];
        ensure($check->invoke(new CheckAuth(), $attributes, $role) === in_array($slug, $role, true), 'Role isolation mismatch');
    }
    ensure(ReflectionCache::getPermissionAttributes(NeutralCacheController::class, 'publicAction') === [], 'Empty attributes mismatch');
}

function runBatch(array $workers, string $action): array
{
    foreach ($workers as $worker) {
        ensure(fwrite($worker['socket'], $action . "\n") !== false, 'Worker dispatch failed');
    }
    $results = [];
    foreach ($workers as $worker) {
        $line = fgets($worker['socket']);
        ensure($line !== false, 'Worker timeout or premature exit');
        $results[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}

$baseline = in_array('--baseline', $argv, true);
$rounds = 1000;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--rounds=')) {
        $rounds = (int) substr($argument, strlen('--rounds='));
    }
}
ensure($rounds >= 1 && extension_loaded('pcntl'), 'Positive rounds and pcntl required');
$root = sys_get_temp_dir() . '/sandadmin-file-cache-' . bin2hex(random_bytes(8));
ensure(mkdir($root, 0700), 'Unable to create isolated fixture');
mkdir($root . '/config');
$config = [
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => File::class,
            'path' => $root . '/cache/',
        ],
    ],
];
if ($baseline) {
    $config['stores']['file']['type'] = ThinkFile::class;
}
file_put_contents($root . '/config/think-cache.php', '<?php return ' . var_export($config, true) . ';');
file_put_contents($root . '/config/app.php', '<?php return [];');
Webman\Config::load($root . '/config');
ensure(config('think-cache.default') === 'file', 'Isolated file-cache configuration not loaded');
ini_set('error_log', $root . '/warnings.log');
$class = $config['stores']['file']['type'];
$file = new $class($config['stores']['file']);
$reflection = ReflectionCache::cacheConfig();
$workers = [];
$failures = [];
$durations = [];
$started = microtime(true);
$checks = [];

try {
    for ($number = 0; $number < 40; $number++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        ensure($pair !== false, 'Unable to create worker channel');
        $pid = pcntl_fork();
        ensure($pid !== -1, 'Unable to fork worker');
        if ($pid === 0) {
            fclose($pair[0]);
            foreach ($workers as $worker) {
                fclose($worker['socket']);
            }
            $driver = new $class($config['stores']['file']);
            while (($line = fgets($pair[1])) !== false) {
                $action = trim($line);
                if ($action === 'stop') {
                    break;
                }
                $begin = microtime(true);
                $warnings = 0;
                set_error_handler(static function () use (&$warnings): bool {
                    $warnings++;
                    return true;
                });
                try {
                    if ($action === 'cold' || $action === 'hot') {
                        reflectAndAuthorize($number);
                        if ($action === 'cold') {
                            $driver->tag('members')->set('worker-' . $number, ['worker' => $number]);
                        } else {
                            ensure($driver->get('worker-' . $number) === ['worker' => $number], 'Worker value mismatch');
                        }
                    } elseif ($action === 'append') {
                        $driver->tag('members')->set('worker-' . $number, ['worker' => $number]);
                    } elseif ($action === 'clear') {
                        if ($number % 4 === 0) {
                            $driver->tag('members')->clear();
                        } else {
                            $driver->tag('members')->set('worker-' . $number, ['worker' => $number]);
                        }
                    } elseif ($action === 'expire') {
                        if ($number % 2 === 0) {
                            $driver->get('expired');
                        } else {
                            $driver->tag('members')->set('expired', ['fresh' => true], 60);
                        }
                    }
                    ensure($warnings === 0, 'PHP warnings: ' . $warnings);
                    $result = ['ok' => true, 'ms' => (microtime(true) - $begin) * 1000];
                } catch (Throwable $error) {
                    $result = ['ok' => false, 'error' => $error->getMessage(), 'warnings' => $warnings];
                } finally {
                    restore_error_handler();
                }
                fwrite($pair[1], json_encode($result, JSON_THROW_ON_ERROR) . "\n");
            }
            fclose($pair[1]);
            exit(0);
        }
        fclose($pair[1]);
        stream_set_timeout($pair[0], 30);
        $workers[] = ['pid' => $pid, 'socket' => $pair[0]];
    }

    for ($round = 0; $round < $rounds; $round++) {
        $file->clear();
        foreach (['cold', 'hot'] as $phase) {
            foreach (runBatch($workers, $phase) as $result) {
                if (!$result['ok']) {
                    $failures[] = ['round' => $round, 'phase' => $phase] + $result;
                } else {
                    $durations[] = $result['ms'];
                }
            }
            try {
                ensure(count($file->getTagItems($reflection['tag'])) === 5, 'Reflection tag members lost');
                ensure(count($file->getTagItems('members')) === 40, 'Concurrent tag append lost members');
            } catch (Throwable $error) {
                $failures[] = ['round' => $round, 'phase' => $phase, 'error' => $error->getMessage()];
            }
        }
        if (($round + 1) % 100 === 0) {
            fwrite(STDOUT, json_encode(['rounds' => $round + 1, 'failures' => count($failures)]) . "\n");
        }
    }
    if (!$baseline) {
        ensure($failures === [], 'Cold/hot regression failed');
        $checks[] = '100% cold/hot reflection values, role predicates and tag membership';

        // Empty arrays must remain cache hits, including methods without Permission.
        $emptyKey = $reflection['attr'] . md5(NeutralCacheController::class . '::publicAction');
        $emptyPath = $file->getCacheKey($emptyKey);
        touch($emptyPath, time() - 10);
        clearstatcache();
        $mtime = filemtime($emptyPath);
        ReflectionCache::getPermissionAttributes(NeutralCacheController::class, 'publicAction');
        clearstatcache();
        ensure(filemtime($emptyPath) === $mtime, 'Empty permission cache rewritten');
        $checks[] = 'empty-array hot hit';

        // Retain all tag members beyond the generic Driver::push 1000-item limit.
        $file->clear();
        for ($member = 0; $member < 1005; $member++) {
            $file->tag('large')->set('member-' . $member, $member);
        }
        ensure(count($file->getTagItems('large')) === 1005, 'Large tag truncated');
        $file->tag('large')->clear();
        ensure($file->get('member-0') === null && $file->get('member-1004') === null, 'Large tag clear left stale members');
        $checks[] = '1005 members and complete invalidation';

        for ($round = 0; $round < 100; $round++) {
            foreach (runBatch($workers, 'clear') as $result) {
                ensure($result['ok'], 'Concurrent clear failed');
            }
            $members = $file->getTagItems('members');
            for ($number = 0; $number < 40; $number++) {
                if ($file->get('worker-' . $number) !== null) {
                    ensure(in_array($file->getCacheKey('worker-' . $number), $members, true), 'Concurrent clear left untracked value');
                }
            }
            $file->tag('members')->clear();
            $file->tag('members')->clear();
            ensure($file->getTagItems('members') === [], 'Clear not idempotent');
        }
        $checks[] = '100 concurrent clear/write rounds; no untracked live values';

        for ($round = 0; $round < 100; $round++) {
            $file->set('expired', ['old' => true], 1);
            touch($file->getCacheKey('expired'), time() - 3);
            foreach (runBatch($workers, 'expire') as $result) {
                ensure($result['ok'], 'Concurrent expiry failed');
            }
            ensure($file->get('expired') === ['fresh' => true], 'Expiry deleted replacement value');
        }
        $checks[] = '100 concurrent expiry/replacement rounds';

        foreach ([false, 'broken', ['invalid' => []]] as $broken) {
            $file->tag('old')->set('stale-permission', ['stale' => true]);
            $file->set($file->getTagKey('members'), $broken);
            foreach (runBatch($workers, 'append') as $result) {
                ensure($result['ok'], 'Corrupt tag recovery failed');
            }
            ensure(count($file->getTagItems('members')) === 40, 'Recovery lost members');
            ensure($file->get('stale-permission') === null, 'Recovery retained untracked stale permissions');
        }
        $file->tag('old')->set('stale-permission', true);
        file_put_contents($file->getCacheKey($file->getTagKey('members')), '<?php // truncated');
        foreach (runBatch($workers, 'append') as $result) {
            ensure($result['ok'], 'Truncated tag recovery failed');
        }
        ensure(count($file->getTagItems('members')) === 40, 'Truncated tag recovery lost members');
        $warnings = file_get_contents($root . '/warnings.log');
        ensure(substr_count($warnings, 'Corrupt file-cache tag rebuilt') === 4, 'Recovery warning not once per corrupt tag');
        $checks[] = 'scalar, malformed-array and truncated tags recover with one sanitized warning each';

        // The sidecar lock survives a whole-store clear and remains shared.
        $file->clear();
        foreach (runBatch($workers, 'append') as $result) {
            ensure($result['ok'], 'Write after store clear failed');
        }
        ensure(count($file->getTagItems('members')) === 40, 'Store clear split lock ownership');
        $checks[] = 'store clear preserves common lock';
    }
} finally {
    foreach ($workers as $worker) {
        fwrite($worker['socket'], "stop\n");
        fclose($worker['socket']);
        pcntl_waitpid($worker['pid'], $status);
    }
}

sort($durations);
$percentile = static fn (float $p): float => round($durations[min(count($durations) - 1, (int) floor(count($durations) * $p))] ?? 0, 3);
$report = [
    'mode' => $baseline ? 'upstream-baseline' : 'host-file-driver',
    'php' => PHP_VERSION,
    'workers' => count($workers),
    'cold_rounds' => $rounds,
    'hot_rounds' => $rounds,
    'worker_batches' => $rounds * 80,
    'failures' => count($failures),
    'failure_samples' => array_slice($failures, 0, 5),
    'elapsed_seconds' => round(microtime(true) - $started, 3),
    'batch_ms' => ['p50' => $percentile(.50), 'p95' => $percentile(.95), 'p99' => $percentile(.99)],
    'checks' => $checks,
    'fixture_path' => $root,
    'scope' => 'PHP processes, real ReflectionCache and CheckAuth permission predicate; no HTTP/JWT/database/installed-host claim',
];
file_put_contents($root . '/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$raceReproduced = array_filter($failures, static fn (array $failure): bool =>
    str_contains($failure['error'], 'only array cache can be push')
    || str_contains($failure['error'], 'tag members lost')
    || str_contains($failure['error'], 'tag append lost members')
);
exit($baseline ? ($raceReproduced === [] ? 1 : 0) : ($failures === [] ? 0 : 1));
