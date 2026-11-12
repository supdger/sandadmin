<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\LoginLog;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\File\ExportFile;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/**
 * M2 登录日志：列表不含地址数字与原始浏览器字符串；
 * 「登录设备地址」只在详情和导出文件里出现（取登录时 IP）。
 */
final class LoginLogService
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
            'SELECT COUNT(*) FROM sand_system_login_log l LEFT JOIN sand_system_user u ON u.id = l.user_id WHERE ' . $where,
            $params,
        ));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.id, l.user_id, l.username_input, l.succeeded, l.reason, l.location,'
            . ' l.device_label, l.login_time, u.nickname'
            . ' FROM sand_system_login_log l LEFT JOIN sand_system_user u ON u.id = l.user_id'
            . ' WHERE ' . $where . ' ORDER BY l.login_time DESC, l.id DESC LIMIT :limit OFFSET :offset',
            $params + ['limit' => $size, 'offset' => ($page - 1) * $size],
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->item($row, false);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $size];
    }

    /** @return array<string, mixed> */
    public function detail(int $logId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT l.id, l.user_id, l.username_input, l.succeeded, l.reason, l.ip, l.location,'
            . ' l.device_label, l.user_agent, l.login_time, u.nickname'
            . ' FROM sand_system_login_log l LEFT JOIN sand_system_user u ON u.id = l.user_id'
            . ' WHERE l.id = :id',
            ['id' => $logId],
        );
        if ($row === false) {
            throw OrgException::notFound();
        }

        return $this->item($row, true);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{task_id: string, state: string}
     */
    public function export(array $filters, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $csv = $this->exportCsv($filters);
        $fileId = ExportFile::write($this->connection, $csv, '登录日志.csv', $actor->writerId());
        $result = json_encode(['file_id' => $fileId], JSON_UNESCAPED_UNICODE);
        $payload = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $publicId = OrgSql::str($this->connection->fetchOne(
            'INSERT INTO sand_system_task_run (type, payload, state, stage, progress, result, created_by, start_time, finish_time)'
            . ' VALUES (\'login-log.export\', CAST(:payload AS jsonb), \'succeeded\', \'已完成\', 100, CAST(:result AS jsonb), :by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            . ' RETURNING public_id::text',
            ['payload' => is_string($payload) ? $payload : '{}', 'result' => is_string($result) ? $result : '{}', 'by' => $actor->writerId()],
        ));
        $this->audit->record($actor, 'login_log', 'export', '导出了登录日志', 'task', $publicId, '登录日志', $requestId, $ip, $device);

        return ['task_id' => $publicId, 'state' => 'succeeded'];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function item(array $row, bool $detailed): array
    {
        $reason = OrgSql::str($row['reason'] ?? '');
        $reason = $reason === '' ? null : $reason;
        $succeeded = self::bool($row['succeeded'] ?? null);
        $item = [
            'id' => OrgSql::int($row['id'] ?? null),
            'username' => OrgSql::str($row['username_input'] ?? null),
            'nickname' => OrgSql::str($row['nickname'] ?? '') !== '' ? OrgSql::str($row['nickname']) : null,
            'succeeded' => $succeeded,
            'reason' => $reason,
            'reason_text' => LoginLogReason::text($reason),
            'location' => OrgSql::str($row['location'] ?? null),
            'device_label' => OrgSql::str($row['device_label'] ?? null),
            'login_time' => self::time($row['login_time'] ?? null),
        ];
        if ($detailed) {
            $item['ip'] = OrgSql::str($row['ip'] ?? null);
            $item['user_agent'] = OrgSql::str($row['user_agent'] ?? null);
        }

        return $item;
    }

    /** @param array<string, mixed> $filters */
    private function exportCsv(array $filters): string
    {
        $lines = ["\xEF\xBB\xBF" . '姓名,登录账号,结果,原因,时间,地点,设备,登录设备地址'];
        [$where, $params] = $this->where($filters);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.username_input, l.succeeded, l.reason, l.ip, l.location, l.device_label, l.login_time, u.nickname'
            . ' FROM sand_system_login_log l LEFT JOIN sand_system_user u ON u.id = l.user_id'
            . ' WHERE ' . $where . ' ORDER BY l.login_time DESC, l.id DESC LIMIT ' . self::EXPORT_LIMIT,
            $params,
        );
        foreach ($rows as $row) {
            $succeeded = self::bool($row['succeeded'] ?? null);
            $reason = OrgSql::str($row['reason'] ?? '');
            $lines[] = implode(',', [
                $this->cell(OrgSql::str($row['nickname'] ?? '')),
                $this->cell(OrgSql::str($row['username_input'] ?? '')),
                $this->cell($succeeded ? '成功' : '没有成功'),
                $this->cell($succeeded ? '' : (LoginLogReason::text($reason === '' ? null : $reason) ?? '')),
                $this->cell(OrgSql::str($row['login_time'] ?? '')),
                $this->cell(OrgSql::str($row['location'] ?? '')),
                $this->cell(OrgSql::str($row['device_label'] ?? '')),
                $this->cell(OrgSql::str($row['ip'] ?? '')),
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
            $where[] = '(l.username_input ILIKE :kw OR COALESCE(u.nickname, \'\') ILIKE :kw)';
            $params['kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        $succeeded = self::boolParam($query['succeeded'] ?? null);
        if ($succeeded !== null) {
            $where[] = 'l.succeeded = :succeeded';
            $params['succeeded'] = $succeeded ? 'true' : 'false';
        }
        $from = self::text($query['login_time_from'] ?? null);
        if ($from !== null) {
            $where[] = 'l.login_time >= :time_from';
            $params['time_from'] = $from;
        }
        $to = self::text($query['login_time_to'] ?? null);
        if ($to !== null) {
            $where[] = 'l.login_time <= :time_to';
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
