<?php

declare(strict_types=1);

// behavior-test-gate: static-rule — lock/route/constant-time implementation invariants.

namespace Saithink\Saipackage\service {
    final class Server
    {
        public static int $sqlCalls = 0;
        public static int $restartCalls = 0;
        /** @var list<string> */
        public static array $sqlFiles = [];

        /** @return array<string,mixed> */
        public static function getIni(string $directory): array
        {
            $info = @parse_ini_file(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini', false, INI_SCANNER_TYPED);
            return is_array($info) ? $info : [];
        }

        /** @param array<string,mixed> $info */
        public static function setIni(string $directory, array $info): bool
        {
            $lines = [];
            foreach ($info as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                $lines[] = $key . ' = ' . (is_numeric($value) ? $value : '"' . addcslashes((string) $value, "\\\"") . '"');
            }
            return file_put_contents(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini', implode("\n", $lines) . "\n") !== false;
        }

        public static function importSql(string $file): bool { self::$sqlCalls++; self::$sqlFiles[] = basename($file); return true; }
        public static function restart(): bool { self::$restartCalls++; return true; }
        /** @return array<string,mixed> */
        public static function getConfig(string $directory, string $name): array { return []; }
        /** @return array<string,mixed> */
        public static function getDepend(string $directory): array { return []; }
    }

    final class Filesystem
    {
        public static function dirIsEmpty(string $directory): bool
        {
            $items = @scandir($directory);
            return is_array($items) && count($items) === 2;
        }

        public static function delDir(string $directory): void
        {
            if (!is_dir($directory) || is_link($directory)) { return; }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($directory);
        }

        public static function unzip(string $archive, string $target): void
        {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true || !$zip->extractTo($target)) {
                throw new \RuntimeException('unzip failed');
            }
            $zip->close();
        }
    }

    final class Version
    {
        public static function compare(string $minimum, string $actual): bool
        {
            return version_compare($actual, $minimum, '>');
        }
    }

    final class Depends {}
}

namespace plugin\sandadmin\exception { class ApiException extends \RuntimeException {} }
namespace plugin\sandadmin\app\cache { final class UserMenuCache { public static function clear(): void {} } }

namespace {
    use Saithink\Saipackage\service\Filesystem;
    use Saithink\Saipackage\service\Server;
    use plugin\sandadmin\exception\ApiException;
    use plugin\sandpackage\app\logic\InstallLogic;

    $root = getenv('SANDPACKAGE_CONTRACT_ROOT') ?: sys_get_temp_dir() . '/sandpackage-discard-contract-' . bin2hex(random_bytes(5));
    $runtime = $root . '/runtime';
    $server = $root . '/server';
    $frontend = $root . '/sandadmin-artd';
    function runtime_path(): string { global $runtime; return $runtime; }
    function base_path(): string { global $server; return $server; }
    function env(string $name, mixed $default = null): mixed { return $default; }
    function config(string $name, mixed $default = null): mixed { return $name === 'plugin.sandadmin.app.version' ? '6.0.11' : $default; }

    require dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/InstallLogic.php';


    final class FaultingInstallLogic extends InstallLogic
    {
        public function __construct(string $appName, private readonly string $fault) { parent::__construct($appName); }
        protected function candidateFault(string $point): void
        {
            if ($point === $this->fault) { throw new \RuntimeException('injected ' . $point); }
        }
    }

    final class ExitFaultingInstallLogic extends InstallLogic
    {
        public function __construct(string $appName, private readonly string $exitFault, private readonly string $throwFault = '') { parent::__construct($appName); }
        protected function candidateFault(string $point): void
        {
            if ($point === $this->throwFault) { throw new \RuntimeException('injected ' . $point); }
            if ($point === $this->exitFault) { exit(97); }
        }
    }

    function fail(string $message): never { throw new \RuntimeException($message); }
    function check(bool $condition, string $message): void { if (!$condition) { fail($message); } }
    function mkdirp(string $directory): void { if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { fail('mkdir ' . $directory); } }
    function write(string $file, string $content): void { mkdirp(dirname($file)); file_put_contents($file, $content); chmod($file, 0644); }
    /** @param array<string,mixed> $info */
    function package(string $directory, array $info, string $marker): void
    {
        mkdirp($directory);
        Server::setIni($directory, $info);
        write($directory . '/config.json', '{"name":"sand-iam"}');
        mkdirp($directory . '/plugin/sand-iam');
        Server::setIni($directory . '/plugin/sand-iam', ['app' => $info['app'], 'version' => $info['version']]);
        write($directory . '/plugin/sand-iam/Runtime.php', 'runtime-' . $marker);
        write($directory . '/sandadmin-artd/src/views/plugin/sand-iam/index.vue', '<!-- frontend-' . $marker . ' -->');
        write($directory . '/install.sql', '-- install ' . $marker);
        write($directory . '/update.sql', '-- update ' . $marker);
        write($directory . '/uninstall.sql', '-- uninstall ' . $marker);
        chmod($directory, 0755);
    }
    function treeHash(string $directory): string
    {
        $manifest = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $item) { $manifest[$iterator->getSubPathName()] = hash_file('sha256', $item->getPathname()); }
        ksort($manifest);
        return hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
    }
    function copyTree(string $source, string $target): void
    {
        mkdirp($target);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $path = $target . '/' . $iterator->getSubPathName();
            if ($item->isDir()) { mkdirp($path); } else { mkdirp(dirname($path)); copy($item->getPathname(), $path); chmod($path, 0644); }
        }
    }
    function zipPackage(string $source, string $archive): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { fail('create upload archive'); }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $item) {
            if (!$item->isFile() || !$zip->addFile($item->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $iterator->getSubPathName()))) {
                fail('add upload archive entry');
            }
        }
        $zip->close();
    }
    function crashWorker(string $root, string $action, string $fault, string $archive = '', string $throwFault = ''): int
    {
        $command = [PHP_BINARY, __FILE__, '--crash-worker'];
        $environment = array_merge($_ENV, [
            'SANDPACKAGE_CONTRACT_ROOT' => $root,
            'SANDPACKAGE_CRASH_ACTION' => $action,
            'SANDPACKAGE_CRASH_FAULT' => $fault,
            'SANDPACKAGE_CRASH_ARCHIVE' => $archive,
            'SANDPACKAGE_CRASH_THROW' => $throwFault,
        ]);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        if (!is_resource($process)) { fail('could not start crash worker'); }
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 97 && $error !== '') { fwrite(STDERR, "crash worker: " . $error); }
        return $exit;
    }
    /** @return mixed */
    function invoke(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod(InstallLogic::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($object, ...$args);
    }
    /** @param array<string,mixed> $payload */
    function journal(string $path, array $payload): void { mkdirp(dirname($path)); file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR)); chmod($path, 0600); }
    function expectReject(callable $operation, string $message): void
    {
        $rejected = false;
        try { $operation(); } catch (ApiException|\RuntimeException) { $rejected = true; }
        if (!$rejected) { fail($message); }
    }

    if (($argv[1] ?? '') === '--crash-worker') {
        $fault = (string) getenv('SANDPACKAGE_CRASH_FAULT');
        $throwFault = (string) getenv('SANDPACKAGE_CRASH_THROW');
        $action = (string) getenv('SANDPACKAGE_CRASH_ACTION');
        if ($action === 'discard') {
            (new ExitFaultingInstallLogic('sand-iam', $fault, $throwFault))->discardCandidate('DISCARD sand-iam@0.7.0');
        } elseif ($action === 'upload') {
            (new ExitFaultingInstallLogic('', $fault, $throwFault))->uploadFromPath((string) getenv('SANDPACKAGE_CRASH_ARCHIVE'));
        } else {
            fail('unknown crash worker action');
        }
        exit(0);
    }

    try {
        mkdirp($runtime);
        mkdirp($server);
        mkdirp($frontend);
        $constructorOutside = $root . '/constructor-outside';
        mkdirp($constructorOutside);
        check(symlink($constructorOutside, $runtime . '/sandpackage'), 'could not create constructor symlink probe');
        expectReject(fn () => new InstallLogic('sand-iam'), 'constructor accepted external sandpackage root');
        check(!is_dir($constructorOutside . '/backups'), 'constructor created backups under external root');
        unlink($runtime . '/sandpackage');
        echo "PASS: constructor external install root\n";
        $semverProbe = new InstallLogic();
        foreach ([
            ['1.0.0+build.1', '1.0.0+build.2', 0], ['1.0.0-alpha', '1.0.0-zeta', -1],
            ['1.0.0-1', '1.0.0-alpha', -1], ['1.0.0-rc.1', '1.0.0-rc.2', -1],
            ['1.0.0-rc.1', '1.0.0-rc.1.1', -1], ['1.0.0', '1.0.0-rc.9', 1],
        ] as [$left, $right, $expected]) {
            $actual = invoke($semverProbe, 'compareSemver', $left, $right);
            check(($actual <=> 0) === $expected, 'SemVer precedence mismatch: ' . $left . ' / ' . $right);
        }
        echo "PASS: shared strict SemVer precedence vectors\n";
        mkdirp($runtime . '/sandpackage/locks');
        $old = ['app' => 'sand-iam', 'title' => 'Sand IAM', 'about' => 'contract', 'author' => 'Sand', 'version' => '0.6.0', 'state' => InstallLogic::INSTALLED, 'stage' => 'registered'];
        $candidate = ['app' => 'sand-iam', 'title' => 'Sand IAM', 'about' => 'contract', 'author' => 'Sand', 'version' => '0.7.0', 'support' => '6.x', 'state' => InstallLogic::WAIT_INSTALL, 'stage' => 'ready'];
        $registry = $runtime . '/sandpackage/sand-iam';
        package($registry, $old, '0.6');
        $logic = new InstallLogic('sand-iam');
        foreach ($logic->getAllowedPath() as $source => $target) {
            mkdirp(dirname($target));
            Filesystem::delDir($target);
            $relative = substr($source, strlen(rtrim($registry, DIRECTORY_SEPARATOR)) + 1);
            mkdirp(dirname($target));
            copyTree($registry . '/' . $relative, $target);
        }
        foreach ($logic->getAllowedPath() as $source => $target) {
            check(is_dir($source) && is_dir($target), 'deployment preparation mismatch ' . $source);
        }
        $runtimeHash = treeHash($server . '/plugin/sand-iam');
        $frontendHash = treeHash($frontend . '/src/views/plugin/sand-iam');
        $old['registration_manifest'] = invoke($logic, 'verifyDeploymentMatchesPackage');
        Server::setIni($registry, $old);

        // The real upload path creates the verified 0.6 backup and 0.7 ready candidate.
        $incoming = $root . '/incoming-0.7';
        package($incoming, $candidate, '0.7');
        $archive = $root . '/official-0.7.zip';
        zipPackage($incoming, $archive);
        (new InstallLogic())->uploadFromPath($archive);
        $ready = $logic->getInfo();
        check(($ready['state'] ?? null) === InstallLogic::WAIT_INSTALL && ($ready['stage'] ?? null) === 'ready' && ($ready['update'] ?? null) === 1, 'ready upgrade candidate was not staged');
        $backupId = (string) ($ready['package_backup_id'] ?? '');
        check($backupId !== '' && is_file($runtime . '/sandpackage/backups/' . $backupId . '/registration_manifest.json'), 'verified backup missing');

        // A fault hook terminates the child process directly after the real
        // candidate-to-quarantine rename. The parent creates a fresh logic
        // object and must recover the bound candidate without lifecycle work.
        check(crashWorker($root, 'discard', 'discard.quarantine.rename.committed') === 97, 'discard crash worker did not exit at quarantine rename');
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        check((new InstallLogic('sand-iam'))->getInfo()['version'] === '0.7.0', 'fresh instance did not recover quarantined candidate');
        echo "PASS: child-exit discard-quarantine-rename recovery\n";

        foreach (['discard.journal.write.temp.fsync', 'discard.journal.write.rename', 'discard.journal.write.parent_sync', 'discard.restore.rename.committed', 'discard.journal.unlink', 'discard.journal.parent_sync'] as $fault) {
            check(crashWorker($root, 'discard', $fault) === 97, 'discard crash worker did not exit at ' . $fault);
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            $afterDiscardCrash = (new InstallLogic('sand-iam'))->getInfo();
            $discardOld = ($afterDiscardCrash['version'] ?? null) === '0.6.0' && ($afterDiscardCrash['state'] ?? null) === InstallLogic::INSTALLED;
            $discardCandidate = ($afterDiscardCrash['version'] ?? null) === '0.7.0' && ($afterDiscardCrash['state'] ?? null) === InstallLogic::WAIT_INSTALL;
            check($discardOld || $discardCandidate, 'discard crash did not converge after ' . $fault);
            if ($discardOld) {
                $recoveryArchive = $root . '/recover-' . str_replace('.', '-', $fault) . '.zip';
                zipPackage($incoming, $recoveryArchive);
                (new InstallLogic())->uploadFromPath($recoveryArchive);
            }
            echo 'PASS: child-exit ' . $fault . " recovery\n";
        }

        $backupId = (string) ($logic->getInfo()['package_backup_id'] ?? '');
        $restored = $logic->discardCandidate('DISCARD sand-iam@0.7.0');
        check(($restored['version'] ?? null) === '0.6.0' && ($restored['state'] ?? null) === InstallLogic::INSTALLED, '0.6 registry was not restored');
        check(treeHash($server . '/plugin/sand-iam') === $runtimeHash && treeHash($frontend . '/src/views/plugin/sand-iam') === $frontendHash, 'discard changed deployed runtime or frontend');
        $quarantine = glob($runtime . '/sandpackage/quarantine/sand-iam/0.7.0-*') ?: [];
        check(count($quarantine) >= 1 && is_dir($quarantine[array_key_last($quarantine)]) && $backupId !== '' && is_dir($runtime . '/sandpackage/backups/' . $backupId), 'candidate quarantine or backup audit trail missing: ' . $backupId . ' / ' . json_encode($quarantine));
        check(Server::$sqlCalls === 0 && Server::$restartCalls === 0, 'discard executed lifecycle SQL or restart');

        // Direct process exits cover each candidate staging boundary. Every
        // run starts with the genuine 0.6 registry and recovers it through a
        // new InstallLogic instance; no catch block runs in the child.
        foreach ([
            ['backup.journal.write.temp.fsync', ''],
            ['backup.rename.committed', ''],
            ['backup.manifest.write.temp.chmod', ''],
            ['backup.manifest.write.temp.fsync', ''],
            ['backup.manifest.write.rename', ''],
            ['backup.manifest.write.parent_sync', ''],
            ['candidate.app.rename.committed', ''],
            ['candidate.binding.write.temp.fsync', ''],
            ['candidate.info.write', ''],
            ['candidate.candidate_info_written.temp.fsync', ''],
            ['candidate.candidate_info_written.rename', ''],
            ['candidate.check', ''],
            ['candidate.complete.unlink', ''],
            ['candidate.complete.parent_sync', ''],
            ['rollback.restore.rename.committed', 'candidate.check'],
            ['rollback.journal.unlink', 'candidate.check'],
            ['rollback.journal.parent_sync', 'candidate.check'],
        ] as [$fault, $throwFault]) {
            $crashArchive = $root . '/crash-' . str_replace('.', '-', $fault) . '.zip';
            zipPackage($incoming, $crashArchive);
            check(crashWorker($root, 'upload', $fault, $crashArchive, $throwFault) === 97, 'upload crash worker did not exit at ' . $fault);
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            $afterCrash = (new InstallLogic('sand-iam'))->getInfo();
            $stableOld = ($afterCrash['version'] ?? null) === '0.6.0' && ($afterCrash['state'] ?? null) === InstallLogic::INSTALLED;
            $stableCandidate = ($afterCrash['version'] ?? null) === '0.7.0' && ($afterCrash['state'] ?? null) === InstallLogic::WAIT_INSTALL && ($afterCrash['stage'] ?? null) === 'ready';
            check($stableOld || $stableCandidate, 'fresh recovery did not converge to a stable package after ' . $fault);
            if ($stableCandidate) { (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'); }
            check(treeHash($server . '/plugin/sand-iam') === $runtimeHash && treeHash($frontend . '/src/views/plugin/sand-iam') === $frontendHash, 'child crash changed deployed targets: ' . $fault);
            echo 'PASS: child-exit ' . $fault . " recovery\n";
        }

        // A prepared journal with the old app still in place must validate
        // its package against the live backend/frontend before it may clear.
        $preparedArchive = $root . '/prepared-runtime-drift.zip';
        zipPackage($incoming, $preparedArchive);
        check(crashWorker($root, 'upload', 'backup.journal.write.parent_sync', $preparedArchive) === 97, 'prepared crash worker did not exit');
        $preparedJournal = $runtime . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        foreach (['runtime drift', 'changed lifecycle', 'missing lifecycle', 'new lifecycle file'] as $case) {
            if ($case === 'runtime drift') { write($server . '/plugin/sand-iam/Runtime.php', 'runtime-prepared-corrupt'); }
            if ($case === 'changed lifecycle') { write($runtime . '/sandpackage/sand-iam/update.sql', '-- update prepared corrupt'); }
            if ($case === 'missing lifecycle') { unlink($runtime . '/sandpackage/sand-iam/update.sql'); }
            if ($case === 'new lifecycle file') { write($runtime . '/sandpackage/sand-iam/unexpected.sql', '-- unexpected'); }
            expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'prepared recovery accepted ' . $case);
            check(is_file($preparedJournal) && is_dir($runtime . '/sandpackage/sand-iam'), 'prepared recovery removed evidence or old app: ' . $case);
            write($server . '/plugin/sand-iam/Runtime.php', 'runtime-0.6');
            write($runtime . '/sandpackage/sand-iam/update.sql', '-- update 0.6');
            if (is_file($runtime . '/sandpackage/sand-iam/unexpected.sql')) { unlink($runtime . '/sandpackage/sand-iam/unexpected.sql'); }
            echo 'PASS: prepared package manifest ' . $case . "\n";
        }
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        check(!is_file($preparedJournal), 'prepared recovery did not clear verified journal');
        echo "PASS: prepared runtime drift retains journal\n";

        // After the old app has moved to backup, validation happens in place
        // before recovery may rename it back. A damaged backup stays put.
        $backedUpArchive = $root . '/backed-up-runtime-drift.zip';
        zipPackage($incoming, $backedUpArchive);
        check(crashWorker($root, 'upload', 'backup.rename.committed', $backedUpArchive) === 97, 'backed_up crash worker did not exit');
        $backedUpPayload = json_decode((string) file_get_contents($preparedJournal), true, 512, JSON_THROW_ON_ERROR);
        $backedUpDir = $runtime . '/sandpackage/backups/' . $backedUpPayload['backup_id'];
        foreach (['runtime drift', 'changed lifecycle', 'missing lifecycle', 'new lifecycle file'] as $case) {
            if ($case === 'runtime drift') { write($backedUpDir . '/plugin/sand-iam/Runtime.php', 'runtime-backup-corrupt'); }
            if ($case === 'changed lifecycle') { write($backedUpDir . '/update.sql', '-- update backup corrupt'); }
            if ($case === 'missing lifecycle') { unlink($backedUpDir . '/update.sql'); }
            if ($case === 'new lifecycle file') { write($backedUpDir . '/unexpected.sql', '-- unexpected'); }
            expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'backed_up recovery accepted ' . $case);
            check(is_file($preparedJournal) && is_dir($backedUpDir) && !is_dir($runtime . '/sandpackage/sand-iam'), 'backed_up recovery lost evidence or moved damaged backup: ' . $case);
            write($backedUpDir . '/plugin/sand-iam/Runtime.php', 'runtime-0.6');
            write($backedUpDir . '/update.sql', '-- update 0.6');
            if (is_file($backedUpDir . '/unexpected.sql')) { unlink($backedUpDir . '/unexpected.sql'); }
            echo 'PASS: backed-up package manifest ' . $case . "\n";
        }
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        check(!is_file($preparedJournal) && (new InstallLogic('sand-iam'))->getInfo()['version'] === '0.6.0', 'backed_up recovery did not restore verified old app');
        echo "PASS: backed-up runtime drift retains journal\n";

        // Same-version re-upload must remain possible after the candidate is quarantined.
        $incomingAgain = $root . '/incoming-0.7-again';
        package($incomingAgain, $candidate, '0.7-again');
        $archiveAgain = $root . '/official-0.7-again.zip';
        zipPackage($incomingAgain, $archiveAgain);
        (new InstallLogic())->uploadFromPath($archiveAgain);
        $again = (new InstallLogic('sand-iam'))->getInfo();
        check(($again['version'] ?? null) === '0.7.0' && ($again['state'] ?? null) === InstallLogic::WAIT_INSTALL, 'same 0.7 upload was not accepted after discard');
        check(Server::$sqlCalls === 0 && Server::$restartCalls === 0, 'upload/discard contract executed lifecycle work');

        // A real historical upload has only its original 12-ish info fields:
        // no modern manifests, no support, and no explicit from-version. Its
        // 0.6 backup is also old-format: info.ini has a digest but no JSON
        // registration manifest.
        (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0');
        check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'modern discard left a stale journal before historical setup');
        $historicalOld = (new InstallLogic('sand-iam'))->getInfo();
        $legacyBackupId = 'sand-iam-package-20260831120000-abcdef123456';
        $legacyBackupDir = $runtime . '/sandpackage/backups/' . $legacyBackupId;
        copyTree($registry, $legacyBackupDir);
        check(!is_file($legacyBackupDir . '/registration_manifest.json'), 'historical backup unexpectedly gained a modern manifest');
        Filesystem::delDir($registry);
        $legacyInfo = [
            'app' => 'sand-iam', 'title' => 'Sand IAM', 'about' => 'historical contract', 'author' => 'Sand',
            'version' => '0.7.0', 'state' => InstallLogic::WAIT_INSTALL, 'stage' => 'ready', 'update' => 1,
            'package_backup_id' => $legacyBackupId,
        ];
        package($registry, $legacyInfo, 'legacy-0.7');
        mkdirp($runtime . '/sandpackage/sand-ai');
        check(symlink($root . '/outside-unrelated', $runtime . '/sandpackage/sand-ai/unrelated-link'), 'could not create unrelated package probe');
        $legacyPresented = InstallLogic::presentInfo($legacyInfo);
        check(($legacyPresented['legacy_recoverable'] ?? false) === true && ($legacyPresented['derived_upgrade_from_version'] ?? null) === '0.6.0', 'read-only historical candidate presentation was not recoverable');
        unlink($runtime . '/sandpackage/sand-ai/unrelated-link');
        echo "PASS: legacy presentation scans only current candidate, backup and deployment targets\n";
        expectReject(fn () => (new InstallLogic('sand-iam'))->install(false, 'UPGRADE sand-iam@0.6.0->0.7.0'), 'legacy candidate was accepted for upgrade');
        expectReject(fn () => (new InstallLogic('sand-iam'))->uninstall(), 'legacy candidate was accepted for uninstall');
        foreach (['single modern field', 'bad modern digest', 'foreign backup prefix', 'invalid candidate version', 'foreign candidate app', 'same version'] as $case) {
            $invalidLegacy = $legacyInfo;
            if ($case === 'single modern field') { $invalidLegacy['support'] = '6.x'; }
            if ($case === 'bad modern digest') { $invalidLegacy['registration_manifest'] = 'not-a-hash'; }
            if ($case === 'foreign backup prefix') { $invalidLegacy['package_backup_id'] = 'sand-ai-package-20260831120000-abcdef123456'; }
            if ($case === 'invalid candidate version') { $invalidLegacy['version'] = '0.7'; }
            if ($case === 'foreign candidate app') { $invalidLegacy['app'] = 'sand-ai'; }
            if ($case === 'same version') { $invalidLegacy['version'] = '0.6.0'; }
            Server::setIni($registry, $invalidLegacy);
            $beforeLegacy = treeHash($registry);
            expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate was accepted: ' . $case);
            check(treeHash($registry) === $beforeLegacy, 'invalid legacy candidate mutated registry: ' . $case);
            check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'legacy rejection wrote a journal: ' . $case);
            Server::setIni($registry, $legacyInfo);
            echo 'PASS: legacy reject ' . $case . "\n";
        }
        $badOldDigest = $historicalOld;
        $badOldDigest['registration_manifest'] = 'not-a-historical-digest';
        Server::setIni($legacyBackupDir, $badOldDigest);
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted malformed old registration digest');
        check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'forged old digest wrote a discard journal');
        Server::setIni($legacyBackupDir, $historicalOld);
        Server::setIni($registry . '/plugin/sand-iam', ['app' => 'sand-iam', 'version' => '0.8.0']);
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted mismatched plugin package identity');
        check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'mismatched plugin identity wrote a discard journal');
        Server::setIni($registry . '/plugin/sand-iam', ['app' => 'sand-iam', 'version' => '0.7.0']);
        echo "PASS: legacy reject malformed old digest and candidate package identity drift\n";
        unlink($legacyBackupDir . '/config.json');
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted incomplete backup lifecycle shape');
        write($legacyBackupDir . '/config.json', '{"name":"sand-iam"}');
        write($legacyBackupDir . '/unexpected.php', '<?php');
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted untracked root PHP');
        unlink($legacyBackupDir . '/unexpected.php');
        echo "PASS: legacy reject incomplete and untracked backup lifecycle files\n";
        write($legacyBackupDir . '/plugin/sand-iam/Runtime.php', 'runtime-legacy-backup-drift');
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted backup package drift');
        check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'backup drift wrote a discard journal');
        write($legacyBackupDir . '/plugin/sand-iam/Runtime.php', 'runtime-0.6');
        write($server . '/plugin/sand-iam/Runtime.php', 'runtime-legacy-drift');
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted runtime drift');
        check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'runtime drift wrote a discard journal');
        write($server . '/plugin/sand-iam/Runtime.php', 'runtime-0.6');
        $legacyRuntime = $runtime . '/sandpackage/sand-iam/plugin/sand-iam/Runtime.php';
        $heldLegacyRuntime = $root . '/held-legacy-runtime.php';
        rename($legacyRuntime, $heldLegacyRuntime);
        check(symlink($heldLegacyRuntime, $legacyRuntime), 'could not create legacy candidate symlink probe');
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'legacy candidate accepted external link');
        check(!is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'external link wrote a discard journal');
        unlink($legacyRuntime);
        rename($heldLegacyRuntime, $legacyRuntime);
        Server::setIni($registry, $legacyInfo);
        echo "PASS: legacy reject backup, runtime and external-link drift\n";

        $legacyJournal = $runtime . '/sandpackage/locks/sand-iam-discard.transaction.json';
        check(!is_file($legacyJournal), 'legacy test started with a stale discard journal');
        check(crashWorker($root, 'discard', 'discard.quarantine.rename.committed') === 97, 'legacy discard crash worker did not exit');
        $legacyPayload = json_decode((string) file_get_contents($legacyJournal), true, 512, JSON_THROW_ON_ERROR);
        check(($legacyPayload['legacy_history'] ?? false) === true && isset($legacyPayload['derived_from_version'], $legacyPayload['derived_registration_digest'], $legacyPayload['derived_backup_package_digest'], $legacyPayload['derived_runtime_digest'], $legacyPayload['derived_candidate_package_digest']), 'legacy discard journal lacks historical evidence');
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        check(!is_file($legacyJournal) && !array_key_exists('registration_manifest', (new InstallLogic('sand-iam'))->getInfo()), 'legacy discard crash recovery did not restore the historical candidate');
        check(crashWorker($root, 'discard', 'discard.restore.rename.committed') === 97, 'legacy restore crash worker did not exit');
        $restorePayload = json_decode((string) file_get_contents($legacyJournal), true, 512, JSON_THROW_ON_ERROR);
        $quarantinedCandidate = (string) $restorePayload['candidate'];
        write($quarantinedCandidate . '/update.sql', '-- changed after restore crash');
        expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'legacy recovery accepted changed quarantined lifecycle file');
        check(is_file($legacyJournal) && is_dir($quarantinedCandidate), 'legacy recovery removed evidence after changed quarantined lifecycle file');
        write($quarantinedCandidate . '/update.sql', '-- update legacy-0.7');
        unlink($quarantinedCandidate . '/update.sql');
        expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'legacy recovery accepted deleted quarantined lifecycle file');
        write($quarantinedCandidate . '/update.sql', '-- update legacy-0.7');
        write($quarantinedCandidate . '/unexpected.sql', '-- added after restore crash');
        expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'legacy recovery accepted added quarantined lifecycle file');
        unlink($quarantinedCandidate . '/unexpected.sql');
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        echo "PASS: legacy restore crash retains changed, deleted and added candidate evidence\n";
        $legacyRestored = (new InstallLogic('sand-iam'))->getInfo();
        check(($legacyRestored['version'] ?? null) === '0.6.0' && ($legacyRestored['state'] ?? null) === InstallLogic::INSTALLED, 'legacy discard did not restore 0.6');
        check(Server::$sqlCalls === 0 && Server::$restartCalls === 0 && treeHash($server . '/plugin/sand-iam') === $runtimeHash && treeHash($frontend . '/src/views/plugin/sand-iam') === $frontendHash, 'legacy discard changed lifecycle or deployed files');
        $legacyReupload = $root . '/legacy-reupload-v4';
        package($legacyReupload, $candidate, 'legacy-reupload-v4');
        $legacyReuploadArchive = $root . '/legacy-reupload-v4.zip';
        zipPackage($legacyReupload, $legacyReuploadArchive);
        (new InstallLogic())->uploadFromPath($legacyReuploadArchive);
        $again = (new InstallLogic('sand-iam'))->getInfo();
        check(($again['version'] ?? null) === '0.7.0' && isset($again['registration_manifest'], $again['runtime_manifest']), 'same-version candidate did not re-upload after legacy discard');
        echo "PASS: historical candidate discard and same-version re-upload\n";

        // A prepared discard journal may only be cleared after the ready
        // candidate's own registration and runtime hashes re-verify against
        // the backup. A single changed field retains evidence.
        $discardJournal = $runtime . '/sandpackage/locks/sand-iam-discard.transaction.json';
        $discardPayload = ['app' => 'sand-iam', 'backup_id' => $again['package_backup_id'], 'candidate' => $runtime . '/sandpackage/quarantine/sand-iam/recovery-ready', 'phase' => 'prepared'];
        foreach (['candidate registration manifest drift', 'candidate runtime manifest drift'] as $case) {
            $changed = $again;
            if ($case === 'candidate registration manifest drift') { $changed['registration_manifest'] = str_repeat('0', 64); }
            if ($case === 'candidate runtime manifest drift') { $changed['runtime_manifest'] = str_repeat('0', 64); }
            Server::setIni($runtime . '/sandpackage/sand-iam', $changed);
            journal($discardJournal, $discardPayload);
            expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'discard recovery accepted ' . $case);
            check(is_file($discardJournal), 'discard recovery removed journal for ' . $case);
            unlink($discardJournal);
            Server::setIni($runtime . '/sandpackage/sand-iam', $again);
            echo 'PASS: discard-ready ' . $case . "\n";
        }

        // Invalid ready candidates are fail-closed before any rename.
        foreach ([
            ['state' => InstallLogic::INSTALLED, 'stage' => 'ready'],
            ['state' => InstallLogic::WAIT_INSTALL, 'stage' => 'failed'],
            ['state' => InstallLogic::WAIT_INSTALL, 'stage' => 'ready', 'update' => 0],
        ] as $invalid) {
            $info = array_replace($again, $invalid);
            Server::setIni($runtime . '/sandpackage/sand-iam', $info);
            $before = treeHash($runtime . '/sandpackage/sand-iam');
            try { (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'); fail('invalid candidate was accepted'); } catch (ApiException) {}
            check(treeHash($runtime . '/sandpackage/sand-iam') === $before, 'invalid candidate mutated registry');
        }

        // Recovery journal represents a process stop after candidate quarantine: recovery returns a stable candidate.
        $info = $again;
        $info['state'] = InstallLogic::WAIT_INSTALL; $info['stage'] = 'ready'; $info['update'] = 1;
        Server::setIni($runtime . '/sandpackage/sand-iam', $info);
        $recoveryBackup = (string) $info['package_backup_id'];
        $recoveryCandidate = $runtime . '/sandpackage/quarantine/sand-iam/crash-candidate';
        mkdirp(dirname($recoveryCandidate));
        rename($runtime . '/sandpackage/sand-iam', $recoveryCandidate);
        journal($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json', ['app' => 'sand-iam', 'backup_id' => $recoveryBackup, 'candidate' => $recoveryCandidate, 'phase' => 'prepared']);
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        check(is_dir($runtime . '/sandpackage/sand-iam') && !is_file($runtime . '/sandpackage/locks/sand-iam-discard.transaction.json'), 'discard crash recovery did not restore candidate');

        $externalJournal = $runtime . '/sandpackage/locks/sand-iam-discard.transaction.json';
        journal($externalJournal, ['app' => 'sand-iam', 'backup_id' => $recoveryBackup, 'candidate' => '/tmp/outside-sandpackage', 'phase' => 'prepared']);
        expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'external recovery journal path was accepted');
        check(is_file($externalJournal) && is_dir($runtime . '/sandpackage/sand-iam'), 'external recovery journal mutated a registry or lost evidence');
        unlink($externalJournal);

        // Every mutable SandPackage root rejects a symlinked parent chain.
        // These probes use the production entry points, not copied helpers.
        $outsideRoot = $root . '/outside-parent';
        mkdirp($outsideRoot);
        foreach ([
            ['locks', fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates()],
            ['backups', fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates()],
            ['uploads', fn () => invoke(new InstallLogic('sand-iam'), 'copyUploadArchiveToPrivate', $root . '/not-used.zip')],
            ['quarantine', fn () => invoke(new InstallLogic('sand-iam'), 'recoveryQuarantinePath')],
        ] as [$managedRoot, $probe]) {
            $managedPath = $runtime . '/sandpackage/' . $managedRoot;
            mkdirp($managedPath);
            $heldPath = $root . '/held-' . $managedRoot;
            rename($managedPath, $heldPath);
            check(symlink($outsideRoot, $managedPath), 'could not create external parent-chain probe: ' . $managedRoot);
            expectReject($probe, 'external parent chain was accepted: ' . $managedRoot);
            unlink($managedPath);
            rename($heldPath, $managedPath);
            echo 'PASS: external-parent-chain ' . $managedRoot . "\n";
        }

        $heldCandidate = $root . '/held-candidate';
        $heldBackup = $root . '/held-backup';
        rename($runtime . '/sandpackage/sand-iam', $heldCandidate);
        rename($runtime . '/sandpackage/backups/' . $recoveryBackup, $heldBackup);
        $missingJournal = $runtime . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        journal($missingJournal, ['app' => 'sand-iam', 'backup_id' => $recoveryBackup, 'phase' => 'backed_up']);
        expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'missing candidate and backup journal was silently cleared');
        check(is_file($missingJournal), 'missing candidate/backup recovery evidence was removed');
        rename($heldBackup, $runtime . '/sandpackage/backups/' . $recoveryBackup);
        rename($heldCandidate, $runtime . '/sandpackage/sand-iam');
        unlink($missingJournal);

        // Each durable boundary is exercised through the real discard flow. A
        // fault either rolls back synchronously or the next locked operation
        // recovers the candidate; neither candidate nor old backup disappears.
        foreach ([
            'discard.journal.write', 'discard.journal.write.committed',
            'discard.quarantine.rename', 'discard.quarantine.rename.committed',
            'discard.backup.copy', 'discard.restore.temp.write', 'discard.restore.temp.fsync',
            'discard.restore.chmod', 'discard.restore.manifest.fsync',
            'discard.restore.rename', 'discard.restore.rename.committed', 'discard.cleanup',
        ] as $fault) {
            expectReject(fn () => (new FaultingInstallLogic('sand-iam', $fault))->discardCandidate('DISCARD sand-iam@0.7.0'), 'fault point was not reached: ' . $fault);
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            check(is_dir($runtime . '/sandpackage/sand-iam') && is_dir($runtime . '/sandpackage/backups/' . $recoveryBackup), 'fault lost candidate or backup at ' . $fault);
        }

        $candidateRoot = $runtime . '/sandpackage/sand-iam';
        $backupManifest = $runtime . '/sandpackage/backups/' . $recoveryBackup . '/registration_manifest.json';
        $candidateInfo = Server::getIni($candidateRoot);
        $originalManifest = (string) file_get_contents($backupManifest);
        $originalRuntime = (string) file_get_contents($server . '/plugin/sand-iam/Runtime.php');
        foreach (['empty registration manifest', 'forged package manifest', 'absolute runtime path', 'third runtime target', 'empty runtime manifest'] as $case) {
            $info = $candidateInfo;
            $metadata = json_decode($originalManifest, true, 512, JSON_THROW_ON_ERROR);
            if ($case === 'empty registration manifest') { $info['registration_manifest'] = ''; }
            if ($case === 'forged package manifest') { $metadata['package_manifest']['info.ini'] = 'forged'; }
            if ($case === 'absolute runtime path') { $metadata['runtime_manifest'][0]['path'] = '/tmp/not-sand-iam'; }
            if ($case === 'third runtime target') { $metadata['runtime_manifest'][] = $metadata['runtime_manifest'][0]; $metadata['runtime_manifest'][2]['path'] = $metadata['runtime_manifest'][2]['path'] . '-third'; }
            if ($case === 'empty runtime manifest') { $metadata['runtime_manifest'] = []; }
            Server::setIni($candidateRoot, $info);
            file_put_contents($backupManifest, json_encode($metadata, JSON_THROW_ON_ERROR)); chmod($backupManifest, 0600);
            expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'unsafe metadata was accepted: ' . $case);
            check(is_dir($candidateRoot), 'unsafe metadata changed candidate: ' . $case);
            Server::setIni($candidateRoot, $candidateInfo);
            file_put_contents($backupManifest, $originalManifest); chmod($backupManifest, 0600);
        }

        // candidate_info_written must never be treated as complete merely
        // because its directory exists. Each mismatch keeps its journal for
        // recovery rather than silently accepting or deleting evidence.
        $recoveryMetadata = json_decode($originalManifest, true, 512, JSON_THROW_ON_ERROR);
        $candidateJournal = $runtime . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        $candidatePayload = [
            'app' => 'sand-iam', 'backup_id' => $recoveryBackup, 'phase' => 'candidate_info_written',
            'deployment_manifest' => $recoveryMetadata['deployment_manifest'],
            'from_version' => $recoveryMetadata['version'],
            'previous_registration_manifest' => $recoveryMetadata['previous_registration_manifest'],
            'package_manifest' => $recoveryMetadata['package_manifest'],
            'runtime_manifest' => $recoveryMetadata['runtime_manifest'],
            'runtime_manifest_hash' => $recoveryMetadata['runtime_manifest_hash'],
            'to_version' => $candidateInfo['version'],
            'upgrade_from_version' => $candidateInfo['upgrade_from_version'],
            'candidate_registration_manifest' => $candidateInfo['registration_manifest'],
            'candidate_runtime_manifest' => $candidateInfo['runtime_manifest'],
        ];
        foreach (['runtime manifest drift', 'lineage drift', 'candidate package incomplete', 'arbitrary app directory'] as $case) {
            $mutated = $candidateInfo;
            if ($case === 'runtime manifest drift') { $mutated['runtime_manifest'] = str_repeat('0', 64); }
            if ($case === 'lineage drift') { $mutated['upgrade_from_version'] = '0.5.0'; }
            if ($case === 'candidate package incomplete') { unset($mutated['title']); }
            if ($case === 'arbitrary app directory') { $mutated['state'] = InstallLogic::FAILED; $mutated['stage'] = 'failed'; }
            Server::setIni($candidateRoot, $mutated);
            journal($candidateJournal, $candidatePayload);
            expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'candidate_info_written was accepted: ' . $case);
            check(is_file($candidateJournal), 'candidate_info_written journal was removed: ' . $case);
            unlink($candidateJournal);
            Server::setIni($candidateRoot, $candidateInfo);
            echo 'PASS: candidate-info-written ' . $case . "\n";
        }
        // Coordinated metadata tampering cannot turn the old 0.6 package into
        // a fictitious 9.9.9 ancestor: the candidate version lineage is also
        // verified before either discard or upgrade proceeds.
        $backupRoot = $runtime . '/sandpackage/backups/' . $recoveryBackup;
        $originalBackupInfo = Server::getIni($backupRoot);
        $forgedBackupInfo = $originalBackupInfo;
        $forgedBackupInfo['version'] = '9.9.9';
        Server::setIni($backupRoot, $forgedBackupInfo);
        $forgedMetadata = json_decode($originalManifest, true, 512, JSON_THROW_ON_ERROR);
        $forgedMetadata['version'] = '9.9.9';
        $forgedMetadata['package_manifest'] = invoke(new InstallLogic('sand-iam'), 'packageManifest', $backupRoot);
        $forgedMetadata['registration_manifest'] = invoke(new InstallLogic('sand-iam'), 'registrationManifestHash', $recoveryBackup, $forgedBackupInfo, $forgedMetadata['package_manifest'], $forgedMetadata['runtime_manifest'], $forgedMetadata['deployment_manifest']);
        file_put_contents($backupManifest, json_encode($forgedMetadata, JSON_THROW_ON_ERROR)); chmod($backupManifest, 0600);
        $forgedCandidate = $candidateInfo;
        $forgedCandidate['registration_manifest'] = $forgedMetadata['registration_manifest'];
        $forgedCandidate['upgrade_from_version'] = '9.9.9';
        Server::setIni($candidateRoot, $forgedCandidate);
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'coordinated 9.9.9 manifest forgery was accepted');
        Server::setIni($backupRoot, $originalBackupInfo);
        file_put_contents($backupManifest, $originalManifest); chmod($backupManifest, 0600);
        Server::setIni($candidateRoot, $candidateInfo);
        write($server . '/plugin/sand-iam/Runtime.php', 'runtime-drift');
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'runtime drift was accepted');
        write($server . '/plugin/sand-iam/Runtime.php', $originalRuntime);
        chmod($candidateRoot . '/info.ini', 0666);
        expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'world writable candidate was accepted');
        chmod($candidateRoot . '/info.ini', 0644);
        if (function_exists('symlink')) {
            symlink('/tmp', $candidateRoot . '/unsafe-link');
            expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'symlink candidate was accepted');
            unlink($candidateRoot . '/unsafe-link');
        }
        if (function_exists('posix_mkfifo')) {
            posix_mkfifo($candidateRoot . '/unsafe-fifo', 0644);
            expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'special-file candidate was accepted');
            unlink($candidateRoot . '/unsafe-fifo');
        }
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            chown($candidateRoot . '/info.ini', 1);
            expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'wrong-owner candidate was accepted');
            chown($candidateRoot . '/info.ini', 0);
        }
        $incompleteCandidate = $candidateInfo;
        unset($incompleteCandidate['runtime_manifest']);
        Server::setIni($candidateRoot, $incompleteCandidate);
        expectReject(fn () => (new InstallLogic('sand-iam'))->install(false), 'incomplete ready candidate install was accepted');
        expectReject(fn () => (new InstallLogic('sand-iam'))->uninstall(), 'incomplete ready candidate uninstall was accepted');
        check((new InstallLogic('sand-iam'))->getInfo()['stage'] === 'ready', 'incomplete ready candidate was mutated by install/uninstall');
        Server::setIni($candidateRoot, $candidateInfo);
        expectReject(fn () => (new InstallLogic('sand-iam'))->install(false), 'ready candidate install was accepted');
        expectReject(fn () => (new InstallLogic('sand-iam'))->uninstall(), 'ready candidate uninstall was accepted');
        check(treeHash($server . '/plugin/sand-iam') === $runtimeHash && treeHash($frontend . '/src/views/plugin/sand-iam') === $frontendHash, 'ready candidate install/uninstall changed deployed files');

        // The production methods use one per-app operation lock. Hold the
        // exact logic lock, prove a second logic instance cannot discard, and
        // prove the matching OS lock rejects a second process while another
        // app's lock remains independent.
        $source = (string) file_get_contents(dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/InstallLogic.php');
        foreach (['uploadFromPath', 'install', 'uninstall', 'registerExisting', 'discardCandidate'] as $method) {
            $start = strpos($source, 'function ' . $method);
            $next = strpos($source, "\n    public function", $start + 1);
            $body = substr($source, $start, $next === false ? null : $next - $start);
            check(str_contains($body, 'acquireOperationLock'), $method . ' does not use the common app lock');
        }
        $controller = (string) file_get_contents(dirname(__DIR__) . '/server/plugin/sandpackage/app/controller/InstallController.php');
        $route = (string) file_get_contents(dirname(__DIR__) . '/server/plugin/sandpackage/config/route.php');
        check(str_contains($controller, '$this->adminId !== 1'), 'controller super-admin check is not fail-closed');
        check(str_contains($controller, "strtoupper(\$request->method()) !== 'POST'") && str_contains($controller, 'hash_equals') === false, 'controller discard POST contract changed unexpectedly');
        check(str_contains($source, "hash_equals('DISCARD ' . \$this->appName . '@' . \$version, \$confirmation)"), 'logic discard confirmation is not constant-time');
        check(str_contains($route, "Route::post('/app/sandpackage/install/discardCandidate'") && str_contains($route, "disableDefaultRoute([plugin\\sandpackage\\app\\controller\\InstallController::class, 'discardCandidate'])"), 'discard route is not POST-only');
        $holder = new InstallLogic('sand-iam');
        invoke($holder, 'acquireOperationLock');
        try {
            expectReject(fn () => (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0'), 'same-app second logic instance bypassed operation lock');
            $lockDir = $runtime . '/sandpackage/locks';
            $child = static function (string $path): string {
                $code = '$h=fopen(' . var_export($path, true) . ',"c"); echo flock($h, LOCK_EX|LOCK_NB) ? "locked" : "blocked";';
                return trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($code)));
            };
            check($child($lockDir . '/sand-iam-operation.lock') === 'blocked', 'same-app second process bypassed operation lock');
            check($child($lockDir . '/sand-ai-operation.lock') === 'locked', 'different app was unnecessarily serialized');
        } finally {
            invoke($holder, 'releaseOperationLock');
        }

        // Backup-side interruption points use the same real staging method.
        // After every failed upload the old 0.6 registry remains usable and
        // any prepared journal is recovered under the shared app lock.
        $beforeBackupFaults = (new InstallLogic('sand-iam'))->getInfo();
        check(invoke(new InstallLogic('sand-iam'), 'isReadyUpgradeCandidate', $beforeBackupFaults), 'candidate lost ready fields: ' . json_encode($beforeBackupFaults));
        (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0');
        foreach ([
            'backup.journal.write', 'backup.journal.write.committed',
            'backup.rename', 'backup.rename.committed',
            'backup.journal.fsync', 'backup.journal.fsync.committed',
            'backup.manifest.write', 'backup.manifest.write.committed',
            'backup.manifest.fsync', 'backup.manifest.fsync.committed',
            'candidate.candidate_ready_to_move', 'candidate.app.rename', 'candidate.app.rename.committed',
            'candidate.candidate_moved', 'candidate.info.write', 'candidate.candidate_info_written',
            'candidate.check', 'candidate.candidate_checked',
        ] as $fault) {
            $incomingFault = $root . '/incoming-' . str_replace('.', '-', $fault);
            package($incomingFault, $candidate, $fault);
            $faultLogic = new FaultingInstallLogic('sand-iam', $fault);
            invoke($faultLogic, 'acquireOperationLock');
            try {
                expectReject(fn () => invoke($faultLogic, 'stageUploadedDirectory', $incomingFault . '/', $candidate), 'backup fault point was not reached: ' . $fault);
            } finally {
                invoke($faultLogic, 'releaseOperationLock');
            }
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            $stable = (new InstallLogic('sand-iam'))->getInfo();
            check(($stable['version'] ?? null) === '0.6.0' && ($stable['state'] ?? null) === InstallLogic::INSTALLED, 'backup fault did not preserve 0.6: ' . $fault);
            check(treeHash($server . '/plugin/sand-iam') === $runtimeHash && treeHash($frontend . '/src/views/plugin/sand-iam') === $frontendHash, 'backup fault changed deployed files: ' . $fault);
        }

        // rollback_restored is only complete after the old package, its
        // registration lineage, package manifest and both deployed targets
        // verify together. A consumed backup is therefore still recoverable
        // from the durable transaction snapshot.
        $rollbackCandidate = $runtime . '/sandpackage/quarantine/sand-iam/rollback-restored-candidate';
        mkdirp(dirname($rollbackCandidate));
        rename($runtime . '/sandpackage/sand-iam', $rollbackCandidate);
        rename($runtime . '/sandpackage/backups/' . $recoveryBackup, $runtime . '/sandpackage/sand-iam');
        unlink($runtime . '/sandpackage/sand-iam/registration_manifest.json');
        $rollbackJournal = $runtime . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        $rollbackPayload = $candidatePayload;
        $rollbackPayload['phase'] = 'rollback_restored';
        journal($rollbackJournal, $rollbackPayload);
        (new InstallLogic('sand-iam'))->recoverPendingCandidates();
        check(!is_file($rollbackJournal) && (new InstallLogic('sand-iam'))->getInfo()['version'] === '0.6.0', 'consumed verified rollback backup did not converge');
        echo "PASS: rollback-restored consumed verified backup\n";
        foreach (['wrong old version', 'forged old registration manifest', 'incomplete old package', 'arbitrary restored directory'] as $case) {
            $oldInfo = Server::getIni($runtime . '/sandpackage/sand-iam');
            if ($case === 'wrong old version') { $oldInfo['version'] = '9.9.9'; }
            if ($case === 'forged old registration manifest') { $oldInfo['registration_manifest'] = str_repeat('f', 64); }
            if ($case === 'arbitrary restored directory') { $oldInfo['state'] = InstallLogic::FAILED; $oldInfo['stage'] = 'failed'; }
            Server::setIni($runtime . '/sandpackage/sand-iam', $oldInfo);
            if ($case === 'incomplete old package') { unlink($runtime . '/sandpackage/sand-iam/update.sql'); }
            journal($rollbackJournal, $rollbackPayload);
            expectReject(fn () => (new InstallLogic('sand-iam'))->recoverPendingCandidates(), 'rollback_restored was accepted: ' . $case);
            check(is_file($rollbackJournal), 'rollback_restored journal was removed: ' . $case);
            Server::setIni($runtime . '/sandpackage/sand-iam', ['app' => 'sand-iam', 'title' => 'Sand IAM', 'about' => 'contract', 'author' => 'Sand', 'version' => '0.6.0', 'state' => InstallLogic::INSTALLED, 'stage' => 'registered', 'registration_manifest' => $rollbackPayload['previous_registration_manifest']]);
            if ($case === 'incomplete old package') { write($runtime . '/sandpackage/sand-iam/update.sql', '-- update 0.6'); }
            (new InstallLogic('sand-iam'))->recoverPendingCandidates();
            echo 'PASS: rollback-restored ' . $case . "\n";
        }

        // An incompatible ready candidate cannot upgrade but remains safely
        // discardable, then the corrected same-version candidate can upgrade.
        $badCandidate = $candidate;
        $badCandidate['support'] = '5.x';
        $badIncoming = $root . '/incoming-bad-support';
        package($badIncoming, $badCandidate, 'bad-support');
        $badArchive = $root . '/official-bad-support.zip';
        zipPackage($badIncoming, $badArchive);
        (new InstallLogic())->uploadFromPath($badArchive);
        expectReject(fn () => (new InstallLogic('sand-iam'))->install(false, 'UPGRADE sand-iam@0.6.0->0.7.0'), 'incompatible support was accepted for upgrade');
        (new InstallLogic('sand-iam'))->discardCandidate('DISCARD sand-iam@0.7.0');

        // Full discard -> re-upload -> explicitly-confirmed upgrade. Only
        // update.sql may run; install.sql must never be selected for upgrade.
        $upgradeIncoming = $root . '/incoming-upgrade';
        package($upgradeIncoming, $candidate, 'upgrade');
        $upgradeArchive = $root . '/official-upgrade.zip';
        zipPackage($upgradeIncoming, $upgradeArchive);
        (new InstallLogic())->uploadFromPath($upgradeArchive);
        $upgradeReady = (new InstallLogic('sand-iam'))->getInfo();
        expectReject(fn () => (new InstallLogic('sand-iam'))->install(false, 'UPGRADE sand-iam@0.6.0->wrong'), 'wrong upgrade confirmation was accepted');
        $beforeSql = Server::$sqlCalls;
        try {
            (new InstallLogic('sand-iam'))->install(false, 'UPGRADE sand-iam@0.6.0->0.7.0');
        } catch (\Throwable $error) {
            fail('confirmed upgrade failed: ' . json_encode((new InstallLogic('sand-iam'))->getInfo()) . ' / ' . $error->getMessage());
        }
        $upgraded = (new InstallLogic('sand-iam'))->getInfo();
        check(($upgraded['version'] ?? null) === '0.7.0' && ($upgraded['state'] ?? null) === InstallLogic::INSTALLED, 'confirmed update did not install 0.7');
        check(Server::$sqlCalls === $beforeSql + 1 && Server::$sqlFiles[array_key_last(Server::$sqlFiles)] === 'update.sql', 'upgrade did not select update.sql exclusively');
        check(($upgradeReady['upgrade_from_version'] ?? null) === '0.6.0', 'ready upgrade did not expose source version');

        echo "discard-candidate real InstallLogic contract passed\n";
    } finally {
        Filesystem::delDir($root);
    }
}
