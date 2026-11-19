<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Sandadmin\Contract\Build\ContractBuild;
use Sandadmin\Contract\KernelApi\KernelApiVersion;

/**
 * 离线包安装/升级/卸载执行器（CLI 通道）。顺序：
 * 开发构建标记 + 编译版闸 → Ed25519 验签 → 检查 zip → 安装闸 → journal
 * → 解包暂存 → 落位 → 记账本 → 契约收集。验签在解包前，拒绝零副作用；
 * 任何一步失败都回滚到操作前状态；进程中断由 journal 恢复。
 * signature_verified 如实反映验签结果：真验签通过才为 true，
 * 未签名放行（仅非编译版 + 显式 allowUnsigned）为 false。
 * devBuildMarker 由调用方给出开发构建标记文件路径：环境变量可伪造，
 * 标记文件不在就按编译版拒绝，与引导期常量判定同一口径。
 */
final class PackageExecutor
{
    private const DATA_POLICIES = ['keep', 'export-delete', 'delete'];

    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly string $repoRoot,
        private readonly string $pluginDir,
        private readonly string $workDir,
        private readonly string $dbDriver,
        private readonly string $devBuildMarker,
    ) {
    }

    /** @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool} */
    public function install(
        string $zipFile,
        ?string $sha256,
        ?string $signatureFile = null,
        ?string $pubkeyFile = null,
        bool $allowUnsigned = false,
    ): array {
        return $this->applyPackage($zipFile, $sha256, $signatureFile, $pubkeyFile, $allowUnsigned, 'install');
    }

    /** @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool} */
    public function upgrade(
        string $zipFile,
        ?string $sha256,
        ?string $signatureFile = null,
        ?string $pubkeyFile = null,
        bool $allowUnsigned = false,
    ): array {
        return $this->applyPackage($zipFile, $sha256, $signatureFile, $pubkeyFile, $allowUnsigned, 'upgrade');
    }

    /**
     * backup 路径先于 rename 写进 journal：进程死在挪文件前后，恢复都能
     * 按 backup 目录是否存在区分「账本未卸+文件该挪回去」与
     * 「账本已卸+文件待删」两种状态。
     * dataPolicy 只影响结果报告：删数据要连库，留给迁移执行器另行执行。
     *
     * @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool}
     */
    public function uninstall(string $app, string $dataPolicy = 'keep'): array
    {
        if ($this->isLocked()) {
            return $this->fail($app, null, 'gate', ['编译版或生产宿主不执行插件卸载'], false);
        }
        if (!in_array($dataPolicy, self::DATA_POLICIES, true)) {
            return $this->fail($app, null, 'gate', ['数据处理口径必须是 keep|export-delete|delete'], false);
        }
        $current = $this->registry->installedVersion($app);
        if ($current === null) {
            return $this->fail($app, null, 'gate', ['插件未安装：' . $app], false);
        }
        $pluginPath = $this->pluginDir . '/' . $app;
        if (!is_dir($pluginPath)) {
            return $this->fail($app, $current, 'gate', ['插件有安装记录但运行目录缺失：' . $pluginPath], false);
        }

        $journal = $this->journal();
        try {
            $id = $journal->begin('uninstall', $app, $dataPolicy, $pluginPath);
        } catch (\Throwable $error) {
            // journal 写不进时按失败报告返回，不把未捕获异常抛给调用方
            return $this->fail($app, $current, 'journal', ['操作中断：' . $error->getMessage()], false);
        }
        $backup = $this->workDir . '/backup-' . $app . '-' . $id;
        $unregistered = false;
        $stage = 'remove';
        $pending = [];
        try {
            $journal->setPaths($id, backup: $backup);
            if (!rename($pluginPath, $backup)) {
                throw new \RuntimeException('无法挪走插件目录：' . $pluginPath);
            }
            $journal->mark($id, 'removed');
            $this->registry->recordUninstall($app, false);
            $unregistered = true;
            $journal->mark($id, 'unregistered');
            $stage = 'collect';
            $report = ContractBuild::collectPlugins($this->repoRoot);
            if ($report['result'] !== 'ok') {
                $pending = array_merge($report['problems'], $report['conflicts']);
                throw new \RuntimeException('插件契约收集未通过');
            }
            PackageJournal::removeTree($backup);
            $journal->mark($id, 'purged');
            $journal->finish($id);

            $problems = [];
            if ($dataPolicy !== 'keep') {
                $problems[] = '已按 ' . $dataPolicy . ' 选择数据处理，但删数据要连库，由迁移执行器另行执行，本次未动插件数据';
            }

            return $this->report('ok', $app, $current, 'done', $problems, false, false, false);
        } catch (\Throwable $error) {
            $problems = array_merge($pending, ['操作中断：' . $error->getMessage()]);
            $restored = !is_dir($backup) || rename($backup, $pluginPath);
            if (!$restored) {
                $problems[] = '插件目录未能恢复，需人工核查 ' . $pluginPath;
            }
            if ($unregistered) {
                $this->revertRegistry($app, $current, $pluginPath, $problems);
            }
            $journal->finish($id);

            return $this->fail($app, $current, $stage, $problems, false);
        }
    }

    /**
     * 恢复被中断的操作：回滚未完结 journal、清掉无 journal 的暂存与备份残留，
     * 再重跑契约收集让生成物与目录现状一致。
     *
     * @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, notes: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool}
     */
    public function recover(): array
    {
        $notes = $this->journal()->recoverPending();
        $leftovers = glob($this->workDir . '/staged-*');
        if ($leftovers !== false) {
            foreach ($leftovers as $dir) {
                if (is_dir($dir)) {
                    PackageJournal::removeTree($dir);
                    $notes[] = '清掉无 journal 的暂存目录 ' . $dir;
                }
            }
        }
        $this->recoverOrphanBackups($notes);
        $problems = [];
        if (is_file($this->repoRoot . '/contract/openapi.yaml')) {
            $report = ContractBuild::collectPlugins($this->repoRoot);
            if ($report['result'] !== 'ok') {
                $problems = array_merge($report['problems'], $report['conflicts']);
            }
        }

        return [
            'result' => $problems === [] ? 'ok' : 'failed',
            'app' => null,
            'version' => null,
            'stage' => 'recover',
            'problems' => $problems,
            'notes' => $notes,
            'sha256_verified' => false,
            'signature_verified' => false,
            'data_removed' => false,
        ];
    }

    /**
     * 扫无 journal 对应的 backup-<app>-<id> 孤儿目录：插件目录缺失就挪回去，
     * 还在就是已完成操作的残留，直接清掉。有 journal 的不算孤儿，
     * journal rollback 自己会处理（含解析不了留下的人工核查件）。
     *
     * @param list<string> $notes
     */
    private function recoverOrphanBackups(array &$notes): void
    {
        $leftovers = glob($this->workDir . '/backup-*');
        if ($leftovers === false) {
            return;
        }
        foreach ($leftovers as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            // 目录名 backup-<app>-<kind>-<hex12>：app 自带连字符，从尾巴拆 journal id
            if (!preg_match('/^backup-(.+)-((?:install|upgrade|uninstall)-[0-9a-f]{12})$/', basename($dir), $match)) {
                continue;
            }
            if (is_file($this->workDir . '/' . $match[2] . '.json')) {
                continue;
            }
            $pluginPath = $this->pluginDir . '/' . $match[1];
            if (is_dir($pluginPath)) {
                PackageJournal::removeTree($dir);
                $notes[] = '清掉已完成操作的遗留备份 ' . $dir;
            } elseif (rename($dir, $pluginPath)) {
                $notes[] = '恢复孤儿备份 ' . $dir . ' 到 ' . $pluginPath;
            } else {
                $notes[] = '孤儿备份恢复失败，需人工核查 ' . $dir;
            }
        }
    }

    /** @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool} */
    private function applyPackage(
        string $zipFile,
        ?string $sha256,
        ?string $signatureFile,
        ?string $pubkeyFile,
        bool $allowUnsigned,
        string $kind,
    ): array {
        $shaOk = $this->shaMatches($zipFile, $sha256);
        if ($this->isLocked()) {
            return $this->fail(null, null, 'gate', ['编译版或生产宿主不执行插件安装与升级'], $shaOk);
        }
        // 先验签再解包：此时 zip 目录项都还没解析、零写入，
        // 编译版闸在前面已整体拒绝，allowUnsigned 的口子进不了生产
        $signature = PackageSignature::checkPackage($zipFile, $signatureFile, $pubkeyFile, $allowUnsigned);
        if ($signature['problems'] !== []) {
            return $this->fail(null, null, 'signature', $signature['problems'], $shaOk);
        }
        $seen = PackageArchive::inspect($zipFile, $sha256);
        if ($seen['manifest'] === null || $seen['payload'] === null) {
            return $this->fail(null, null, 'inspect', $seen['problems'], $shaOk);
        }
        $manifest = $seen['manifest'];
        $app = $manifest->app;
        $pluginPath = $this->pluginDir . '/' . $app;
        $current = $this->registry->installedVersion($app);
        $problems = $this->gateProblems($kind, $manifest, $current, $pluginPath);
        if ($problems !== []) {
            return $this->fail($app, $manifest->version, 'gate', $problems, $shaOk);
        }

        $journal = $this->journal();
        try {
            $id = $journal->begin($kind, $app, 'keep', $pluginPath);
        } catch (\Throwable $error) {
            return $this->fail($app, $manifest->version, 'journal', ['操作中断：' . $error->getMessage()], $shaOk);
        }
        $staged = $this->workDir . '/staged-' . $id;
        $backup = $this->workDir . '/backup-' . $app . '-' . $id;
        $registered = false;
        $stage = 'extract';
        $pending = [];
        try {
            if (!is_dir($this->pluginDir) && !mkdir($this->pluginDir, 0755, true) && !is_dir($this->pluginDir)) {
                throw new \RuntimeException('无法创建插件目录：' . $this->pluginDir);
            }
            PackageArchive::extract($zipFile, $seen['payload'], $staged, $manifest);
            $journal->setPaths($id, staged: $staged);
            $journal->mark($id, 'staged');
            $stage = 'place';
            if ($kind === 'upgrade' && is_dir($pluginPath)) {
                $journal->setPaths($id, backup: $backup);
                if (!rename($pluginPath, $backup)) {
                    throw new \RuntimeException('无法挪走旧版插件目录：' . $pluginPath);
                }
                $journal->mark($id, 'backup');
            }
            if (!rename($staged, $pluginPath)) {
                throw new \RuntimeException('无法落位插件目录：' . $pluginPath);
            }
            $journal->mark($id, 'placed');
            $stage = 'register';
            $digest = hash_file('sha256', $pluginPath . '/manifest.json');
            $this->registry->recordInstall($app, $app, $manifest->version, is_string($digest) ? $digest : '');
            $registered = true;
            $journal->mark($id, 'registered');
            $stage = 'collect';
            $report = ContractBuild::collectPlugins($this->repoRoot);
            if ($report['result'] !== 'ok') {
                $pending = array_merge($report['problems'], $report['conflicts']);
                throw new \RuntimeException('插件契约收集未通过');
            }
            if (is_dir($backup)) {
                PackageJournal::removeTree($backup);
            }
            $journal->finish($id);

            return $this->report('ok', $app, $manifest->version, 'done', [], $shaOk, $signature['verified'], false);
        } catch (\Throwable $error) {
            $problems = array_merge($pending, ['操作中断：' . $error->getMessage()]);
            $this->undoPlace($id, $app, $pluginPath, $registered, $current, $problems);

            return $this->fail($app, $manifest->version, $stage, $problems, $shaOk);
        }
    }

    /**
     * @return list<string>
     */
    private function gateProblems(string $kind, PluginPackageManifest $manifest, ?string $current, string $pluginPath): array
    {
        $problems = [];
        $app = $manifest->app;
        if ($kind === 'install') {
            if ($current !== null) {
                $problems[] = '插件已安装：' . $app . ' ' . $current;
            }
            if (is_dir($pluginPath)) {
                $problems[] = '插件目录已存在，不静默覆盖：' . $pluginPath;
            }
        } else {
            if ($current === null) {
                $problems[] = '插件未安装，无法升级：' . $app;
            } elseif ($current === $manifest->version) {
                $problems[] = '目标版本与已装版本相同，不算升级：' . $current;
            }
            if ($current !== null && !is_dir($pluginPath)) {
                $problems[] = '插件有安装记录但运行目录缺失：' . $pluginPath;
            }
        }
        if (!KernelApiVersion::satisfies($manifest->kernelApiMin, $manifest->kernelApiMax)) {
            $problems[] = '插件要求 kernel_api ' . $manifest->kernelApiMin . '~' . $manifest->kernelApiMax
                . '，当前宿主 ' . KernelApiVersion::CURRENT;
        }
        if (!in_array($this->dbDriver, $manifest->databases, true)) {
            $problems[] = '插件不支持目标数据库 ' . $this->dbDriver . '（声明：' . implode(',', $manifest->databases) . '）';
        }
        $installed = $this->registry->installedVersions();
        foreach ($manifest->requires as $dep => $range) {
            $ends = explode(',', $range);
            $depMin = trim($ends[0]);
            $depMax = trim($ends[1] ?? '');
            $depVersion = $installed[$dep] ?? null;
            if ($depVersion === null) {
                $problems[] = '依赖插件未安装：' . $dep . '（要求 ' . $range . '）';
            } elseif (!KernelApiVersion::isSemver($depVersion)
                || KernelApiVersion::compare($depVersion, $depMin) < 0
                || KernelApiVersion::compare($depVersion, $depMax) > 0) {
                $problems[] = '依赖插件 ' . $dep . ' 版本 ' . $depVersion . ' 不在区间 ' . $range;
            }
        }

        return $problems;
    }

    /**
     * 安装/升级失败后的原地回滚：文件按 journal 恢复，账本回写原值。
     * @param list<string> $problems
     */
    private function undoPlace(string $id, string $app, string $pluginPath, bool $registered, ?string $current, array &$problems): void
    {
        if ($this->journal()->rollback($id) === null) {
            $problems[] = 'journal 缺失，文件回滚未完成，需人工核查 ' . $pluginPath;
        }
        if (!$registered) {
            return;
        }
        try {
            if ($current === null) {
                // 从未装过的安装回滚记回 uninstalled，行保留与 keep 口径一致
                $this->registry->recordUninstall($app, false);
            } else {
                $digest = is_file($pluginPath . '/manifest.json')
                    ? hash_file('sha256', $pluginPath . '/manifest.json')
                    : false;
                $this->registry->recordInstall($app, $app, $current, is_string($digest) ? $digest : '');
            }
        } catch (\Throwable $error) {
            $problems[] = '账本回写失败：' . $error->getMessage();
        }
    }

    /** @param list<string> $problems */
    private function revertRegistry(string $app, string $current, string $pluginPath, array &$problems): void
    {
        try {
            $digest = is_file($pluginPath . '/manifest.json')
                ? hash_file('sha256', $pluginPath . '/manifest.json')
                : false;
            $this->registry->recordInstall($app, $app, $current, is_string($digest) ? $digest : '');
        } catch (\Throwable $error) {
            $problems[] = '账本回写失败：' . $error->getMessage();
        }
    }

    /**
     * 生命周期闸：编译版一律拒绝；开发构建标记文件不在也按编译版拒绝——
     * SAND_COMPILED 这类环境变量能伪造，标记文件是构建产物事实。
     */
    private function isLocked(): bool
    {
        return PackageGate::compiled() || !is_file($this->devBuildMarker);
    }

    private function shaMatches(string $zipFile, ?string $sha256): bool
    {
        if ($sha256 === null || !is_file($zipFile)) {
            return false;
        }
        $digest = hash_file('sha256', $zipFile);

        return is_string($digest) && hash_equals(strtolower($sha256), $digest);
    }

    private function journal(): PackageJournal
    {
        return new PackageJournal($this->workDir);
    }

    /**
     * @param list<string> $problems
     * @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool}
     */
    private function report(string $result, ?string $app, ?string $version, string $stage, array $problems, bool $shaOk, bool $sigOk, bool $dataRemoved): array
    {
        return [
            'result' => $result,
            'app' => $app,
            'version' => $version,
            'stage' => $stage,
            'problems' => $problems,
            'sha256_verified' => $shaOk,
            'signature_verified' => $sigOk,
            'data_removed' => $dataRemoved,
        ];
    }

    /**
     * @param list<string> $problems
     * @return array{result: string, app: ?string, version: ?string, stage: string, problems: list<string>, sha256_verified: bool, signature_verified: bool, data_removed: bool}
     */
    private function fail(?string $app, ?string $version, string $stage, array $problems, bool $shaOk): array
    {
        return $this->report('failed', $app, $version, $stage, $problems, false, false, false);
    }
}
