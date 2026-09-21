<?php
// Legacy recovery regression only; normal lifecycle is covered by UpstreamPostgresLifecycleTest.php.

declare(strict_types=1);

namespace {
    $testRoot = sys_get_temp_dir() . '/sandpackage-diagnostic-v13-' . bin2hex(random_bytes(6));

    function runtime_path(): string
    {
        global $testRoot;
        return $testRoot . '/runtime';
    }

    function base_path(): string
    {
        global $testRoot;
        return $testRoot . '/server';
    }

    function env(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}

namespace Saithink\Saipackage\service {
    final class Server
    {
        /** @return array<string,mixed> */
        public static function getIni(string $directory): array
        {
            $file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini';
            return is_file($file) ? (parse_ini_file($file, false, INI_SCANNER_TYPED) ?: []) : [];
        }

        /** @param array<string,mixed> $info */
        public static function setIni(string $directory, array $info): bool
        {
            $lines = [];
            foreach ($info as $key => $value) {
                $lines[] = $key . ' = ' . var_export($value, true);
            }
            return file_put_contents(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini', implode(PHP_EOL, $lines) . PHP_EOL) !== false;
        }
    }

    final class Version {}
    final class Filesystem {}
    final class Depends {}
}

namespace plugin\sandadmin\exception {
    class ApiException extends \RuntimeException {}
}

namespace plugin\sandadmin\app\cache {
    final class UserMenuCache { public static function clearMenuCache(): void {} }
}

namespace support {
    final class Log
    {
        /** @var list<array{message:string,context:array<string,mixed>}> */
        public static array $entries = [];

        /** @param array<string,mixed> $context */
        public static function error(string $message, array $context = []): void
        {
            self::$entries[] = ['message' => $message, 'context' => $context];
        }
    }
}

namespace {
    use plugin\sandpackage\app\logic\LegacyInstallLogic as InstallLogic;
    use Saithink\Saipackage\service\Server;
    use support\Log;

    require dirname(__DIR__) . '/server/plugin/sandpackage/app/service/PluginStorage.php';
    require dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/LegacyInstallLogic.php';

    function expect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    /** @param array<string,mixed> $info */
    function writeInfo(string $directory, array $info): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('cannot create package directory');
        }
        expect(Server::setIni($directory, $info), 'cannot write registry info');
    }

    function databaseException(string|int $sqlState, string $message): \PDOException
    {
        $error = new \PDOException($message);
        $code = new \ReflectionProperty(\Exception::class, 'code');
        $code->setAccessible(true);
        $code->setValue($error, (string) $sqlState);
        return $error;
    }

    function writeRuntimeFixture(string $path, string $contents): void
    {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('cannot create runtime fixture directory');
        }
        expect(file_put_contents($path, $contents) !== false, 'cannot write runtime fixture');
    }

    $registry = runtime_path() . '/sandpackage/sand-iam/';
    writeInfo($registry, [
        'app' => 'sand-iam',
        'version' => '0.7.0',
        'state' => InstallLogic::WAIT_INSTALL,
        'update' => 1,
        'stage' => 'database_update',
    ]);
    writeRuntimeFixture($registry . 'plugin/sand-iam/Runtime.php', "<?php\n// fixture\n");
    writeRuntimeFixture($registry . 'sandadmin-artd/src/views/plugin/sand-iam/index.vue', "<template><div>fixture</div></template>\n");
    writeRuntimeFixture(base_path() . '/plugin/sand-iam/Runtime.php', "<?php\n// fixture\n");
    writeRuntimeFixture(dirname(base_path()) . '/sandadmin-artd/src/views/plugin/sand-iam/index.vue', "<template><div>fixture</div></template>\n");
    $logic = new InstallLogic('sand-iam');
    $recordFailure = new \ReflectionMethod(InstallLogic::class, 'recordFailure');
    $recordFailure->setAccessible(true);
    $publicMessage = new \ReflectionMethod(InstallLogic::class, 'publicFailureMessage');
    $publicMessage->setAccessible(true);

    foreach ([
        'P0001' => 'RAISE EXCEPTION secret=demo-token at /private/upgrade.sql',
        '23505' => 'duplicate key in INSERT INTO sand_iam_secret password=hidden at /srv/plugin/update.sql',
    ] as $sqlState => $message) {
        $sqlState = (string) $sqlState;
        Log::$entries = [];
        $diagnosticId = $recordFailure->invoke($logic, 'database_update', databaseException($sqlState, $message));
        expect(is_string($diagnosticId) && preg_match('/^SP-\d{14}-[a-f0-9]{12}$/', $diagnosticId) === 1, 'diagnostic id is not opaque and auditable');
        $stored = Server::getIni($registry);
        $userMessage = (string) ($stored['last_error'] ?? '');
        expect(($stored['diagnostic_id'] ?? null) === $diagnosticId, 'registry did not persist diagnostic id');
        expect(($stored['last_error_code'] ?? null) === 'SANDPACKAGE_DATABASE_UPDATE_FAILED', 'registry did not persist the safe failure code');
        expect(str_contains($userMessage, '数据库升级没有完成') && str_contains($userMessage, $diagnosticId), 'registry did not retain the human message and diagnostic id');
        expect(!str_contains($userMessage, 'INSERT') && !str_contains($userMessage, '/private/') && !str_contains($userMessage, 'password=') && !str_contains($userMessage, 'secret='), 'user message leaked SQL, path, or credential text');
        $pageMessage = $publicMessage->invoke(null, 'database_update', $diagnosticId);
        expect($pageMessage === '数据库升级没有完成，诊断编号 ' . $diagnosticId . '。请根据诊断编号查看服务端日志后处理', 'database update page message is not human-readable or auditable');
        expect(count(Log::$entries) === 1, 'server failure log was not written');
        $log = Log::$entries[0];
        expect($log['message'] === 'SandPackage operation failed', 'server log message changed unexpectedly');
        expect(($log['context']['diagnostic_id'] ?? null) === $diagnosticId && ($log['context']['sqlstate'] ?? null) === $sqlState, 'server log lacks diagnostic id or SQLSTATE');
        expect(($log['context']['exception_message'] ?? null) === $message, 'server log lacks original exception context');
    }

    $markInstalled = new \ReflectionMethod(InstallLogic::class, 'markInstalled');
    $markInstalled->setAccessible(true);
    $markInstalled->invoke($logic);
    $stored = Server::getIni($registry);
    expect(!array_key_exists('diagnostic_id', $stored) && !array_key_exists('last_error_code', $stored) && ($stored['last_error'] ?? null) === '', 'normal success did not clear old failure diagnostics');

    echo "SandPackage v13 database diagnostic contract passed\n";
}
