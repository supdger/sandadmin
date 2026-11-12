<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Doctrine\DBAL\Connection;
use Sandadmin\Http\ApiOutcome;
use Sandadmin\Infra\File\LocalFileStorage;
use Sandadmin\Infra\Plugin\PluginResidue;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Module\Org\UserExportFile;

/**
 * 插件与系统升级的网页入口。只登记元数据和生命周期，不执行上传内容，不 include 任何安装包。
 * 生产环境和编译版一律拒绝。官方目录里的条目也只是名称和版本，没有可执行代码。
 */
final class PackageOps
{
    private const EXECUTOR = 'package-ops';

    /** @var list<array{code: string, name: string, version: string, latest_version: string, description: string}> */
    private const CATALOG = [
        [
            'code' => 'flow',
            'name' => '流程审批',
            'version' => '1.0.0',
            'latest_version' => '1.1.0',
            'description' => '同事提交申请后，按设定的步骤审批',
        ],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function overview(string $requestId): array
    {
        return ApiOutcome::ok([
            'compiled' => PackageGate::compiled(),
            'package_ops_enabled' => PackageGate::opsEnabled(),
            'signature_required' => true,
        ], $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function market(string $requestId): array
    {
        if (!PackageGate::opsEnabled()) {
            return ApiOutcome::ok(['items' => []], $requestId);
        }

        return ApiOutcome::ok(['items' => $this->marketItems()], $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function installed(string $requestId): array
    {
        if (!PackageGate::opsEnabled()) {
            return ApiOutcome::ok(['items' => []], $requestId);
        }
        $items = [];
        foreach ($this->marketItems() as $item) {
            if ($item['installed'] === true) {
                $items[] = $item;
            }
        }

        return ApiOutcome::ok(['items' => $items], $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function showPlugin(string $code, string $requestId): array
    {
        foreach ($this->marketItems() as $item) {
            if ($item['code'] === $code) {
                return ApiOutcome::ok($item, $requestId);
            }
        }

        return ApiOutcome::fail(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载', [], $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function updateStatus(string $requestId): array
    {
        return ApiOutcome::ok($this->statusBody(), $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function install(string $code, OrgActor $actor, string $requestId): array
    {
        $denied = $this->guard($actor, $requestId, false);
        if ($denied !== null) {
            return $denied;
        }
        $entry = $this->catalogEntry($code);
        if ($entry === null) {
            return ApiOutcome::fail(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载', [], $requestId);
        }
        if ($this->installedVersion($code) !== null) {
            return ApiOutcome::fail(409, 'PLUGIN_ALREADY_INSTALLED', '「' . $entry['name'] . '」已经装好了', [], $requestId);
        }

        return $this->enqueue($actor, 'plugin_install', $code, 'official', null, $entry['version'], false, null, null, $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function upgrade(string $code, OrgActor $actor, string $requestId): array
    {
        $denied = $this->guard($actor, $requestId, false);
        if ($denied !== null) {
            return $denied;
        }
        $entry = $this->catalogEntry($code);
        if ($entry === null) {
            return ApiOutcome::fail(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载', [], $requestId);
        }
        $current = $this->installedVersion($code);
        if ($current === null) {
            return ApiOutcome::fail(409, 'PLUGIN_NOT_INSTALLED', '这个插件还没安装', [], $requestId);
        }
        if ($current === $entry['latest_version']) {
            return ApiOutcome::fail(409, 'PLUGIN_VERSION_INCOMPATIBLE', '不能装比现在更旧的版本', [], $requestId);
        }

        return $this->enqueue($actor, 'plugin_upgrade', $code, 'official', $current, $entry['latest_version'], false, null, null, $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function uninstall(string $code, bool $deletePluginRow, OrgActor $actor, string $requestId): array
    {
        $denied = $this->guard($actor, $requestId, false);
        if ($denied !== null) {
            return $denied;
        }
        $entry = $this->catalogEntry($code);
        if ($entry === null || $this->installedVersion($code) === null) {
            return ApiOutcome::fail(404, 'PLUGIN_NOT_FOUND', '插件不存在或已经被卸载', [], $requestId);
        }
        $current = $this->installedVersion($code);

        return $this->enqueue($actor, 'plugin_uninstall', $code, 'official', $current, null, $deletePluginRow, null, null, $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function upgradeAll(OrgActor $actor, string $requestId): array
    {
        $denied = $this->guard($actor, $requestId, false);
        if ($denied !== null) {
            return $denied;
        }
        $items = [];
        foreach ($this->marketItems() as $item) {
            if ($item['installed'] === true && $item['upgrade_available'] === true) {
                $items[] = [
                    'code' => $item['code'],
                    'from_version' => $item['version'],
                    'to_version' => $item['latest_version'],
                    'state' => 'queued',
                ];
            }
        }
        if ($items === []) {
            return ApiOutcome::fail(409, 'UPDATE_NOT_AVAILABLE', '现在没有可以升级的插件', [], $requestId);
        }
        $encoded = json_encode($items, JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
        }

        return $this->enqueue($actor, 'plugin_upgrade_batch', '*', 'official', null, null, false, $encoded, null, $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function upload(string $fileName, string $bytes, OrgActor $actor, string $requestId): array
    {
        $denied = $this->guard($actor, $requestId, false);
        if ($denied !== null) {
            return $denied;
        }
        $blocked = $this->rejectUpload($fileName, $bytes, $requestId);
        if ($blocked !== null) {
            return $blocked;
        }
        $decoded = json_decode($bytes, true);
        $code = is_array($decoded) && is_string($decoded['code'] ?? null) ? $decoded['code'] : '';
        $entry = $this->catalogEntry($code);
        if ($entry === null) {
            return ApiOutcome::fail(422, 'PLUGIN_PACKAGE_INVALID', '这个文件不是能用的安装包，请检查后再试', [], $requestId);
        }
        if ($this->installedVersion($code) !== null) {
            return ApiOutcome::fail(409, 'PLUGIN_ALREADY_INSTALLED', '「' . $entry['name'] . '」已经装好了', [], $requestId);
        }
        $fileId = $this->storePackage($fileName, $bytes, $actor->userId);

        return $this->enqueue($actor, 'plugin_install', $code, 'upload', null, $entry['version'], false, null, $fileId, $requestId);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function startUpdate(OrgActor $actor, string $requestId): array
    {
        $denied = $this->guard($actor, $requestId, true);
        if ($denied !== null) {
            return $denied;
        }
        $status = $this->statusBody();
        if ($status['update_available'] !== true || !is_string($status['latest_version'])) {
            return ApiOutcome::fail(409, 'UPDATE_NOT_AVAILABLE', '已经是最新版本', [], $requestId);
        }

        return $this->enqueue(
            $actor,
            'system_upgrade',
            'host',
            'official',
            is_string($status['current_version']) ? $status['current_version'] : '1.1.0',
            $status['latest_version'],
            false,
            null,
            null,
            $requestId,
        );
    }

    /** 轮询时往前走一阶段。只推进本模块写下的申请，不碰别的行。 */
    public function advance(string $publicId): void
    {
        if (!PackageGate::opsEnabled()) {
            return;
        }
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $publicId) !== 1) {
            return;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT id, kind, target_code, from_version, to_version, purge_data, state, stage_key, progress, items'
            . ' FROM sand_system_lifecycle_request WHERE public_id = ? AND executor_id = ?'
            . ' AND state IN (\'queued\', \'running\')',
            [$publicId, self::EXECUTOR],
        );
        if ($row === false) {
            return;
        }
        $kind = is_string($row['kind'] ?? null) ? $row['kind'] : '';
        $this->connection->transactional(function () use ($row, $kind): void {
            $next = $this->nextStep($kind, is_string($row['stage_key'] ?? null) ? $row['stage_key'] : null, OrgSql::int($row['progress'] ?? 0));
            if ($next['done']) {
                $this->apply($row);
                $this->connection->executeStatement(
                    'UPDATE sand_system_lifecycle_request SET state = \'succeeded\', stage = :stage, stage_key = :stage_key,'
                    . ' progress = 100, finish_time = CURRENT_TIMESTAMP, heartbeat_time = CURRENT_TIMESTAMP WHERE id = :id',
                    ['stage' => '已完成', 'stage_key' => $next['stage_key'], 'id' => $row['id']],
                );

                return;
            }
            $this->connection->executeStatement(
                'UPDATE sand_system_lifecycle_request SET state = \'running\', stage = :stage, stage_key = :stage_key,'
                . ' progress = :progress, start_time = COALESCE(start_time, CURRENT_TIMESTAMP), heartbeat_time = CURRENT_TIMESTAMP WHERE id = :id',
                [
                    'stage' => $next['stage'],
                    'stage_key' => $next['stage_key'],
                    'progress' => $next['progress'],
                    'id' => $row['id'],
                ],
            );
        });
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function guard(OrgActor $actor, string $requestId, bool $update): ?array
    {
        if ($actor->userId <= 0) {
            return ApiOutcome::fail(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (!$actor->isSuper) {
            return ApiOutcome::fail(403, 'PLUGIN_OPERATION_FORBIDDEN', '只有超级管理员能安装和升级', [], $requestId);
        }
        if (PackageGate::compiled() || !PackageGate::opsEnabled()) {
            $message = $update ? PackageGate::UPDATE_COMPILED_MESSAGE : PackageGate::COMPILED_MESSAGE;

            return ApiOutcome::fail(403, $update ? 'SYSTEM_UPDATE_OPERATION_FORBIDDEN' : 'PLUGIN_OPERATION_FORBIDDEN', $message, [], $requestId);
        }
        $busy = $this->connection->fetchOne(
            'SELECT 1 FROM sand_system_lifecycle_request WHERE state IN (\'queued\', \'running\') LIMIT 1',
        );
        if ($busy !== false && $busy !== null) {
            return ApiOutcome::fail(409, 'PLUGIN_BUSY', '刚才已经有别的安装或升级开始了，做完后再试', [], $requestId);
        }

        return null;
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function rejectUpload(string $fileName, string $bytes, string $requestId): ?array
    {
        $lower = strtolower($fileName);
        $compiledName = str_ends_with($lower, '.phar') || str_ends_with($lower, '.so');
        $decoded = json_decode($bytes, true);
        $compiledFlag = is_array($decoded) && ($decoded['compiled'] ?? false) === true;
        if ($compiledName || $compiledFlag || str_contains(substr($bytes, 0, 512), "\0")) {
            return ApiOutcome::fail(403, 'PLUGIN_OPERATION_FORBIDDEN', PackageGate::COMPILED_PLUGIN_MESSAGE, [], $requestId);
        }
        if (!is_array($decoded)) {
            return ApiOutcome::fail(422, 'PLUGIN_PACKAGE_INVALID', '这个文件不是能用的安装包，请检查后再试', [], $requestId);
        }

        return null;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function enqueue(
        OrgActor $actor,
        string $kind,
        string $target,
        string $source,
        ?string $fromVersion,
        ?string $toVersion,
        bool $deletePluginRow,
        ?string $items,
        ?int $packageFileId,
        string $requestId,
    ): array {
        $clientKey = 'pkg_' . bin2hex(random_bytes(16));
        $params = [
            'kind' => $kind,
            'target' => $target,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'source' => $source,
            'purge' => $deletePluginRow ? 'true' : 'false',
            'by' => $actor->userId,
            'request_id' => $clientKey,
            'executor' => self::EXECUTOR,
            'package_file_id' => $packageFileId,
        ];
        $itemsSql = 'NULL';
        if ($items !== null) {
            $itemsSql = 'CAST(:items AS jsonb)';
            $params['items'] = $items;
        }
        $publicId = $this->connection->fetchOne(
            'INSERT INTO sand_system_lifecycle_request ('
            . ' kind, target_code, from_version, to_version, source, purge_data, package_file_id, items, state, stage, progress,'
            . ' requested_by, request_id, executor_id'
            . ' ) VALUES ('
            . ' :kind, :target, :from_version, :to_version, :source, :purge, :package_file_id, ' . $itemsSql . ', \'queued\', \'排队中\', 0,'
            . ' :by, :request_id, :executor'
            . ' ) RETURNING public_id::text',
            $params,
        );

        return ApiOutcome::ok([
            'request_id' => is_string($publicId) ? $publicId : '',
            'state' => 'queued',
        ], $requestId);
    }

    /** 只把上传字节存成安装包文件，不解析成可执行代码。 */
    private function storePackage(string $fileName, string $bytes, int $uploaderId): int
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('无法准备安装包文件');
        }
        $written = fwrite($stream, $bytes);
        if ($written === false) {
            fclose($stream);
            throw new \RuntimeException('无法准备安装包文件');
        }
        $storage = LocalFileStorage::open(UserExportFile::root());
        $key = $storage->writeStream($stream);
        fclose($stream);
        $safeName = $fileName === '' ? '安装包.json' : $fileName;

        return OrgSql::int($this->connection->fetchOne(
            'INSERT INTO sand_system_file ('
            . ' original_name, kind, media_type, extension, size_bytes, sha256,'
            . ' storage_key, visibility, purpose, usage_tracked, uploader_id'
            . ' ) VALUES ('
            . ' :original_name, \'other\', \'application/json\', \'json\', :size_bytes, :sha256,'
            . ' :storage_key, \'private\', \'package\', false, :uploader_id'
            . ' ) RETURNING id',
            [
                'original_name' => mb_substr($safeName, 0, 255),
                'size_bytes' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'storage_key' => $key,
                'uploader_id' => $uploaderId,
            ],
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function apply(array $row): void
    {
        $kind = is_string($row['kind'] ?? null) ? $row['kind'] : '';
        $code = is_string($row['target_code'] ?? null) ? $row['target_code'] : '';
        if ($kind === 'system_upgrade') {
            $version = is_string($row['to_version'] ?? null) ? $row['to_version'] : '1.2.0';
            $encoded = json_encode($version, JSON_UNESCAPED_UNICODE);
            $this->connection->executeStatement(
                'INSERT INTO sand_system_setting (key, value, group_code) VALUES (\'host.version\', CAST(:value AS jsonb), \'basic\')'
                . ' ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, update_time = CURRENT_TIMESTAMP',
                ['value' => is_string($encoded) ? $encoded : '"1.2.0"'],
            );

            return;
        }
        if ($kind === 'plugin_uninstall') {
            $purge = ($row['purge_data'] ?? null) === true || ($row['purge_data'] ?? null) === 't' || ($row['purge_data'] ?? null) === '1';
            if ($purge) {
                $this->connection->executeStatement('DELETE FROM sand_system_plugin WHERE code = ?', [$code]);
            } else {
                $this->connection->executeStatement(
                    'UPDATE sand_system_plugin SET state = \'uninstalled\', uninstall_time = CURRENT_TIMESTAMP, update_time = CURRENT_TIMESTAMP WHERE code = ?',
                    [$code],
                );
                (new PluginResidue($this->connection))->suspend($code, new \DateTimeImmutable());
            }

            return;
        }
        if ($kind === 'plugin_upgrade_batch') {
            foreach (self::CATALOG as $entry) {
                $current = $this->installedVersion($entry['code']);
                if ($current !== null && $current !== $entry['latest_version']) {
                    $this->connection->executeStatement(
                        'UPDATE sand_system_plugin SET version = :version, update_time = CURRENT_TIMESTAMP WHERE code = :code',
                        ['version' => $entry['latest_version'], 'code' => $entry['code']],
                    );
                }
            }

            return;
        }
        $entry = $this->catalogEntry($code);
        if ($entry === null) {
            return;
        }
        $version = is_string($row['to_version'] ?? null) ? $row['to_version'] : $entry['version'];
        $sha = hash('sha256', $code . '-' . $version);
        // 不删数据的卸载只把 state 改成 uninstalled，行还在。再装必须更新这行，
        // 否则唯一约束会挡住插入，扩展位覆盖也会被级联删掉。
        $kept = $this->connection->fetchOne('SELECT id FROM sand_system_plugin WHERE code = ?', [$code]);
        if ($kept === false && $kind === 'plugin_install') {
            $this->connection->executeStatement(
                'INSERT INTO sand_system_plugin (code, name, version, host_constraint, manifest_sha256, state)'
                . ' VALUES (:code, :name, :version, :host, :sha, \'installed\')',
                [
                    'code' => $code,
                    'name' => $entry['name'],
                    'version' => $version,
                    'host' => '^1.1',
                    'sha' => $sha,
                ],
            );
        } else {
            $this->connection->executeStatement(
                'UPDATE sand_system_plugin SET version = :version, state = \'installed\', uninstall_time = NULL, update_time = CURRENT_TIMESTAMP WHERE code = :code',
                ['version' => $version, 'code' => $code],
            );
        }
        if ($kind === 'plugin_install') {
            (new PluginResidue($this->connection))->resume($code, new \DateTimeImmutable());
        }
    }

    /** @return array{done: bool, stage: string, stage_key: string|null, progress: int} */
    private function nextStep(string $kind, ?string $stageKey, int $progress): array
    {
        if ($kind === 'system_upgrade') {
            $order = [
                null => ['done' => false, 'stage' => '正在检查环境', 'stage_key' => 'check', 'progress' => 20],
                'check' => ['done' => false, 'stage' => '正在备份数据', 'stage_key' => 'backup', 'progress' => 40],
                'backup' => ['done' => false, 'stage' => '正在下载新版本', 'stage_key' => 'download', 'progress' => 60],
                'download' => ['done' => false, 'stage' => '正在切换到新版本', 'stage_key' => 'switch', 'progress' => 80],
                'switch' => ['done' => false, 'stage' => '正在确认能正常用', 'stage_key' => 'verify', 'progress' => 100],
                'verify' => ['done' => true, 'stage' => '已完成', 'stage_key' => 'verify', 'progress' => 100],
            ];

            return $order[$stageKey] ?? $order['verify'];
        }
        if ($progress < 30) {
            return ['done' => false, 'stage' => '正在下载', 'stage_key' => null, 'progress' => 30];
        }
        if ($progress < 60) {
            return ['done' => false, 'stage' => '正在安装', 'stage_key' => null, 'progress' => 60];
        }
        if ($progress < 90) {
            return ['done' => false, 'stage' => '正在检查', 'stage_key' => null, 'progress' => 90];
        }

        return ['done' => true, 'stage' => '已完成', 'stage_key' => null, 'progress' => 100];
    }

    /** @return list<array<string, mixed>> */
    private function marketItems(): array
    {
        $items = [];
        foreach (self::CATALOG as $entry) {
            $installedVersion = $this->installedVersion($entry['code']);
            $installed = $installedVersion !== null;
            $version = $installedVersion ?? $entry['version'];
            $items[] = [
                'id' => $entry['code'],
                'code' => $entry['code'],
                'name' => $entry['name'],
                'version' => $version,
                'state' => $installed ? 'installed' : 'uninstalled',
                'task_state' => $this->taskState($entry['code']),
                'rolled_back' => false,
                'signature_verified' => true,
                'description' => $entry['description'],
                'provides' => [
                    'menus' => [
                        ['title' => $entry['name'], 'url' => '/plugin/' . $entry['code']],
                    ],
                ],
                'install_time' => null,
                'latest_version' => $entry['latest_version'],
                'installed' => $installed,
                'upgrade_available' => $installed && $version !== $entry['latest_version'],
            ];
        }

        return $items;
    }

    /** @return list<array{key: string, name: string}> 已装且有新版本的插件，随系统升级一起更新（J8） */
    private function bundledPlugins(): array
    {
        $out = [];
        foreach ($this->marketItems() as $item) {
            $code = $item['code'] ?? null;
            $name = $item['name'] ?? null;
            if (($item['installed'] ?? false) === true
                && ($item['upgrade_available'] ?? false) === true
                && is_string($code) && is_string($name)) {
                $out[] = ['key' => $code, 'name' => $name];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function statusBody(): array
    {
        $current = '1.1.0';
        $stored = $this->connection->fetchOne('SELECT value FROM sand_system_setting WHERE key = ?', ['host.version']);
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_string($decoded) && $decoded !== '') {
                $current = $decoded;
            }
        }
        $ops = PackageGate::opsEnabled();
        $latest = $ops && $current !== '1.2.0' ? '1.2.0' : null;
        $running = $this->runningUpdate();

        return [
            'current_version' => $current,
            'latest_version' => $latest,
            'update_available' => $latest !== null,
            // 「你会看到的变化」3-5 条大白话（J8）；无可用更新时为空
            'changes' => $latest === null ? [] : [
                '人员、公告和选项的提示更清楚了',
                '系统升级会按五个步骤显示进度',
                '修了一些界面上的小问题',
            ],
            // 随系统升级一起更新的插件：已装且有新版本（J8）
            'bundled_plugins' => $latest === null ? [] : $this->bundledPlugins(),
            'checked_time' => null,
            'compiled' => PackageGate::compiled(),
            'package_ops_enabled' => $ops,
            'running_request' => $running,
            'last_failed_request' => $running === null ? $this->latestFailedUpdate() : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestFailedUpdate(): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT public_id::text AS public_id, kind, target_code, source, from_version, to_version, purge_data,'
            . ' state, stage, stage_key, progress, rolled_back, error_code, error_message, create_time, start_time, finish_time'
            . ' FROM sand_system_lifecycle_request WHERE kind = \'system_upgrade\' AND state = \'failed\''
            . ' ORDER BY id DESC LIMIT 1',
        );
        if ($row === false) {
            return null;
        }

        return $this->requestBody($row);
    }

    /** @return array<string, mixed>|null */
    private function runningUpdate(): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT public_id::text AS public_id, kind, target_code, source, from_version, to_version, purge_data,'
            . ' state, stage, stage_key, progress, rolled_back, error_code, error_message, create_time, start_time, finish_time'
            . ' FROM sand_system_lifecycle_request WHERE kind = \'system_upgrade\' AND state IN (\'queued\', \'running\')'
            . ' ORDER BY id DESC LIMIT 1',
        );
        if ($row === false) {
            return null;
        }

        return $this->requestBody($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function requestBody(array $row): array
    {
        $errorMessage = is_string($row['error_message'] ?? null) ? trim($row['error_message']) : '';
        if (($row['state'] ?? null) === 'failed' && ($errorMessage === '' || $errorMessage === '失败')) {
            // M3：失败必须是一句具体人话，不允许只回「失败」
            $errorMessage = '系统出了点问题，请稍后再试';
        }

        return [
            'id' => $row['public_id'],
            'kind' => $row['kind'],
            'target_code' => $row['target_code'],
            'source' => $row['source'],
            'from_version' => $row['from_version'],
            'to_version' => $row['to_version'],
            'purge_data' => ($row['purge_data'] ?? null) === true || ($row['purge_data'] ?? null) === 't',
            'items' => null,
            'state' => $row['state'],
            'stage' => $row['stage'],
            'stage_key' => $row['stage_key'],
            'progress' => OrgSql::int($row['progress'] ?? 0),
            'rolled_back' => ($row['rolled_back'] ?? null) === true || ($row['rolled_back'] ?? null) === 't' || ($row['rolled_back'] ?? null) === '1',
            'error_code' => $row['error_code'],
            'error_message' => $errorMessage === '' ? null : $errorMessage,
            'create_time' => is_string($row['create_time'] ?? null) ? $row['create_time'] : '',
            'start_time' => $row['start_time'],
            'finish_time' => $row['finish_time'],
        ];
    }

    private function taskState(string $code): ?string
    {
        $state = $this->connection->fetchOne(
            'SELECT state FROM sand_system_lifecycle_request WHERE target_code = ? AND state IN (\'queued\', \'running\') ORDER BY id DESC LIMIT 1',
            [$code],
        );

        return is_string($state) ? $state : null;
    }

    private function installedVersion(string $code): ?string
    {
        $version = $this->connection->fetchOne(
            'SELECT version FROM sand_system_plugin WHERE code = ? AND state = \'installed\'',
            [$code],
        );

        return is_string($version) ? $version : null;
    }

    /** @return array{code: string, name: string, version: string, latest_version: string, description: string}|null */
    private function catalogEntry(string $code): ?array
    {
        foreach (self::CATALOG as $entry) {
            if ($entry['code'] === $code) {
                return $entry;
            }
        }

        return null;
    }
}
