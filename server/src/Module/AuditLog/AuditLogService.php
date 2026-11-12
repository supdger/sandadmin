<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\AuditLog;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Audit\AuditModuleOptions;
use Sandadmin\Infra\File\ExportFile;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/**
 * J10 操作日志：只读列表/详情/功能选项/导出。
 * 列表不返回 changes/ip/device_label；详情给全字段（changes 写库前已脱敏）。
 */
final class AuditLogService
{
    private const MAX_PAGE_SIZE = 100;
    private const EXPORT_LIMIT = 10000;

    public function __construct(
        private readonly Connection $connection,
        private readonly OrgAudit $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, page_size: int}
     */
    public function page(array $query): array
    {
        $page = $this->pageNo($query);
        $size = $this->pageSize($query);
        [$where, $params] = $this->where($query);
        $total = OrgSql::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_audit_log a WHERE ' . $where,
            $params,
        ));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.id, a.actor_type, a.actor_id, a.actor_name, a.module, a.action, a.action_text,'
            . ' a.target_type, a.target_id, a.target_label, a.succeeded, a.location, a.create_time'
            . ' FROM sand_system_audit_log a WHERE ' . $where
            . ' ORDER BY a.create_time DESC, a.id DESC LIMIT :limit OFFSET :offset',
            $params + ['limit' => $size, 'offset' => ($page - 1) * $size],
        );
        $items = [];
        $labels = $this->moduleLabels();
        foreach ($rows as $row) {
            $items[] = $this->item($row, $labels, false);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $size];
    }

    /** @return array<string, mixed> */
    public function detail(int $logId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, actor_type, actor_id, actor_name, module, action, action_text,'
            . ' target_type, target_id, target_label, succeeded, changes, ip, location,'
            . ' device_label, request_id, create_time FROM sand_system_audit_log WHERE id = :id',
            ['id' => $logId],
        );
        if ($row === false) {
            throw OrgException::notFound();
        }

        return $this->item($row, $this->moduleLabels(), true);
    }

    /** @return list<array{key: string, name: string}> */
    public function moduleOptions(): array
    {
        $out = [];
        foreach ((new AuditModuleOptions($this->connection))->options() as $option) {
            $out[] = ['key' => $option->key, 'name' => $option->name];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{task_id: string, state: string}
     */
    public function export(array $filters, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $csv = $this->exportCsv($filters);
        $fileId = ExportFile::write($this->connection, $csv, '操作日志.csv', $actor->writerId());
        $result = json_encode(['file_id' => $fileId], JSON_UNESCAPED_UNICODE);
        $payload = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $publicId = OrgSql::str($this->connection->fetchOne(
            'INSERT INTO sand_system_task_run (type, payload, state, stage, progress, result, created_by, start_time, finish_time)'
            . ' VALUES (\'audit-log.export\', CAST(:payload AS jsonb), \'succeeded\', \'已完成\', 100, CAST(:result AS jsonb), :by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            . ' RETURNING public_id::text',
            ['payload' => is_string($payload) ? $payload : '{}', 'result' => is_string($result) ? $result : '{}', 'by' => $actor->writerId()],
        ));
        $this->audit->record($actor, 'audit_log', 'export', '导出了操作日志', 'task', $publicId, '操作日志', $requestId, $ip, $device);

        return ['task_id' => $publicId, 'state' => 'succeeded'];
    }

    /**
     * @param array<string, mixed>   $row
     * @param array<string, string>  $labels
     *
     * @return array<string, mixed>
     */
    private function item(array $row, array $labels, bool $detailed): array
    {
        $module = OrgSql::str($row['module'] ?? null);
        $item = [
            'id' => OrgSql::int($row['id'] ?? null),
            'actor_type' => OrgSql::str($row['actor_type'] ?? null),
            'actor_id' => ($row['actor_id'] ?? null) === null ? null : OrgSql::int($row['actor_id']),
            'actor_name' => OrgSql::str($row['actor_name'] ?? null),
            'module' => $module,
            'module_label' => $labels[$module] ?? null,
            'action' => OrgSql::str($row['action'] ?? null),
            'action_text' => OrgSql::str($row['action_text'] ?? null),
            'target_type' => OrgSql::str($row['target_type'] ?? null),
            'target_id' => OrgSql::str($row['target_id'] ?? null),
            'target_label' => OrgSql::str($row['target_label'] ?? null),
            'succeeded' => self::bool($row['succeeded'] ?? null),
            'location' => OrgSql::str($row['location'] ?? null),
            'create_time' => self::time($row['create_time'] ?? null),
        ];
        if ($detailed) {
            $raw = $row['changes'] ?? null;
            $changes = null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $changes = is_array($decoded) ? $decoded : null;
            }
            $item['changes'] = $changes;
            $item['ip'] = ($row['ip'] ?? null) === null ? null : OrgSql::str($row['ip']);
            $item['device_label'] = OrgSql::str($row['device_label'] ?? null);
            $item['request_id'] = OrgSql::str($row['request_id'] ?? null);
        }

        return $item;
    }

    /** @return array<string, string> 模块键 → 中文名，module_label 派生字段用 */
    private function moduleLabels(): array
    {
        $labels = [];
        foreach ((new AuditModuleOptions($this->connection))->options() as $option) {
            $labels[$option->key] = $option->name;
        }

        return $labels;
    }

    /** @param array<string, mixed> $filters */
    private function exportCsv(array $filters): string
    {
        $lines = ["\xEF\xBB\xBF" . '时间,操作人,功能,做了什么,对象,结果,地点'];
        [$where, $params] = $this->where($filters);
        $labels = $this->moduleLabels();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT actor_type, actor_name, module, action_text, target_label, succeeded, location, create_time'
            . ' FROM sand_system_audit_log a WHERE ' . $where
            . ' ORDER BY a.create_time DESC, a.id DESC LIMIT ' . self::EXPORT_LIMIT,
            $params,
        );
        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $this->cell(OrgSql::str($row['create_time'] ?? '')),
                $this->cell(OrgSql::str($row['actor_type'] ?? '') === 'system' ? '系统' : OrgSql::str($row['actor_name'] ?? '')),
                $this->cell($labels[OrgSql::str($row['module'] ?? '')] ?? OrgSql::str($row['module'] ?? '')),
                $this->cell(OrgSql::str($row['action_text'] ?? '')),
                $this->cell(OrgSql::str($row['target_label'] ?? '')),
                $this->cell(self::bool($row['succeeded'] ?? null) ? '成功' : '没有成功'),
                $this->cell(OrgSql::str($row['location'] ?? '')),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function where(array $query): array
    {
        $where = ['1=1'];
        $params = [];
        $keyword = self::text($query['keyword'] ?? null);
        if ($keyword !== null) {
            $where[] = '(a.actor_name ILIKE :kw OR a.action_text ILIKE :kw OR a.target_label ILIKE :kw)';
            $params['kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        $module = self::text($query['module'] ?? null);
        if ($module !== null) {
            $where[] = 'a.module = :module';
            $params['module'] = $module;
        }
        $actorId = $query['actor_id'] ?? null;
        if (is_numeric($actorId) && (int) $actorId > 0) {
            $where[] = 'a.actor_id = :actor_id';
            $params['actor_id'] = (int) $actorId;
        }
        $succeeded = self::boolParam($query['succeeded'] ?? null);
        if ($succeeded !== null) {
            $where[] = 'a.succeeded = :succeeded';
            $params['succeeded'] = $succeeded ? 'true' : 'false';
        }
        $from = self::text($query['create_time_from'] ?? null);
        if ($from !== null) {
            $where[] = 'a.create_time >= :time_from';
            $params['time_from'] = $from;
        }
        $to = self::text($query['create_time_to'] ?? null);
        if ($to !== null) {
            $where[] = 'a.create_time <= :time_to';
            $params['time_to'] = $to;
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param array<string, mixed> $query */
    private function pageNo(array $query): int
    {
        $raw = $query['page'] ?? 1;
        $page = is_numeric($raw) ? (int) $raw : 1;

        return max(1, $page);
    }

    /** @param array<string, mixed> $query */
    private function pageSize(array $query): int
    {
        $raw = $query['page_size'] ?? 20;
        $size = is_numeric($raw) ? (int) $raw : 20;

        return min(self::MAX_PAGE_SIZE, max(1, $size));
    }

    private static function text(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function bool(mixed $value): bool
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }

    private static function boolParam(mixed $value): ?bool
    {
        if ($value === true || $value === 'true' || $value === '1' || $value === 1) {
            return true;
        }
        if ($value === false || $value === 'false' || $value === '0' || $value === 0) {
            return false;
        }

        return null;
    }

    private static function time(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
    }

    private function cell(string $value): string
    {
        $safe = $value;
        if ($safe !== '' && str_contains('=+-@', $safe[0])) {
            $safe = "'" . $safe;
        }

        return '"' . str_replace('"', '""', $safe) . '"';
    }
}
