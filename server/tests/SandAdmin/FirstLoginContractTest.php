<?php

declare(strict_types=1);

/**
 * Static contract coverage for a clean SandAdmin PostgreSQL installation.
 * It deliberately does not connect to a local database or modify an instance.
 */

$repositoryRoot = dirname(__DIR__, 3);
$firstLoginAssertionCount = 0;

/** @return string */
function firstLoginRead(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }

    return $content;
}

function firstLoginAssert(bool $condition, string $message): void
{
    global $firstLoginAssertionCount;

    $firstLoginAssertionCount++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$sqlFiles = [
    $repositoryRoot . '/server/plugin/sandadmin/db/sandadmin-6.0.pgsql',
    $repositoryRoot . '/server/plugin/sandadmin/db/sandadmin-pure.pgsql',
];

foreach ($sqlFiles as $sqlFile) {
    $sql = firstLoginRead($sqlFile);
    $matched = preg_match(
        '/INSERT INTO "sand_system_user" VALUES \\(1, \'admin\', \'([^\']+)\'/',
        $sql,
        $matches
    );
    firstLoginAssert($matched === 1, basename($sqlFile) . ' does not seed admin');
    firstLoginAssert(password_verify('123456', $matches[1]), basename($sqlFile) . ' admin hash does not match 123456');
}

$installController = firstLoginRead($repositoryRoot . '/server/plugin/sandadmin/app/controller/InstallController.php');
firstLoginAssert(str_contains($installController, "'initial_admin' => ["), 'Install response does not expose the initial-admin contract');
firstLoginAssert(str_contains($installController, "'username' => 'admin'"), 'Install response username diverges from SQL seed');
firstLoginAssert(str_contains($installController, "'password' => '123456'"), 'Install response password diverges from SQL seed');
firstLoginAssert(str_contains($installController, "if (is_file(\$env))"), 'Install guard for existing instances is missing');
firstLoginAssert(str_contains($installController, "if (\$installed)"), 'Install guard for an initialized database is missing');

$installPage = firstLoginRead($repositoryRoot . '/server/plugin/sandadmin/app/view/install/index.html');
foreach (['首次登录凭据', 'initialAdminUsername', 'initialAdminPassword', 'initial_admin', '请在首次登录后立即修改默认密码'] as $needle) {
    firstLoginAssert(str_contains($installPage, $needle), 'Install success page is missing ' . $needle);
}

$loginController = firstLoginRead($repositoryRoot . '/server/plugin/sandadmin/app/controller/LoginController.php');
firstLoginAssert(str_contains($loginController, "return \$this->fail('验证码错误');"), 'Captcha failures are not returned precisely');

$userLogic = firstLoginRead($repositoryRoot . '/server/plugin/sandadmin/app/logic/system/SystemUserLogic.php');
firstLoginAssert(str_contains($userLogic, '账号或密码错误，请重新输入!'), 'Credential failure contract changed unexpectedly');
firstLoginAssert(str_contains($userLogic, '您已被禁止登录!'), 'Disabled-account failure contract is missing');

$httpClient = firstLoginRead($repositoryRoot . '/sandadmin-artd/src/utils/http/index.ts');
firstLoginAssert(str_contains($httpClient, "throw createHttpError(message || \$t('httpMsg.requestFailed'), code)"), 'Frontend no longer preserves API error messages');

fwrite(STDOUT, "FirstLoginContractTest.php passed ({$firstLoginAssertionCount} assertions)\n");
