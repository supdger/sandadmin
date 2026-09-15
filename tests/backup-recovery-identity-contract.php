<?php
// Legacy recovery regression only; normal lifecycle is covered by UpstreamPostgresLifecycleTest.php.

declare(strict_types=1);

namespace {
    $testRoot = getenv('SANDPACKAGE_V15_ROOT') ?: sys_get_temp_dir() . '/sandpackage-upload-sanitize-v15-' . bin2hex(random_bytes(6));

    function runtime_path(): string { global $testRoot; return $testRoot . '/runtime'; }
    function base_path(): string { global $testRoot; return $testRoot . '/server'; }
    function env(string $key, mixed $default = null): mixed { return $default; }
    function config(string $key, mixed $default = null): mixed
    {
        return in_array($key, ['plugin.sandadmin.app.version', 'plugin.sandpackage.app.version'], true) ? '6.0.11' : $default;
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

        /** @return list<array<string,mixed>> */
        public static function installedList(string $directory): array
        {
            $items = [];
            foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $path) {
                if (in_array(basename($path), ['backups', 'locks', 'uploads', 'quarantine'], true)) {
                    continue;
                }
                $info = self::getIni($path);
                if ($info !== []) {
                    $items[] = $info;
                }
            }
            return $items;
        }
    }

    final class Version { public static function compare(string $minimum, string $actual): bool { return version_compare($actual, $minimum, '>='); } }
    final class Filesystem
    {
        public static function dirIsEmpty(string $directory): bool { return !is_dir($directory) || count(scandir($directory) ?: []) <= 2; }
        public static function unzip(string $archive, string $target): void
        {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true || !$zip->extractTo($target)) {
                throw new \RuntimeException('cannot extract upload fixture');
            }
            $zip->close();
        }
        public static function delDir(string $directory): void
        {
            if (!is_dir($directory)) { return; }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) { $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
            rmdir($directory);
        }
    }
    final class Depends {}
}

namespace plugin\sandadmin\exception { class ApiException extends \RuntimeException {} }
namespace plugin\sandadmin\app\cache { final class UserMenuCache { public static function clearMenuCache(): void {} } }
namespace plugin\sandadmin\app\middleware { final class SystemLog {} final class CheckLogin {} }
namespace plugin\sandadmin\basic {
    abstract class BaseController
    {
        protected int $adminId = 1;
        public function __construct() {}
        public function success(array $data): \support\Response { return new \support\Response($data); }
    }
}
namespace support {
    final class Request {}
    final class Response { /** @param array<string,mixed> $data */ public function __construct(public array $data) {} }
    final class Log { public static function error(string $message, array $context = []): void {} }
}
namespace support\annotation { #[\Attribute(\Attribute::TARGET_CLASS)] final class Middleware { public function __construct(string ...$middlewares) {} } }

namespace {
    use plugin\sandpackage\app\controller\InstallController;
    use plugin\sandpackage\app\logic\LegacyInstallLogic as InstallLogic;
    use Saithink\Saipackage\service\Server;

    require dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/LegacyInstallLogic.php';
    require __DIR__ . '/fixtures/InstallController.php';

    final class ExitFaultingInstallLogic extends InstallLogic
    {
        protected function candidateFault(string $point): void
        {
            if ($point === 'candidate.app.rename.committed') {
                exit(97);
            }
        }
    }

    function expect(bool $condition, string $message): void
    {
        if (!$condition) { throw new \RuntimeException($message); }
    }

    function removeTree(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($directory);
    }

    function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('cannot create fixture directory');
        }
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('cannot write fixture file');
        }
        chmod($path, 0644);
    }

    function archive(string $path): void
    {
        $zip = new \ZipArchive();
        expect($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 'cannot create upload fixture');
        $zip->addFromString('info.ini', implode(PHP_EOL, [
            'app = sand-iam',
            'title = Fixture',
            'about = Fixture',
            'author = Fixture',
            'version = 0.7.0',
            'support = 6.x',
            'state = 8',
            'last_error_code = ATTACKER_CONTROLLED',
            'diagnostic_id = SP-ATTACKER-CONTROLLED',
            '',
        ]));
        $zip->addFromString('plugin/sand-iam/info.ini', "app = sand-iam\nversion = 0.7.0\n");
        $zip->addFromString('plugin/sand-iam/Runtime.php', "<?php\n// candidate runtime\n");
        $zip->addFromString('sandadmin-artd/src/views/plugin/sand-iam/index.vue', '<template><div>candidate</div></template>');
        $zip->addFromString('install.sql', '-- install candidate');
        $zip->addFromString('update.sql', '-- update candidate');
        $zip->addFromString('uninstall.sql', '-- uninstall candidate');
        expect($zip->close(), 'cannot close upload fixture');
    }

    function crashUpload(string $zip): int
    {
        $process = proc_open([PHP_BINARY, __FILE__, '--crash-upload'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge($_ENV, [
            'SANDPACKAGE_V15_ROOT' => $GLOBALS['testRoot'],
            'SANDPACKAGE_V15_ARCHIVE' => $zip,
        ]));
        expect(is_resource($process), 'cannot start crash upload worker');
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 97) {
            fwrite(STDERR, $stdout . $stderr);
        }
        return $exitCode;
    }

    function prepareRegisteredOldPackage(): void
    {
        $registry = runtime_path() . '/sandpackage/sand-iam';
        $backups = runtime_path() . '/sandpackage/backups';
        if (!is_dir($backups) && !mkdir($backups, 0755, true) && !is_dir($backups)) {
            throw new \RuntimeException('cannot create backup registry');
        }
        if (!is_dir($registry) && !mkdir($registry, 0755, true) && !is_dir($registry)) {
            throw new \RuntimeException('cannot create old registry');
        }
        $old = [
            'app' => 'sand-iam', 'title' => 'Fixture', 'about' => 'Fixture', 'author' => 'Fixture',
            'version' => '0.6.0', 'state' => InstallLogic::INSTALLED, 'stage' => 'registered',
        ];
        Server::setIni($registry, $old);
        writeFile($registry . '/plugin/sand-iam/info.ini', "app = sand-iam\nversion = 0.6.0\n");
        writeFile($registry . '/plugin/sand-iam/Runtime.php', "<?php\n// old runtime\n");
        writeFile($registry . '/sandadmin-artd/src/views/plugin/sand-iam/index.vue', '<template><div>old</div></template>');
        writeFile($registry . '/install.sql', '-- install old');
        writeFile($registry . '/update.sql', '-- update old');
        writeFile($registry . '/uninstall.sql', '-- uninstall old');
        $logic = new InstallLogic('sand-iam');
        foreach ($logic->getAllowedPath() as $source => $target) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                $path = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                if ($item->isDir()) {
                    if (!is_dir($path)) { mkdir($path, 0755, true); }
                } else {
                    writeFile($path, (string) file_get_contents($item->getPathname()));
                }
            }
        }
        $method = new \ReflectionMethod(InstallLogic::class, 'verifyDeploymentMatchesPackage');
        $method->setAccessible(true);
        $old['registration_manifest'] = $method->invoke($logic);
        Server::setIni($registry, $old);
    }

    /** @return array<string,mixed> */
    function controllerRow(): array
    {
        $response = (new InstallController())->index(new \support\Request());
        $rows = $response->data['data'] ?? null;
        expect(is_array($rows) && count($rows) === 1 && is_array($rows[0]), 'controller did not serialize uploaded registry row');
        return $rows[0];
    }

    function assertControllerHasNoAttackerFields(string $scenario): void
    {
        $stored = (new InstallLogic('sand-iam'))->getInfo();
        expect(!array_key_exists('last_error_code', $stored) && !array_key_exists('diagnostic_id', $stored), $scenario . ': registry leaked attacker diagnostic fields');
        expect(($stored['last_error'] ?? null) === '', $scenario . ': registry retained attacker error text');
        $row = controllerRow();
        expect(!array_key_exists('last_error_code', $row) && !array_key_exists('diagnostic_id', $row), $scenario . ': controller leaked attacker diagnostic fields');
        expect(($row['last_error'] ?? null) === '', $scenario . ': controller exposed attacker error text');
    }

    if (($argv[1] ?? '') === '--crash-upload') {
        (new ExitFaultingInstallLogic())->uploadFromPath((string) getenv('SANDPACKAGE_V15_ARCHIVE'));
        exit(0);
    }

    foreach ([false => 'ready', true => 'registration_ready'] as $runtimeExists => $expectedStage) {
        global $testRoot;
        removeTree($testRoot);
        mkdir($testRoot, 0755, true);
        if ($runtimeExists) {
            $runtime = base_path() . '/plugin/sand-iam';
            mkdir($runtime, 0755, true);
        }
        $zip = $testRoot . '/malicious-info.zip';
        archive($zip);
        (new InstallLogic())->uploadFromPath($zip);
        $stored = (new InstallLogic('sand-iam'))->getInfo();
        expect(($stored['stage'] ?? null) === $expectedStage, $expectedStage . ': actual upload did not reach expected stage');
        expect(!array_key_exists('last_error_code', $stored) && !array_key_exists('diagnostic_id', $stored), $expectedStage . ': uploaded registry retained attacker diagnostic fields');
        expect(($stored['last_error'] ?? null) === '', $expectedStage . ': uploaded registry did not reset error text');
        $row = controllerRow();
        expect(!array_key_exists('last_error_code', $row) && !array_key_exists('diagnostic_id', $row), $expectedStage . ': controller leaked attacker diagnostic fields');
        expect(($row['last_error'] ?? null) === '', $expectedStage . ': controller exposed stale uploaded error text');
    }

    // The process exits immediately after the candidate rename. The directory
    // visible to the real list controller must already be free of upload-owned
    // diagnostics, even before any recovery method runs.
    removeTree($testRoot);
    mkdir($testRoot, 0755, true);
    $initialArchive = $testRoot . '/initial-crash.zip';
    archive($initialArchive);
    expect(crashUpload($initialArchive) === 97, 'initial ready crash worker did not exit at candidate rename');
    assertControllerHasNoAttackerFields('initial ready rename crash');
    $initial = new InstallLogic('sand-iam');
    expect($initial->getInstallState() === InstallLogic::WAIT_INSTALL, 'initial ready rename crash left an unusable registry state');

    removeTree($testRoot);
    mkdir($testRoot, 0755, true);
    prepareRegisteredOldPackage();
    $upgradeArchive = $testRoot . '/upgrade-crash.zip';
    archive($upgradeArchive);
    expect(crashUpload($upgradeArchive) === 97, 'upgrade ready crash worker did not exit at candidate rename');
    assertControllerHasNoAttackerFields('upgrade ready rename crash');
    (new InstallLogic('sand-iam'))->recoverPendingCandidates();
    $recovered = (new InstallLogic('sand-iam'))->getInfo();
    expect(!array_key_exists('last_error_code', $recovered) && !array_key_exists('diagnostic_id', $recovered), 'upgrade recovery reintroduced attacker diagnostics');
    expect(($recovered['version'] ?? null) === '0.6.0' && (int) ($recovered['state'] ?? -1) === InstallLogic::INSTALLED, 'upgrade rename crash did not restore the verified 0.6 package');
    expect(!is_file(runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json'), 'verified upgrade recovery did not clear its journal');

    // A ready-to-move journal must not restore an old backup until that backup
    // still matches its original package, registry, and deployed-runtime
    // identity. Each corruption remains in place with its journal intact.
    foreach (['update_rewrite', 'update_delete', 'lifecycle_add', 'info_version', 'info_state', 'info_registration', 'runtime_drift'] as $fault) {
        removeTree($testRoot);
        mkdir($testRoot, 0755, true);
        prepareRegisteredOldPackage();
        $faultArchive = $testRoot . '/upgrade-' . $fault . '.zip';
        archive($faultArchive);
        expect(crashUpload($faultArchive) === 97, $fault . ': crash worker did not exit at candidate rename');
        $backups = glob(runtime_path() . '/sandpackage/backups/sand-iam-package-*', GLOB_ONLYDIR) ?: [];
        expect(count($backups) === 1, $fault . ': interrupted upgrade has no unique backup');
        $backup = $backups[0];
        $candidate = runtime_path() . '/sandpackage/sand-iam';
        $journal = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        if ($fault === 'update_rewrite') {
            writeFile($backup . '/update.sql', '-- rewritten update');
        } elseif ($fault === 'update_delete') {
            expect(unlink($backup . '/update.sql'), $fault . ': cannot delete backup update.sql');
        } elseif ($fault === 'lifecycle_add') {
            writeFile($backup . '/extra.sql', '-- unexpected lifecycle file');
        } elseif ($fault === 'runtime_drift') {
            writeFile(base_path() . '/plugin/sand-iam/Runtime.php', "<?php\n// runtime drift\n");
        } else {
            $info = Server::getIni($backup);
            if ($fault === 'info_version') {
                $info['version'] = '0.6.1';
            } elseif ($fault === 'info_state') {
                $info['state'] = InstallLogic::WAIT_INSTALL;
            } else {
                $info['registration_manifest'] = str_repeat('0', 64);
            }
            expect(Server::setIni($backup, $info), $fault . ': cannot corrupt backup registry info');
        }
        $rejected = false;
        try {
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        } catch (\Throwable) {
            $rejected = true;
        }
        expect($rejected, $fault . ': recovery accepted corrupted backup evidence');
        expect(is_file($journal), $fault . ': recovery removed journal for corrupted backup');
        expect(is_dir($backup) && is_dir($candidate), $fault . ': recovery moved corrupted backup or candidate');
    }

    global $testRoot;
    removeTree($testRoot);
    echo "SandPackage v16 backup recovery identity contract passed\n";
}
