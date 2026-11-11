<?php
// 用法：php tests/security/ProductionDebugTest.php（在 server/ 下；无需 vendor）
declare(strict_types=1);

namespace Webman { if (!interface_exists(Bootstrap::class)) { interface Bootstrap { public static function start($worker); } } }
namespace {
    $GLOBALS['cfg'] = [];
    function config($k, $d = null) { return $GLOBALS['cfg'][$k] ?? $d; }
    function env($k, $d = null) { $v = getenv($k); return $v === false ? $d : $v; }
    function base_path($p = true) { return __DIR__; }
    require __DIR__ . '/../../app/bootstrap/ProductionDebugGuard.php';
    use app\bootstrap\ProductionDebugGuard as G;
    $fail = 0;
    $check = function (bool $ok, string $n) use (&$fail) { echo ($ok ? 'PASS ' : 'FAIL ') . "$n\n"; $fail += $ok ? 0 : 1; };
    $throws = function (callable $f) { try { $f(); return false; } catch (RuntimeException) { return true; } };

    // config/app.php 默认 debug=false，并正确解析字符串
    putenv('APP_DEBUG');
    $check((require __DIR__ . '/../../config/app.php')['debug'] === false, 'config/app.php 未设 APP_DEBUG 时 debug=false');
    putenv('APP_DEBUG=false');
    $check((require __DIR__ . '/../../config/app.php')['debug'] === false, 'APP_DEBUG="false" 解析为 false');
    putenv('APP_DEBUG=true');
    $check((require __DIR__ . '/../../config/app.php')['debug'] === true, 'APP_DEBUG="true" 解析为 true');
    putenv('APP_DEBUG');

    $check($throws(fn () => G::assertSafe('production', true)), 'production + debug=true 拒绝');
    $check($throws(fn () => G::assertSafe('PROD', '1')), 'PROD + "1" 拒绝');
    $check(!$throws(fn () => G::assertSafe('production', false)), 'production + debug=false 通过');
    $check(!$throws(fn () => G::assertSafe('development', true)), 'development + debug=true 通过（开发区分）');

    putenv('APP_ENV=production');
    $GLOBALS['cfg']['app.debug'] = true;
    $halted = false;
    $halt = function () use (&$halted) { $halted = true; };
    $threw = $throws(fn () => G::start(new stdClass(), $halt));
    $check($threw && $halted, 'start()：生产 + debug 抛错并停止主进程');
    $GLOBALS['cfg']['app.debug'] = false;
    $check(!$throws(fn () => G::start(new stdClass(), fn () => null)), 'start()：生产 + debug=false 正常');
    putenv('APP_ENV');

    $boot = require __DIR__ . '/../../config/bootstrap.php';
    $check(($boot[0] ?? null) === G::class, 'ProductionDebugGuard 为首个 bootstrap');
    echo $fail ? "$fail failed\n" : "all passed\n";
    exit($fail ? 1 : 0);
}
