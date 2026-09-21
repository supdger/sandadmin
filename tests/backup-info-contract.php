<?php
// Legacy recovery regression only; normal lifecycle is covered by UpstreamPostgresLifecycleTest.php.

declare(strict_types=1);

namespace {
    $testRoot = sys_get_temp_dir() . '/sandpackage-v11-' . bin2hex(random_bytes(6));

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
        /** Deliberately preserves the vendor's trailing-separator requirement. */
        public static function getIni(string $directory): array
        {
            $file = $directory . 'info.ini';
            return is_file($file) ? (parse_ini_file($file, true, INI_SCANNER_TYPED) ?: []) : [];
        }

        /** @param array<string,mixed> $data */
        public static function setIni(string $directory, array $data): bool
        {
            $lines = [];
            foreach ($data as $key => $value) {
                $lines[] = $key . ' = ' . var_export($value, true);
            }
            return file_put_contents($directory . 'info.ini', implode(PHP_EOL, $lines) . PHP_EOL) !== false;
        }
    }

    final class Version
    {
        public static function compare(string $required, string $actual): bool
        {
            return version_compare($actual, $required, '>=');
        }
    }

    final class Filesystem
    {
        public static function dirIsEmpty(string $directory): bool
        {
            return !is_dir($directory) || count(scandir($directory) ?: []) <= 2;
        }

        public static function delDir(string $directory): void
        {
            if (!is_dir($directory)) {
                return;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $entry) {
                $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }

    final class Depends
    {
    }
}

namespace plugin\sandadmin\exception {
    class ApiException extends \RuntimeException
    {
    }
}

namespace plugin\sandadmin\app\cache {
    final class UserMenuCache
    {
        public static function clearMenuCache(): void
        {
        }
    }
}

namespace {
    use plugin\sandadmin\exception\ApiException;
    use plugin\sandpackage\app\logic\LegacyInstallLogic as InstallLogic;
    use Saithink\Saipackage\service\Server;

    require dirname(__DIR__) . '/server/plugin/sandpackage/app/service/PluginStorage.php';
    require dirname(__DIR__) . '/server/plugin/sandpackage/app/logic/LegacyInstallLogic.php';

    final class BackupRecoveryFaultLogic extends InstallLogic
    {
        public static string $fault = '';

        protected function candidateFault(string $point): void
        {
            $fault = self::$fault;
            if (in_array($fault, ['rollback_rename_failure', 'rollback_rename_false', 'post_restore_identity'], true) && $point === 'backup.manifest.fsync') {
                throw new ApiException('fixture interrupted backup transaction after backup evidence was written');
            }
            if ($fault === 'rollback_rename_failure' && $point === 'backup.rollback.rename') {
                throw new ApiException('fixture interrupted backup recovery rename');
            }
            if ($fault === 'rollback_rename_false' && $point === 'backup.rollback.rename') {
                $directory = runtime_path() . '/sandpackage/sand-iam';
                if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \RuntimeException('backup recovery rename-false fixture could not create destination');
                }
                writeFile($directory . '/foreign.txt', 'fixture destination collision');
            }
            if (in_array($fault, ['backup_info_delete', 'backup_version', 'backup_state', 'backup_registration'], true)
                && $point === 'backup.rename.committed') {
                $files = glob(runtime_path() . '/sandpackage/backups/sand-iam-package-*/info.ini') ?: [];
                if (count($files) !== 1) {
                    throw new \RuntimeException('backup metadata fault fixture could not locate info.ini');
                }
                if ($fault === 'backup_info_delete') {
                    if (!unlink($files[0])) {
                        throw new \RuntimeException('backup metadata fault fixture could not remove info.ini');
                    }
                    return;
                }
                $directory = dirname($files[0]) . DIRECTORY_SEPARATOR;
                $info = Server::getIni($directory);
                if ($fault === 'backup_version') {
                    $info['version'] = '0.6.1';
                } elseif ($fault === 'backup_state') {
                    $info['state'] = InstallLogic::WAIT_INSTALL;
                } else {
                    $info['registration_manifest'] = str_repeat('0', 64);
                }
                writeInfo($directory, $info);
                return;
            }
            if ($fault === 'post_restore_identity' && $point === 'backup.rollback.rename.committed') {
                $directory = runtime_path() . '/sandpackage/sand-iam/';
                $info = Server::getIni($directory);
                $info['registration_manifest'] = str_repeat('0', 64);
                writeInfo($directory, $info);
            }
            if ($fault === 'legacy_restore_rename_interrupt' && $point === 'legacy.restore.rename.committed') {
                throw new ApiException('fixture interrupted explicit legacy restore after rename');
            }
            if ($fault === 'legacy_restore_info_temp_interrupt' && $point === 'legacy.restore.registration.temp_written') {
                throw new ApiException('fixture interrupted explicit legacy restore during temporary info write');
            }
            if ($fault === 'legacy_restore_info_rename_interrupt' && $point === 'legacy.restore.registration.renamed') {
                throw new ApiException('fixture interrupted explicit legacy restore after atomic info replacement');
            }
            if ($fault === 'legacy_restore_registration_interrupt' && $point === 'legacy.restore.registration.committed') {
                throw new ApiException('fixture interrupted explicit legacy restore after registration');
            }
            if ($fault === 'legacy_restore_completed_interrupt' && $point === 'legacy.restore.completed.committed') {
                throw new ApiException('fixture interrupted explicit legacy restore after completed journal');
            }
        }
    }

    function expect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function expectApiException(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (ApiException) {
            return;
        }
        throw new \RuntimeException($message);
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

    function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }

    /** @param array<string,mixed> $info */
    function writeInfo(string $directory, array $info): void
    {
        expect(Server::setIni(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $info), 'cannot write fixture info');
        chmod(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.ini', 0600);
    }

    /** @return array<string,string> */
    function fixtureFiles(string $version): array
    {
        return [
            'plugin/sand-iam/info.ini' => "app = sand-iam\nversion = {$version}\n",
            'plugin/sand-iam/config/app.php' => "<?php\nreturn ['version' => '{$version}'];\n",
            'plugin/sand-iam/app/Identity.php' => "<?php\n// {$version}\n",
            'sandadmin-artd/src/views/plugin/sand-iam/index.vue' => "<template><div>{$version}</div></template>\n",
        ];
    }

    /** @param array<string,string> $files */
    function populate(string $directory, array $files): void
    {
        foreach ($files as $relative => $contents) {
            writeFile(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative, $contents);
        }
    }

    function treeDigest(string $directory): string
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $files[str_replace(DIRECTORY_SEPARATOR, '/', $iterator->getSubPathName())] = hash_file('sha256', $entry->getPathname());
            }
        }
        ksort($files, SORT_STRING);
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    function deployedRuntimeDigest(): string
    {
        return hash('sha256', json_encode([
            treeDigest(base_path() . '/plugin/sand-iam'),
            treeDigest(dirname(base_path()) . '/sandadmin-artd/src/views/plugin/sand-iam'),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{logic:InstallLogic,old:string,candidate:string,runtime:string} */
    function makeFixture(string $fault = ''): array
    {
        global $testRoot;
        removeTree($testRoot);
        mkdir($testRoot, 0755, true);
        mkdir(runtime_path(), 0755, true);
        mkdir(base_path(), 0755, true);
        mkdir(runtime_path() . '/sandpackage/locks', 0755, true);
        mkdir(runtime_path() . '/sandpackage/backups', 0755, true);

        $old = runtime_path() . '/sandpackage/sand-iam/';
        $candidate = $testRoot . '/v4-candidate/';
        $runtime = base_path() . '/plugin/sand-iam/';
        $runtimeFrontend = dirname(base_path()) . '/sandadmin-artd/src/views/plugin/sand-iam/';
        populate($old, fixtureFiles('0.6.0'));
        populate($candidate, fixtureFiles('0.7.0'));
        populate($runtime, [
            'info.ini' => "app = sand-iam\nversion = 0.6.0\n",
            'config/app.php' => "<?php\nreturn ['version' => '0.6.0'];\n",
            'app/Identity.php' => "<?php\n// 0.6.0\n",
        ]);
        populate($runtimeFrontend, ['index.vue' => "<template><div>0.6.0</div></template>\n"]);

        BackupRecoveryFaultLogic::$fault = $fault;
        $logic = $fault === '' ? new InstallLogic('sand-iam') : new BackupRecoveryFaultLogic('sand-iam');
        $reflection = new \ReflectionClass(InstallLogic::class);
        $deployment = $reflection->getMethod('verifyDeploymentMatchesPackage');
        $deployment->setAccessible(true);
        $oldInfo = [
            'app' => 'sand-iam',
            'title' => 'Fixture',
            'about' => 'Fixture',
            'author' => 'Fixture',
            'version' => '0.6.0',
            'state' => InstallLogic::INSTALLED,
            'registration_manifest' => $deployment->invoke($logic),
        ];
        writeInfo($old, $oldInfo);
        writeInfo($candidate, [
            'app' => 'sand-iam',
            'title' => 'Fixture v4',
            'about' => 'Fixture candidate',
            'author' => 'Fixture',
            'version' => '0.7.0',
            'support' => '6.x',
            'state' => InstallLogic::WAIT_INSTALL,
        ]);
        return ['logic' => $logic, 'old' => $old, 'candidate' => $candidate, 'runtime' => dirname(base_path())];
    }

    function stage(InstallLogic $logic, string $candidate): void
    {
        $reflection = new \ReflectionClass(InstallLogic::class);
        $acquire = $reflection->getMethod('acquireOperationLock');
        $release = $reflection->getMethod('releaseOperationLock');
        $method = $reflection->getMethod('stageUploadedDirectory');
        $acquire->setAccessible(true);
        $release->setAccessible(true);
        $method->setAccessible(true);
        $info = Server::getIni($candidate);
        $acquire->invoke($logic);
        try {
            $method->invoke($logic, $candidate, $info);
        } finally {
            $release->invoke($logic);
        }
    }

    function assertOldPackage(string $old, string $runtimeDigest, int $expectedState, string $message): void
    {
        $info = Server::getIni($old);
        expect(is_dir($old), $message . ': old package was not restored');
        expect(($info['version'] ?? null) === '0.6.0' && (int) ($info['state'] ?? -1) === $expectedState, $message . ': old package identity changed');
        expect(deployedRuntimeDigest() === $runtimeDigest, $message . ': deployed runtime changed');
    }

    /** @return array<string,mixed> */
    function candidateJournal(): array
    {
        $path = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        expect(is_file($path), 'candidate recovery journal was unexpectedly removed');
        $payload = json_decode((string) file_get_contents($path), true);
        expect(is_array($payload), 'candidate recovery journal is unreadable');
        return $payload;
    }

    function recoverOnNewInstance(): ?\Throwable
    {
        $logic = new InstallLogic('sand-iam');
        $reflection = new \ReflectionClass(InstallLogic::class);
        $acquire = $reflection->getMethod('acquireOperationLock');
        $release = $reflection->getMethod('releaseOperationLock');
        $acquire->setAccessible(true);
        $release->setAccessible(true);
        $locked = false;
        try {
            $acquire->invoke($logic);
            $locked = true;
            return null;
        } catch (\Throwable $e) {
            return $e;
        } finally {
            if ($locked) {
                $release->invoke($logic);
            }
        }
    }

    /** @return array{logic:InstallLogic,old:string,backup:string,journal:string,confirmation:string,deployment:string,runtime:string} */
    function makeLegacyInterruptedBackup(string $fault = ''): array
    {
        $fixture = makeFixture($fault);
        $logic = $fixture['logic'];
        $info = Server::getIni($fixture['old']);
        $previousRegistration = str_repeat('f', 64);
        $info['registration_manifest'] = $previousRegistration;
        writeInfo($fixture['old'], $info);
        $infoPath = $fixture['old'] . 'info.ini';
        $rawInfo = file_get_contents($infoPath);
        expect(is_string($rawInfo), 'legacy fixture info.ini is unreadable');
        $rawInfo = preg_replace(
            "/^about = .*$/m",
            "about = \"Original; meaningful detail\"",
            $rawInfo,
            1,
        );
        expect(is_string($rawInfo), 'legacy fixture could not preserve quoted metadata');
        writeFile($infoPath, $rawInfo . "[runtime]\ndriver = pgsql\nstrict = true\n");
        chmod($infoPath, 0640);

        $reflection = new \ReflectionClass(InstallLogic::class);
        $prepared = $reflection->getMethod('preparedPackageManifest');
        $preparedDigest = $reflection->getMethod('preparedPackageManifestDigest');
        $deployment = $reflection->getMethod('verifyDeploymentMatchesPackage');
        $prepared->setAccessible(true);
        $preparedDigest->setAccessible(true);
        $deployment->setAccessible(true);
        $manifest = $prepared->invoke($logic, $fixture['old']);
        $deploymentHash = $deployment->invoke($logic);
        $backupId = 'sand-iam-package-20260912000000-abcdef123456';
        $backup = runtime_path() . '/sandpackage/backups/' . $backupId;
        expect(rename($fixture['old'], $backup), 'legacy fixture could not move package into backup');

        $journal = runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json';
        writeFile(runtime_path() . '/sandpackage/locks/sand-iam-operation.lock', '');
        writeFile($journal, json_encode([
            'app' => 'sand-iam',
            'backup_id' => $backupId,
            'phase' => 'backed_up',
            'from_version' => '0.6.0',
            'previous_registration_manifest' => $previousRegistration,
            'deployment_manifest' => $deploymentHash,
            'prepared_package_manifest' => $manifest,
            'prepared_package_manifest_digest' => $preparedDigest->invoke($logic, $manifest),
            'created_at' => '2026-09-12T00:00:00+08:00',
        ], JSON_THROW_ON_ERROR));
        chmod($journal, 0600);
        $inspection = $logic->inspectInterruptedPreUpgradeBackup();
        return [
            'logic' => $logic,
            'old' => $fixture['old'],
            'backup' => $backup,
            'journal' => $journal,
            'confirmation' => (string) $inspection['confirmation'],
            'deployment' => $deploymentHash,
            'runtime' => deployedRuntimeDigest(),
        ];
    }

    // Full 0.6 registered -> 0.7 candidate staging path. The test Server has
    // the same no-trailing-separator behavior as the vendor implementation.
    $baseline = makeFixture();
    $runtimeDigest = deployedRuntimeDigest();
    stage($baseline['logic'], $baseline['candidate']);
    $ready = $baseline['logic']->getInfo();
    expect(($ready['version'] ?? null) === '0.7.0' && (int) ($ready['state'] ?? -1) === InstallLogic::WAIT_INSTALL && (int) ($ready['update'] ?? 0) === 1 && ($ready['stage'] ?? null) === 'ready', 'successful staging did not produce the ready 0.7 upgrade candidate');
    $backupId = (string) ($ready['package_backup_id'] ?? '');
    expect($backupId !== '', 'successful staging did not create a package backup');
    $backup = runtime_path() . '/sandpackage/backups/' . $backupId;
    expect(is_file($backup . '/registration_manifest.json'), 'successful staging did not create a modern backup manifest');
    expect(!is_file(runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json'), 'successful staging did not converge the candidate journal');
    expect(deployedRuntimeDigest() === $runtimeDigest, 'successful staging changed the deployed 0.6 runtime');
    expect(Server::getIni($backup) === [], 'fixture did not reproduce the vendor no-trailing-separator read');
    $readInfo = (new \ReflectionMethod(InstallLogic::class, 'readPackageInfo'));
    $readInfo->setAccessible(true);
    expect(($readInfo->invoke(null, $backup)['version'] ?? null) === '0.6.0', 'safe package-info helper did not normalize the backup directory');
    $readBackup = (new \ReflectionMethod(InstallLogic::class, 'readVerifiedCandidateBackup'));
    $readBackup->setAccessible(true);
    $verified = $readBackup->invoke($baseline['logic'], $backupId, (string) $ready['registration_manifest']);
    expect(($verified['version'] ?? null) === '0.6.0', 'modern backup verification did not accept the staged backup');

    // Each rejected pre-upgrade condition must leave the registered 0.6
    // package and both runtime targets intact.
    foreach (['registration_manifest', 'state', 'package', 'runtime'] as $fault) {
        $fixture = makeFixture();
        $beforeRuntime = deployedRuntimeDigest();
        if ($fault === 'registration_manifest') {
            $info = Server::getIni($fixture['old']);
            $info['registration_manifest'] = str_repeat('0', 64);
            writeInfo($fixture['old'], $info);
        } elseif ($fault === 'state') {
            $info = Server::getIni($fixture['old']);
            $info['state'] = InstallLogic::WAIT_INSTALL;
            writeInfo($fixture['old'], $info);
        } elseif ($fault === 'package') {
            writeFile($fixture['old'] . 'plugin/sand-iam/app/Identity.php', "<?php\n// tampered\n");
        } elseif ($fault === 'runtime') {
            writeFile(base_path() . '/plugin/sand-iam/app/Identity.php', "<?php\n// tampered runtime\n");
            $beforeRuntime = deployedRuntimeDigest();
        }
        try {
            stage($fixture['logic'], $fixture['candidate']);
            throw new \RuntimeException($fault . ': staging unexpectedly succeeded');
        } catch (ApiException) {
        }
        assertOldPackage(
            $fixture['old'],
            $beforeRuntime,
            $fault === 'state' ? InstallLogic::WAIT_INSTALL : InstallLogic::INSTALLED,
            $fault
        );
        if (in_array($fault, ['registration_manifest', 'state'], true)) {
            expect(!is_file(runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json'), $fault . ': invalid pre-upgrade metadata wrote a recovery journal');
            expect((glob(runtime_path() . '/sandpackage/backups/sand-iam-package-*') ?: []) === [], $fault . ': invalid pre-upgrade metadata created a package backup');
        }
        expect(is_dir($fixture['candidate']), $fault . ': rejected candidate was unexpectedly consumed');
    }

    // A damaged backup must remain visible in its backed_up journal. The
    // failed rollback must never recreate an incomplete old directory or
    // clear the only diagnostic record.
    foreach (['backup_info_delete', 'backup_version', 'backup_state', 'backup_registration'] as $fault) {
        $fixture = makeFixture($fault);
        $beforeRuntime = deployedRuntimeDigest();
        try {
            stage($fixture['logic'], $fixture['candidate']);
            throw new \RuntimeException($fault . ': staging unexpectedly succeeded');
        } catch (ApiException) {
        }
        $journal = candidateJournal();
        expect(($journal['phase'] ?? null) === 'backed_up', $fault . ': backup journal did not remain backed_up');
        expect(!is_dir($fixture['old']), $fault . ': damaged backup was presented as a restored package');
        expect(is_dir(runtime_path() . '/sandpackage/backups/' . $journal['backup_id']), $fault . ': damaged backup evidence disappeared');
        expect(deployedRuntimeDigest() === $beforeRuntime, $fault . ': deployed runtime changed');
        $recoveryError = recoverOnNewInstance();
        expect($recoveryError !== null, $fault . ': a new instance accepted damaged backup evidence');
        expect(!str_contains($recoveryError->getMessage(), '安装目录被占用'), $fault . ': recovery fell into the generic occupied-directory dead end');
        candidateJournal();
    }

    // If rollback cannot rename the verified backup, a fresh logic instance
    // must restore the intact 0.6 package and finish the retained journal.
    $renameFailure = makeFixture('rollback_rename_failure');
    $beforeRuntime = deployedRuntimeDigest();
    try {
        stage($renameFailure['logic'], $renameFailure['candidate']);
        throw new \RuntimeException('rollback_rename_failure: staging unexpectedly succeeded');
    } catch (ApiException) {
    }
    $journal = candidateJournal();
    expect(($journal['phase'] ?? null) === 'backed_up', 'rollback_rename_failure: journal did not retain backed_up state');
    expect(!is_dir($renameFailure['old']), 'rollback_rename_failure: backup rename unexpectedly completed');
    expect(recoverOnNewInstance() === null, 'rollback_rename_failure: new instance did not converge recovery');
    assertOldPackage($renameFailure['old'], $beforeRuntime, InstallLogic::INSTALLED, 'rollback_rename_failure');
    expect(!is_file(runtime_path() . '/sandpackage/locks/sand-iam-candidate.transaction.json'), 'rollback_rename_failure: converged recovery did not clear journal');

    // A real false return from rename leaves the verified backup and its
    // backed_up journal intact. A new worker must report that evidence state,
    // rather than losing it and later calling the path simply occupied.
    $renameFalse = makeFixture('rollback_rename_false');
    try {
        set_error_handler(static fn (): bool => true);
        stage($renameFalse['logic'], $renameFalse['candidate']);
        throw new \RuntimeException('rollback_rename_false: staging unexpectedly succeeded');
    } catch (ApiException) {
    } finally {
        restore_error_handler();
    }
    $journal = candidateJournal();
    expect(($journal['phase'] ?? null) === 'backed_up', 'rollback_rename_false: journal did not retain backed_up state');
    expect(is_dir(runtime_path() . '/sandpackage/backups/' . $journal['backup_id']), 'rollback_rename_false: verified backup disappeared');
    expect(is_file($renameFalse['old'] . '/foreign.txt'), 'rollback_rename_false: fixture did not force destination collision');
    $recoveryError = recoverOnNewInstance();
    expect($recoveryError !== null, 'rollback_rename_false: new instance accepted conflicting recovery paths');
    expect(!str_contains($recoveryError->getMessage(), '安装目录被占用'), 'rollback_rename_false: recovery fell into the generic occupied-directory dead end');
    candidateJournal();

    // An interruption after the durable rollback rename is still fail-closed:
    // the journal stays behind and a new instance reports identity evidence
    // failure instead of treating the corrupt directory as an ordinary clash.
    $postRestore = makeFixture('post_restore_identity');
    try {
        stage($postRestore['logic'], $postRestore['candidate']);
        throw new \RuntimeException('post_restore_identity: staging unexpectedly succeeded');
    } catch (ApiException) {
    }
    $journal = candidateJournal();
    expect(($journal['phase'] ?? null) === 'backed_up', 'post_restore_identity: journal did not remain backed_up');
    expect(is_dir($postRestore['old']), 'post_restore_identity: rollback directory disappeared');
    $recoveryError = recoverOnNewInstance();
    expect($recoveryError !== null, 'post_restore_identity: new instance accepted corrupt restored package');
    expect(!str_contains($recoveryError->getMessage(), '安装目录被占用'), 'post_restore_identity: recovery fell into the generic occupied-directory dead end');
    candidateJournal();

    // Historical v6.1.4 could move an otherwise valid installed package while
    // its stale registration digest differed from the deployed runtime. The
    // repair is explicit, confirmation-bound and does not execute SQL.
    $legacy = makeLegacyInterruptedBackup();
    expectApiException(
        fn () => $legacy['logic']->restoreInterruptedPreUpgradeBackup('RESTORE PRE-UPGRADE wrong'),
        'legacy restore accepted a wrong confirmation'
    );
    expect(!is_dir($legacy['old']) && is_dir($legacy['backup']) && is_file($legacy['journal']), 'wrong confirmation changed legacy recovery evidence');
    $restored = $legacy['logic']->restoreInterruptedPreUpgradeBackup($legacy['confirmation']);
    expect(($restored['state'] ?? null) === 'restored' && ($restored['sql_executed'] ?? null) === false, 'legacy restore did not report a no-SQL recovery');
    expect(is_dir($legacy['old']) && !is_dir($legacy['backup']) && !is_file($legacy['journal']), 'legacy restore did not converge package paths and journal');
    $restoredInfo = Server::getIni($legacy['old']);
    expect(($restoredInfo['registration_manifest'] ?? null) === $legacy['deployment'], 'legacy restore did not normalize the registration digest');
    expect(($restoredInfo['about'] ?? null) === 'Original; meaningful detail', 'legacy restore damaged quoted metadata');
    expect(($restoredInfo['runtime'] ?? null) === ['driver' => 'pgsql', 'strict' => true], 'legacy restore changed an INI section');
    expect((fileperms($legacy['old'] . '/info.ini') & 0777) === 0640, 'legacy restore changed info.ini permissions');
    expect(deployedRuntimeDigest() === $legacy['runtime'], 'legacy restore changed deployed runtime files');

    // A file-level mismatch remains blocked and preserves the only backup and
    // transaction evidence for manual diagnosis.
    $tamperedLegacy = makeLegacyInterruptedBackup();
    writeFile($tamperedLegacy['backup'] . '/plugin/sand-iam/app/Identity.php', "<?php\n// tampered legacy backup\n");
    expectApiException(
        fn () => $tamperedLegacy['logic']->restoreInterruptedPreUpgradeBackup($tamperedLegacy['confirmation']),
        'tampered legacy backup unexpectedly restored'
    );
    expect(!is_dir($tamperedLegacy['old']) && is_dir($tamperedLegacy['backup']) && is_file($tamperedLegacy['journal']), 'tampered legacy recovery lost evidence');
    expect(deployedRuntimeDigest() === $tamperedLegacy['runtime'], 'tampered legacy recovery changed deployed runtime');

    // A process interruption after the durable rename can be retried with the
    // same confirmation and converges without replaying SQL or deployment.
    $interruptedLegacy = makeLegacyInterruptedBackup('legacy_restore_rename_interrupt');
    expectApiException(
        fn () => $interruptedLegacy['logic']->restoreInterruptedPreUpgradeBackup($interruptedLegacy['confirmation']),
        'legacy restore interruption fixture unexpectedly completed'
    );
    expect(is_dir($interruptedLegacy['old']) && !is_dir($interruptedLegacy['backup']) && is_file($interruptedLegacy['journal']), 'legacy interruption did not retain retryable evidence');
    $retryResult = (new InstallLogic('sand-iam'))->restoreInterruptedPreUpgradeBackup($interruptedLegacy['confirmation']);
    expect(($retryResult['state'] ?? null) === 'restored' && ($retryResult['sql_executed'] ?? null) === false, 'legacy restore retry did not converge');
    expect(!is_file($interruptedLegacy['journal']), 'legacy restore retry did not clear completed journal');
    expect((Server::getIni($interruptedLegacy['old'])['registration_manifest'] ?? null) === $interruptedLegacy['deployment'], 'legacy restore retry did not normalize registration');

    // A registration write interrupted before fsync is replayable. The retry
    // revalidates and fsyncs the whole package before completing the journal.
    $registrationRetry = makeLegacyInterruptedBackup('legacy_restore_registration_interrupt');
    expectApiException(
        fn () => $registrationRetry['logic']->restoreInterruptedPreUpgradeBackup($registrationRetry['confirmation']),
        'legacy registration retry fixture unexpectedly completed'
    );
    $registrationRetryResult = (new InstallLogic('sand-iam'))->restoreInterruptedPreUpgradeBackup($registrationRetry['confirmation']);
    expect(($registrationRetryResult['state'] ?? null) === 'restored', 'legacy registration interruption did not converge on retry');
    expect(!is_file($registrationRetry['journal']), 'legacy registration retry did not clear completed journal');

    // Interruptions on either side of the atomic info.ini replacement remain
    // retryable: the active file is always the complete old or complete new form.
    foreach (['legacy_restore_info_temp_interrupt', 'legacy_restore_info_rename_interrupt'] as $infoFault) {
        $infoRetry = makeLegacyInterruptedBackup($infoFault);
        $oldInfoRaw = file_get_contents($infoRetry['backup'] . '/info.ini');
        expectApiException(
            fn () => $infoRetry['logic']->restoreInterruptedPreUpgradeBackup($infoRetry['confirmation']),
            $infoFault . ': fixture unexpectedly completed'
        );
        $activeInfoRaw = file_get_contents($infoRetry['old'] . '/info.ini');
        expect(is_string($activeInfoRaw) && ($activeInfoRaw === $oldInfoRaw
            || (Server::getIni($infoRetry['old'])['registration_manifest'] ?? null) === $infoRetry['deployment']),
            $infoFault . ': interruption left a partial info.ini');
        $infoRetryResult = (new InstallLogic('sand-iam'))->restoreInterruptedPreUpgradeBackup($infoRetry['confirmation']);
        expect(($infoRetryResult['state'] ?? null) === 'restored', $infoFault . ': retry did not converge');
        expect(!is_file($infoRetry['journal']), $infoFault . ': retry did not clear completed journal');
    }

    // If registration was persisted but the completion journal was not, a
    // later non-runtime package change cannot become the new recovery truth.
    $registrationInterrupted = makeLegacyInterruptedBackup('legacy_restore_registration_interrupt');
    expectApiException(
        fn () => $registrationInterrupted['logic']->restoreInterruptedPreUpgradeBackup($registrationInterrupted['confirmation']),
        'legacy registration interruption fixture unexpectedly completed'
    );
    writeFile($registrationInterrupted['old'] . '/update.sql', '-- changed after registration interruption');
    expectApiException(
        fn () => (new InstallLogic('sand-iam'))->restoreInterruptedPreUpgradeBackup($registrationInterrupted['confirmation']),
        'legacy registration retry accepted post-crash package drift'
    );
    expect(is_file($registrationInterrupted['journal']), 'legacy registration drift removed recovery evidence');

    // Even after the completed journal is durable, runtime drift must block
    // final journal deletion and keep the state available for diagnosis.
    $completedInterrupted = makeLegacyInterruptedBackup('legacy_restore_completed_interrupt');
    expectApiException(
        fn () => $completedInterrupted['logic']->restoreInterruptedPreUpgradeBackup($completedInterrupted['confirmation']),
        'legacy completed interruption fixture unexpectedly returned success'
    );
    writeFile(base_path() . '/plugin/sand-iam/app/Identity.php', "<?php\n// runtime drift after completed journal\n");
    expectApiException(
        fn () => (new InstallLogic('sand-iam'))->restoreInterruptedPreUpgradeBackup($completedInterrupted['confirmation']),
        'legacy completed retry accepted runtime drift'
    );
    expect(is_file($completedInterrupted['journal']), 'legacy completed drift removed recovery evidence');

    removeTree($testRoot);
    echo "SandPackage v12 backup recovery contract passed\n";
}
