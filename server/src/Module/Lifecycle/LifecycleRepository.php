<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

use Sandadmin\Infra\Db\Repository;
use Sandadmin\Infra\Db\Row;
use Sandadmin\Task\TaskState;

/**
 * sand_system_lifecycle_request 只读仓储。网页端只查询不写入；
 * 申请由独立进程执行，本模块不提供任何取消/变更入口。
 *
 * @extends Repository<LifecycleRequest>
 */
final class LifecycleRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_system_lifecycle_request';
    }

    protected function columns(): array
    {
        return [
            'public_id', 'kind', 'target_code', 'source', 'from_version', 'to_version',
            'purge_data', 'items', 'state', 'stage', 'stage_key', 'progress',
            'rolled_back', 'error_code', 'error_message', 'requested_by',
            'create_time', 'start_time', 'finish_time',
        ];
    }

    protected function fieldMap(): array
    {
        return [
            'public_id' => 'public_id',
            'kind' => 'kind',
            'state' => 'state',
            'create_time' => 'create_time',
        ];
    }

    /** 对外只接受 uuid（public_id），内部自增 id 不作为查询条件。 */
    public function findByPublicId(string $publicId): ?LifecycleRequest
    {
        return $this->findOneBy('public_id', $publicId);
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function strOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    protected function hydrate(Row $row): object
    {
        $rawItems = $row->isNull('items') ? null : $row->string('items');
        $items = null;
        if (is_string($rawItems) && $rawItems !== '') {
            $decoded = json_decode($rawItems, true);
            if (is_array($decoded)) {
                $items = [];
                foreach ($decoded as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $items[] = [
                        'code' => self::str($entry['code'] ?? null),
                        'from_version' => self::strOrNull($entry['from_version'] ?? null),
                        'to_version' => self::strOrNull($entry['to_version'] ?? null),
                        'state' => self::strOrNull($entry['state'] ?? null) ?? TaskState::Queued->value,
                    ];
                }
            }
        }
        $stageKey = $row->isNull('stage_key') ? null : $row->string('stage_key');

        return new LifecycleRequest(
            publicId: $row->string('public_id'),
            kind: LifecycleKind::from($row->string('kind')),
            targetCode: $row->string('target_code'),
            source: $row->string('source'),
            fromVersion: $row->isNull('from_version') ? null : $row->string('from_version'),
            toVersion: $row->isNull('to_version') ? null : $row->string('to_version'),
            deletePluginRow: $row->bool('purge_data'),
            items: $items,
            state: TaskState::from($row->string('state')),
            stage: $row->isNull('stage') ? null : $row->string('stage'),
            stageKey: $stageKey === null ? null : LifecycleStage::tryFrom($stageKey),
            progress: $row->int('progress'),
            rolledBack: $row->bool('rolled_back'),
            errorCode: $row->isNull('error_code') ? null : $row->string('error_code'),
            errorMessage: $row->isNull('error_message') ? null : $row->string('error_message'),
            requestedBy: $row->int('requested_by'),
            createTime: $row->dateTime('create_time'),
            startTime: $row->isNull('start_time') ? null : $row->dateTime('start_time'),
            finishTime: $row->isNull('finish_time') ? null : $row->dateTime('finish_time'),
        );
    }
}
