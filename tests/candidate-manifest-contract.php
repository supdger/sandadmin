<?php

declare(strict_types=1);

namespace {
    $testRoot = getenv('SANDPACKAGE_V17_ROOT') ?: sys_get_temp_dir() . '/sandpackage-candidate-manifest-v17-' . bin2hex(random_bytes(6));

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
    use plugin\sandpackage\app\logic\InstallLogic;
    use Saithink\Saipackage\service\Server;

    require dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/InstallLogic.php';
    require __DIR__ . '/fixtures/InstallController.php';

    final class ExitFaultingInstallLogic extends InstallLogic
    {
        protected function candidateFault(string $point): void
        {
            if ($point === 'candidate.candidate_info_written.committed') {
                exit(97);
            }
        }
    }

    final class ParentSyncThrowingInstallLogic extends InstallLogic
    {
        protected function candidateFault(string $point): void
        {
            if ($point === 'candidate.complete.parent_sync') {
                throw new \RuntimeException('simulated committed-parent-sync failure');
            }
        }
    }

    final class ExitDiscardFaultingInstallLogic extends InstallLogic
    {
        protected function candidateFault(string $point): void
        {
            if ($point === 'discard.quarantine.rename.committed') {
                exit(96);
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
            'SANDPACKAGE_V17_ROOT' => $GLOBALS['testRoot'],
            'SANDPACKAGE_V17_ARCHIVE' => $zip,
        ]));
        expect(is_resource($process), 'cannot start crash upload worker');
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return proc_close($process);
    }

    function crashDiscard(): int
    {
        $process = proc_open([PHP_BINARY, __FILE__, '--crash-discard'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge($_ENV, [
            'SANDPACKAGE_V17_ROOT' => $GLOBALS['testRoot'],
        ]));
        expect(is_resource($process), 'cannot start discard crash worker');
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return proc_close($process);
    }

    function prepareRegisteredOldPackage(): void
    {
        $registry = runtime_path() . '/sandpackage/sand-iam';
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
        (new ExitFaultingInstallLogic())->uploadFromPath((string) getenv('SANDPACKAGE_V17_ARCHIVE'));
        exit(0);
    }
    if (($argv[1] ?? '') === '--crash-discard') {
        (new ExitDiscardFaultingInstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0');
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

    // The child terminates only after the candidate directory has moved into
    // the registry and its final info plus journal are durable. Recovery must
    // validate the exact tree frozen before that rename, not only metadata.
    removeTree($testRoot);
    mkdir($testRoot, 0755, true);
    prepareRegisteredOldPackage();
    $upgradeArchive = $testRoot . '/upgrade-crash.zip';
    archive($upgradeArchive);
    expect(crashUpload($upgradeArchive) === 97, 'upgrade crash worker did not exit after candidate transaction commit');
    assertControllerHasNoAttackerFields('upgrade candidate transaction crash');
    $journal = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
    $payload = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
    expect(is_array($payload) && ($payload['phase'] ?? null) === 'candidate_info_written', 'candidate journal did not retain the committed phase');
    expect(is_array($payload['candidate_package_manifest'] ?? null), 'candidate journal did not freeze a package manifest');
    expect(is_string($payload['candidate_package_manifest_digest'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $payload['candidate_package_manifest_digest']) === 1, 'candidate journal did not freeze a manifest digest');
    (new InstallLogic('sand-iam'))->recoverPendingCandidates();
    $recovered = (new InstallLogic('sand-iam'))->getInfo();
    expect(!array_key_exists('last_error_code', $recovered) && !array_key_exists('diagnostic_id', $recovered), 'upgrade recovery reintroduced attacker diagnostics');
    expect((int) ($recovered['state'] ?? -1) === InstallLogic::WAIT_INSTALL && ($recovered['version'] ?? null) === '0.7.0', 'unmodified candidate crash did not recover as the ready upgrade');
    expect(!file_exists($journal), 'unmodified candidate recovery did not complete the transaction');

    // This is deliberately a Throwable, rather than a process exit. The
    // journal has already been unlinked, so the production compensation catch
    // must not delete the committed 0.7 candidate or restore 0.6 over it.
    removeTree($testRoot);
    mkdir($testRoot, 0755, true);
    prepareRegisteredOldPackage();
    $commitArchive = $testRoot . '/upgrade-parent-sync-throw.zip';
    archive($commitArchive);
    try {
        (new ParentSyncThrowingInstallLogic())->uploadFromPath($commitArchive);
        throw new \RuntimeException('commit boundary: parent-sync fault was not raised');
    } catch (\RuntimeException $e) {
        if (str_contains($e->getMessage(), 'parent-sync fault was not raised')) { throw $e; }
    }
    $committedJournal = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
    $committed = (new InstallLogic('sand-iam'))->getInfo();
    expect(!is_file($committedJournal), 'commit boundary: already-committed transaction journal reappeared');
    expect(($committed['version'] ?? null) === '0.7.0' && (int) ($committed['state'] ?? -1) === InstallLogic::WAIT_INSTALL, 'commit boundary: parent-sync fault rolled back or removed the committed candidate');
    (new InstallLogic('sand-iam'))->recoverPendingCandidates();
    $afterRestart = (new InstallLogic('sand-iam'))->getInfo();
    expect(($afterRestart['version'] ?? null) === '0.7.0' && (int) ($afterRestart['state'] ?? -1) === InstallLogic::WAIT_INSTALL, 'commit boundary: a new instance did not retain the committed candidate');

    /** @param callable(string):void $mutate */
    function assertTamperedCandidateHeld(string $name, callable $mutate): void
    {
        global $testRoot;
        removeTree($testRoot);
        mkdir($testRoot, 0755, true);
        prepareRegisteredOldPackage();
        $zip = $testRoot . '/upgrade-' . $name . '.zip';
        archive($zip);
        expect(crashUpload($zip) === 97, $name . ': crash worker did not stop after the candidate transaction commit');
        $candidate = runtime_path() . '/sandpackage/sand-iam';
        $journal = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        expect(is_dir($candidate) && is_file($journal), $name . ': candidate or transaction is missing after crash');
        $mutate($candidate);
        try {
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            throw new \RuntimeException($name . ': tampered candidate was accepted during recovery');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'tampered candidate was accepted')) {
                throw $e;
            }
        }
        expect(is_file($journal), $name . ': recovery deleted the journal for a tampered candidate');
        expect(is_dir($candidate), $name . ': recovery moved a tampered candidate without a verified decision');
    }

    assertTamperedCandidateHeld('file-rewrite', static function (string $candidate): void {
        writeFile($candidate . '/plugin/sand-iam/Runtime.php', "<?php\\n// tampered runtime\\n");
    });
    assertTamperedCandidateHeld('file-delete', static function (string $candidate): void {
        expect(unlink($candidate . '/update.sql'), 'file-delete: cannot remove candidate lifecycle file');
    });
    assertTamperedCandidateHeld('file-add', static function (string $candidate): void {
        writeFile($candidate . '/unexpected.php', "<?php\\n// unexpected candidate file\\n");
    });
    assertTamperedCandidateHeld('symlink', static function (string $candidate): void {
        $runtime = $candidate . '/plugin/sand-iam/Runtime.php';
        expect(unlink($runtime), 'symlink: cannot replace candidate runtime file');
        $outside = dirname($candidate) . '/outside-runtime.php';
        writeFile($outside, "<?php\\n// outside candidate runtime\\n");
        expect(symlink($outside, $runtime), 'symlink: cannot create candidate runtime symlink');
    });

    // A recovery record is evidence, not an optional cache: unsafe modes and
    // symlink substitution are rejected before any registry path is touched.
    $journal = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
    expect(chmod($journal, 0644), 'journal mode: cannot loosen transaction journal permissions');
    try {
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        throw new \RuntimeException('journal mode: unsafe transaction journal was accepted');
    } catch (\RuntimeException $e) {
        if (str_contains($e->getMessage(), 'unsafe transaction journal was accepted')) { throw $e; }
    }
    expect(is_file($journal), 'journal mode: unsafe transaction journal was removed');
    expect(chmod($journal, 0600), 'journal symlink: cannot restore transaction journal permissions');
    $outsideJournal = runtime_path() . '/sandpackage/locks/outside.json';
    writeFile($outsideJournal, '{"outside":true}');
    expect(unlink($journal) && symlink($outsideJournal, $journal), 'journal symlink: cannot replace transaction journal');
    try {
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        throw new \RuntimeException('journal symlink: symlinked transaction journal was accepted');
    } catch (\RuntimeException $e) {
        if (str_contains($e->getMessage(), 'symlinked transaction journal was accepted')) { throw $e; }
    }
    expect(is_link($journal), 'journal symlink: recovery removed symlink evidence');

    /** @param callable(string,string):void $mutate */
    function assertDiscardRecoveryHeld(string $name, callable $mutate): void
    {
        global $testRoot;
        removeTree($testRoot);
        mkdir($testRoot, 0755, true);
        prepareRegisteredOldPackage();
        $zip = $testRoot . '/discard-' . $name . '.zip';
        archive($zip);
        (new InstallLogic())->uploadFromPath($zip);
        expect(crashDiscard() === 96, $name . ': discard worker did not exit after quarantine rename');
        $journal = runtime_path() . '/sandpackage/locks/sand-iam-discard.transaction.json';
        $quarantine = glob(runtime_path() . '/sandpackage/quarantine/sand-iam/*', GLOB_ONLYDIR) ?: [];
        expect(is_file($journal) && count($quarantine) === 1 && !is_dir(runtime_path() . '/sandpackage/sand-iam'), $name . ': modern discard crash fixture is incomplete');
        $mutate($journal, $quarantine[0]);
        try {
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            throw new \RuntimeException($name . ': tampered modern discard transaction was accepted');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'tampered modern discard transaction was accepted')) { throw $e; }
        }
        expect(is_file($journal), $name . ': recovery removed the modern discard journal');
        expect(is_dir($quarantine[0]) && !is_dir(runtime_path() . '/sandpackage/sand-iam'), $name . ': recovery moved a tampered quarantined candidate');
    }

    assertDiscardRecoveryHeld('quarantine-lifecycle-rewrite', static function (string $journal, string $candidate): void {
        writeFile($candidate . '/update.sql', '-- tampered quarantined lifecycle');
    });
    assertDiscardRecoveryHeld('journal-modern-field-deletion', static function (string $journal, string $candidate): void {
        $payload = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
        expect(is_array($payload), 'journal field deletion: cannot read modern transaction');
        unset($payload['schema'], $payload['epoch'], $payload['nonce'], $payload['candidate_package_manifest'], $payload['candidate_package_manifest_digest'], $payload['backup_package_manifest'], $payload['backup_package_manifest_digest']);
        expect(file_put_contents($journal, json_encode($payload, JSON_THROW_ON_ERROR)) !== false, 'journal field deletion: cannot rewrite transaction');
        expect(chmod($journal, 0600), 'journal field deletion: cannot restore transaction permissions');
        writeFile($candidate . '/update.sql', '-- tampered quarantined lifecycle');
    });

    foreach (['journal', 'candidate', 'backup'] as $source) {
        foreach (['schema', 'epoch', 'nonce'] as $field) {
            foreach (['delete', 'rewrite'] as $operation) {
                assertDiscardRecoveryHeld($source . '-' . $field . '-' . $operation, static function (string $journal, string $candidate) use ($source, $field, $operation): void {
                    $payload = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
                    expect(is_array($payload), $source . '-' . $field . '-' . $operation . ': cannot read transaction');
                    $key = $source === 'journal' ? $field : 'discard_journal_' . $field;
                    if ($source === 'journal') {
                        if ($operation === 'delete') {
                            unset($payload[$key]);
                        } else {
                            $payload[$key] = $field === 'epoch' ? 99 : 'tampered-' . $field;
                        }
                        expect(file_put_contents($journal, json_encode($payload, JSON_THROW_ON_ERROR)) !== false, $source . '-' . $field . '-' . $operation . ': cannot rewrite transaction');
                        expect(chmod($journal, 0600), $source . '-' . $field . '-' . $operation . ': cannot restore transaction permissions');
                        return;
                    }
                    if ($source === 'candidate') {
                        $info = Server::getIni($candidate);
                        if ($operation === 'delete') {
                            unset($info[$key]);
                        } else {
                            $info[$key] = $field === 'epoch' ? 99 : 'tampered-' . $field;
                        }
                        expect(Server::setIni($candidate, $info), $source . '-' . $field . '-' . $operation . ': cannot rewrite candidate evidence');
                        return;
                    }
                    $backupFile = runtime_path() . '/sandpackage/backups/' . $payload['backup_id'] . '/registration_manifest.json';
                    $backup = json_decode((string) file_get_contents($backupFile), true, 512, JSON_THROW_ON_ERROR);
                    expect(is_array($backup), $source . '-' . $field . '-' . $operation . ': cannot read backup evidence');
                    if ($operation === 'delete') {
                        unset($backup[$key]);
                    } else {
                        $backup[$key] = $field === 'epoch' ? 99 : 'tampered-' . $field;
                    }
                    expect(file_put_contents($backupFile, json_encode($backup, JSON_THROW_ON_ERROR)) !== false, $source . '-' . $field . '-' . $operation . ': cannot rewrite backup evidence');
                    expect(chmod($backupFile, 0600), $source . '-' . $field . '-' . $operation . ': cannot restore backup evidence permissions');
                });
            }
        }
    }

    foreach (['candidate_package_manifest', 'candidate_package_manifest_digest', 'backup_package_manifest', 'backup_package_manifest_digest'] as $field) {
        assertDiscardRecoveryHeld('journal-' . $field . '-delete', static function (string $journal, string $candidate) use ($field): void {
            $payload = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
            expect(is_array($payload), $field . ': cannot read transaction');
            unset($payload[$field]);
            expect(file_put_contents($journal, json_encode($payload, JSON_THROW_ON_ERROR)) !== false, $field . ': cannot rewrite transaction');
            expect(chmod($journal, 0600), $field . ': cannot restore transaction permissions');
        });
    }

    assertDiscardRecoveryHeld('nonce-cross-mismatch', static function (string $journal, string $candidate): void {
        $info = Server::getIni($candidate);
        $info['discard_journal_nonce'] = str_repeat('a', 32);
        expect(Server::setIni($candidate, $info), 'nonce cross mismatch: cannot rewrite candidate evidence');
    });

    assertDiscardRecoveryHeld('backup-manifest-digest-rewrite', static function (string $journal, string $candidate): void {
        $payload = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
        expect(is_array($payload), 'backup digest rewrite: cannot read transaction');
        $payload['backup_package_manifest_digest'] = str_repeat('0', 64);
        expect(file_put_contents($journal, json_encode($payload, JSON_THROW_ON_ERROR)) !== false, 'backup digest rewrite: cannot rewrite transaction');
        expect(chmod($journal, 0600), 'backup digest rewrite: cannot restore transaction permissions');
    });

    foreach (['rewrite', 'delete', 'add', 'link'] as $operation) {
        assertDiscardRecoveryHeld('backup-update-sql-' . $operation, static function (string $journal, string $candidate) use ($operation): void {
            $payload = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
            expect(is_array($payload), 'backup ' . $operation . ': cannot read transaction');
            $backup = runtime_path() . '/sandpackage/backups/' . $payload['backup_id'];
            $update = $backup . '/update.sql';
            if ($operation === 'rewrite') {
                writeFile($update, '-- changed backup update');
                return;
            }
            if ($operation === 'delete') {
                expect(unlink($update), 'backup delete: cannot remove update.sql');
                return;
            }
            if ($operation === 'add') {
                writeFile($backup . '/extra.sql', '-- unexpected backup lifecycle');
                return;
            }
            expect(unlink($update), 'backup link: cannot replace update.sql');
            $outside = dirname($backup) . '/outside-update.sql';
            writeFile($outside, '-- outside backup lifecycle');
            expect(symlink($outside, $update), 'backup link: cannot create lifecycle link');
        });
    }

    global $testRoot;
    removeTree($testRoot);
    echo "SandPackage v17 candidate manifest contract passed\n";
}
