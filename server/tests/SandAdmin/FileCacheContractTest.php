<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use plugin\sandadmin\app\cache\driver\File;
use Webman\ThinkCache\driver\File as ThinkFile;

$root = sys_get_temp_dir() . '/sandadmin-file-cache-contract-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
ini_set('error_log', $root . '/warnings.log');
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

foreach ([false, true] as $compress) {
    $options = ['path' => $root . '/format-' . (int) $compress, 'prefix' => 'fixture', 'data_compress' => $compress];
    $native = new ThinkFile($options);
    $file = new File($options);
    $native->tag('format')->set('native', ['slug' => 'neutral:read'], 60);
    $assert($file->get('native') === ['slug' => 'neutral:read'], 'Native file format unreadable');
    $file->tag('format')->set('host', ['slug' => 'neutral:write'], 60);
    $assert($native->get('host') === ['slug' => 'neutral:write'], 'Host file format diverged');
    $assert(count($native->getTagItems('format')) === 2, 'Native tag format diverged');
    $file->tag('format')->clear();
    $assert($file->get('host') === null && $file->get('native') === null, 'Format-compatible invalidation failed');
    foreach (['truncated', 'invalid-payload'] as $corruption) {
        $file->tag('format')->set('old-permission', ['stale' => true]);
        $warningsBefore = is_file($root . '/warnings.log')
            ? substr_count(file_get_contents($root . '/warnings.log'), 'Corrupt file-cache tag rebuilt') : 0;
        $tagPath = $file->getCacheKey($file->getTagKey('format'));
        $payload = $corruption === 'truncated' ? '<?php // truncated'
            : substr(file_get_contents($tagPath), 0, 32) . 'invalid serialized or compressed bytes';
        file_put_contents($tagPath, $payload);
        $file->tag('format')->clear();
        $assert($file->get('old-permission') === null, 'Corrupt tag clear retained stale permission');
        $assert($file->getTagItems('format') === [], 'Corrupt tag clear not idempotent');
        $assert(substr_count(file_get_contents($root . '/warnings.log'), 'Corrupt file-cache tag rebuilt') === $warningsBefore + 1, 'Corrupt tag warning missing or repeated');
    }
    $warningsBefore = file_get_contents($root . '/warnings.log');
    $assert($file->getTagItems('missing') === [], 'Missing tag not empty');
    $file->set($file->getTagKey('expired'), [], 1);
    touch($file->getCacheKey($file->getTagKey('expired')), time() - 3);
    $assert($file->getTagItems('expired') === [], 'Expired tag not empty');
    $assert(file_get_contents($root . '/warnings.log') === $warningsBefore, 'Missing/expired tag reported corruption');
}

$options = ['path' => $root . '/remember'];
$file = new File($options);
$calls = 0;
$value = $file->tag('memo')->remember('memo', static function () use (&$calls): array {
    $calls++;
    return ['permission' => 'neutral:read'];
}, 60);
$assert($value === ['permission' => 'neutral:read'] && $calls === 1, 'Remember miss failed');
$before = file_get_contents($file->getCacheKey('memo'));
$file->tag('memo')->remember('memo', static function () use (&$calls): void { $calls++; }, 1);
$assert($calls === 1 && file_get_contents($file->getCacheKey('memo')) === $before, 'Remember hit invoked callback or changed TTL');

$file->set($file->getTagKey('memo'), false);
$value = $file->tag('memo')->remember('memo', ['wrong' => true], 60);
$assert($value === ['permission' => 'neutral:read'] && $file->get('memo') === $value, 'Remember recovery lost new value');
$assert(count($file->getTagItems('memo')) === 1, 'Remember recovery lost tag');

try {
    $file->tag('memo')->remember('exception', static function (): never {
        throw new RuntimeException('expected callback failure');
    });
    $assert(false, 'Remember swallowed callback exception');
} catch (RuntimeException $error) {
    $assert($error->getMessage() === 'expected callback failure', 'Unexpected callback error');
}
$assert($file->tag('memo')->set('after-exception', true), 'Exception retained lock');

// The closure can wait for another process that uses the same cache store.
$file->tag('memo')->remember('cross-process', static function () use ($options, $assert): string {
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $assert($pair !== false, 'Socket unavailable');
    $pid = pcntl_fork();
    $assert($pid !== -1, 'Fork failed');
    if ($pid === 0) {
        fclose($pair[0]);
        try {
            (new File($options))->set('callback-child', true);
            fwrite($pair[1], "ok\n");
            exit(0);
        } catch (Throwable) {
            fwrite($pair[1], "failed\n");
            exit(1);
        }
    }
    fclose($pair[1]);
    stream_set_timeout($pair[0], 3);
    $result = fgets($pair[0]);
    fclose($pair[0]);
    pcntl_waitpid($pid, $status);
    $assert($result === "ok\n" && pcntl_wexitstatus($status) === 0, 'Remember callback holds the store lock');
    return 'complete';
});
$assert($file->get('cross-process') === 'complete', 'Remember did not persist callback result');

try {
    $file->synchronized(static function (): never { throw new RuntimeException('expected lock failure'); });
} catch (RuntimeException) {
}
$assert((new File($options))->set('lock-released', true), 'Failed operation did not release lock');
fwrite(STDOUT, "FileCacheContractTest passed ({$checks} assertions); fixture: {$root}\n");
