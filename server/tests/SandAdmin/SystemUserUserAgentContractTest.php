<?php

declare(strict_types=1);

/**
 * The login event records user-agent details after both successful and failed
 * login attempts. Proxies and programmatic clients may omit that header.
 */

$repositoryRoot = dirname(__DIR__, 3);
require $repositoryRoot . '/vendor/autoload.php';

use plugin\sandadmin\app\event\SystemUser;

$assertionCount = 0;

function systemUserUserAgentAssert(bool $condition, string $message): void
{
    global $assertionCount;

    $assertionCount++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$event = new SystemUser();
$browser = new ReflectionMethod(SystemUser::class, 'getBrowser');
$os = new ReflectionMethod(SystemUser::class, 'getOs');
$eventSource = file_get_contents($repositoryRoot . '/server/plugin/sandadmin/app/event/SystemUser.php');

systemUserUserAgentAssert($eventSource !== false, 'Unable to read the login event source');
systemUserUserAgentAssert(
    str_contains($eventSource, "(string) \$request->header('user-agent', '')"),
    'Login event must normalize a missing user-agent header before classification'
);
systemUserUserAgentAssert($browser->invoke($event, '') === 'Other', 'Missing user-agent must produce the fallback browser value');
systemUserUserAgentAssert($os->invoke($event, '') === 'Other', 'Missing user-agent must produce the fallback operating-system value');
systemUserUserAgentAssert($browser->invoke($event, 'Mozilla/5.0 Chrome/120.0') === 'Chrome', 'Chrome user-agent classification changed unexpectedly');
systemUserUserAgentAssert($os->invoke($event, 'Mozilla/5.0 (Macintosh; Intel Mac OS X)') === 'Mac', 'Mac user-agent classification changed unexpectedly');

fwrite(STDOUT, "SystemUserUserAgentContractTest.php passed ({$assertionCount} assertions)\n");
