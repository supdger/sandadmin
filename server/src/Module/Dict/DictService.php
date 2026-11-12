<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Dict;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgInput;
use Sandadmin\Module\Org\OrgSql;

/**
 * 选项组与选项。系统自带与插件在用的组不能删。
 * used 查 sand_system_dict_item_ref：有引用为 true，管理员自建组没有引用为 false。
 * 插件组（extension_key 非空）没有引用行时为 null，前端按可能已使用提醒。
 */
final class DictService
{
    /** @var list<string> */
    private const COLORS = ['#3491FA', '#13C2C2', '#52C41A', '#7B61FF', '#F77234', '#D83931', '#C7A252', '#8C8C8C'];

    public function __construct(
        private readonly Connection $connection,
        private readonly OrgAudit $audit,
        private readonly OrgIdempotency $keys,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>} */
    public function listTypes(?string $keyword): array
    {
        $params = [];
        $where = '1=1';
        if ($keyword !== null && $keyword !== '') {
            $where = 'name ILIKE :kw';
            $params['kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.id, t.name, t.code, t.remark, t.is_system, t.extension_key,'
            . ' (SELECT COUNT(*) FROM sand_system_dict_item i WHERE i.type_id = t.id) AS item_count'
            . ' FROM sand_system_dict_type t WHERE ' . $where . ' ORDER BY t.id',
            $params,
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->typeItem($row);
        }

        return ['items' => $items];
    }

    /** @return array<string, mixed> */
    public function showType(int $id): array
    {
        $type = $this->typeItem($this->requireType($id));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT i.id, i.type_id, i.label, i.value, i.color, i.is_default, i.is_system, i.sort, i.status, i.create_time,'
            . ' t.extension_key,'
            . ' (SELECT COUNT(*) FROM sand_system_dict_item_ref r WHERE r.item_id = i.id) AS ref_count'
            . ' FROM sand_system_dict_item i'
            . ' JOIN sand_system_dict_type t ON t.id = i.type_id'
            . ' WHERE i.type_id = ? ORDER BY i.sort, i.id',
            [$id],
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->item($row);
        }
        $type['items'] = $items;

        return $type;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function createType(array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device, ?string $idempotencyKey = null): array
    {
        $draft = $this->typeDraft($body);
        try {
            return $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'dict.type.create', $body, function () use ($draft, $actor, $requestId, $ip, $device): array {
                $createdId = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_dict_type (code, name, remark, created_by, updated_by)'
                    . ' VALUES (:code, :name, :remark, :by, :by) RETURNING id',
                    [
                        'code' => 'dict_' . bin2hex(random_bytes(8)),
                        'name' => $draft['name'],
                        'remark' => $draft['remark'],
                        'by' => $actor->writerId(),
                    ],
                ));
                $this->audit->record($actor, 'dict', 'create', '添加了选项组' . $draft['name'], 'dict_type', (string) $createdId, $draft['name'], $requestId, $ip, $device);

                return $this->typeItem($this->requireType($createdId));
            });
        } catch (\Throwable $error) {
            $this->map($error);
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function updateType(int $id, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $before = $this->requireType($id);
        $draft = $this->typeDraft($body);
        try {
            $this->connection->transactional(function () use ($id, $draft, $actor, $before, $requestId, $ip, $device): void {
                $this->connection->executeStatement(
                    'UPDATE sand_system_dict_type SET name = :name, remark = :remark, updated_by = :by, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                    ['name' => $draft['name'], 'remark' => $draft['remark'], 'by' => $actor->writerId(), 'id' => $id],
                );
                $this->audit->record(
                    $actor,
                    'dict',
                    'update',
                    '保存了选项组' . $draft['name'],
                    'dict_type',
                    (string) $id,
                    $draft['name'],
                    $requestId,
                    $ip,
                    $device,
                    ['name' => ['before' => OrgSql::str($before['name'] ?? null), 'after' => $draft['name']]],
                );
            });
        } catch (\Throwable $error) {
            $this->map($error);
        }

        return $this->typeItem($this->requireType($id));
    }

    public function removeType(int $id, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $row = $this->requireType($id);
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1 || ($row['is_system'] ?? null) === '1';
        $extensionKey = $row['extension_key'] ?? null;
        if ($isSystem || (is_string($extensionKey) && $extensionKey !== '')) {
            throw DictException::invalid([], '系统或插件在用，不能删除');
        }
        $name = OrgSql::str($row['name'] ?? null);
        $this->connection->transactional(function () use ($id, $actor, $name, $requestId, $ip, $device): void {
            $this->connection->executeStatement('DELETE FROM sand_system_dict_type WHERE id = ?', [$id]);
            $this->audit->record($actor, 'dict', 'delete', '删除了选项组' . $name, 'dict_type', (string) $id, $name, $requestId, $ip, $device);
        });
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function createItem(int $typeId, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device, ?string $idempotencyKey = null): array
    {
        $this->requireType($typeId);
        $draft = $this->itemDraft($body);
        try {
            return $this->keys->storeOnce($this->connection, $actor->userId, $idempotencyKey, 'dict.item.create.' . $typeId, $body, function () use ($typeId, $draft, $actor, $requestId, $ip, $device): array {
                if ($draft['is_default']) {
                    $this->connection->executeStatement(
                        'UPDATE sand_system_dict_item SET is_default = false WHERE type_id = ? AND is_default',
                        [$typeId],
                    );
                }
                $createdId = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_dict_item (type_id, label, value, color, is_default, status, sort, created_by, updated_by)'
                    . ' VALUES (:type_id, :label, :value, :color, :is_default, :status, :sort, :by, :by) RETURNING id',
                    [
                        'type_id' => $typeId,
                        'label' => $draft['label'],
                        'value' => 'item_' . bin2hex(random_bytes(8)),
                        'color' => $draft['color'],
                        'is_default' => $draft['is_default'] ? 'true' : 'false',
                        'status' => $draft['status'],
                        'sort' => $draft['sort'],
                        'by' => $actor->writerId(),
                    ],
                ));
                $this->audit->record($actor, 'dict', 'create', '添加了选项' . $draft['label'], 'dict_item', (string) $createdId, $draft['label'], $requestId, $ip, $device);

                return $this->item($this->requireItem($createdId));
            });
        } catch (\Throwable $error) {
            $this->map($error);
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function updateItem(int $id, array $body, OrgActor $actor, string $requestId, ?string $ip, ?string $device): array
    {
        $before = $this->requireItem($id);
        $draft = $this->itemDraft($body);
        $draft['status'] = OrgInput::keptStatus($body, OrgSql::int($before['status'] ?? 1));
        $typeId = OrgSql::int($before['type_id'] ?? 0);
        try {
            $this->connection->transactional(function () use ($id, $typeId, $draft, $actor, $before, $requestId, $ip, $device): void {
                if ($draft['is_default']) {
                    $this->connection->executeStatement(
                        'UPDATE sand_system_dict_item SET is_default = false WHERE type_id = ? AND id <> ? AND is_default',
                        [$typeId, $id],
                    );
                }
                $this->connection->executeStatement(
                    'UPDATE sand_system_dict_item SET label = :label, color = :color, is_default = :is_default,'
                    . ' status = :status, updated_by = :by, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                    [
                        'label' => $draft['label'],
                        'color' => $draft['color'],
                        'is_default' => $draft['is_default'] ? 'true' : 'false',
                        'status' => $draft['status'],
                        'by' => $actor->writerId(),
                        'id' => $id,
                    ],
                );
                $this->audit->record(
                    $actor,
                    'dict',
                    'update',
                    '保存了选项' . $draft['label'],
                    'dict_item',
                    (string) $id,
                    $draft['label'],
                    $requestId,
                    $ip,
                    $device,
                    ['label' => ['before' => OrgSql::str($before['label'] ?? null), 'after' => $draft['label']]],
                );
            });
        } catch (\Throwable $error) {
            $this->map($error);
        }

        return $this->item($this->requireItem($id));
    }

    public function removeItem(int $id, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $row = $this->requireItem($id);
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1 || ($row['is_system'] ?? null) === '1';
        if ($isSystem) {
            throw DictException::invalid([], '系统自带的选项不能删除');
        }
        $label = OrgSql::str($row['label'] ?? null);
        $this->connection->transactional(function () use ($id, $actor, $label, $requestId, $ip, $device): void {
            $this->connection->executeStatement('DELETE FROM sand_system_dict_item WHERE id = ?', [$id]);
            $this->audit->record($actor, 'dict', 'delete', '删除了选项' . $label, 'dict_item', (string) $id, $label, $requestId, $ip, $device);
        });
    }

    public function setItemStatus(int $id, int $status, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $row = $this->requireItem($id);
        $label = OrgSql::str($row['label'] ?? null);
        $this->connection->transactional(function () use ($id, $status, $actor, $label, $requestId, $ip, $device): void {
            $this->connection->executeStatement(
                'UPDATE sand_system_dict_item SET status = :status, update_time = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => $status, 'id' => $id],
            );
            $verb = $status === 2 ? '停用了选项' : '启用了选项';
            $this->audit->record($actor, 'dict', 'update', $verb . $label, 'dict_item', (string) $id, $label, $requestId, $ip, $device);
        });
    }

    /** @param list<int> $itemIds */
    public function sortItems(int $typeId, array $itemIds, OrgActor $actor, string $requestId, ?string $ip, ?string $device): void
    {
        $this->requireType($typeId);
        $this->connection->transactional(function () use ($typeId, $itemIds, $actor, $requestId, $ip, $device): void {
            $sort = 0;
            foreach ($itemIds as $itemId) {
                $this->connection->executeStatement(
                    'UPDATE sand_system_dict_item SET sort = :sort WHERE id = :id AND type_id = :type_id',
                    ['sort' => $sort, 'id' => $itemId, 'type_id' => $typeId],
                );
                $sort++;
            }
            $this->audit->record($actor, 'dict', 'update', '调整了选项顺序', 'dict_type', (string) $typeId, '选项顺序', $requestId, $ip, $device);
        });
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{name: string, remark: ?string}
     */
    private function typeDraft(array $body): array
    {
        $name = OrgInput::requiredText($body, 'name');
        if (mb_strlen($name) > 20) {
            throw DictException::invalid(['name' => ['组名称最多 20 个字']]);
        }
        $remark = OrgInput::optionalText($body, 'remark');
        if ($remark !== null && mb_strlen($remark) > 100) {
            throw DictException::invalid(['remark' => ['说明最多 100 个字']]);
        }

        return ['name' => $name, 'remark' => $remark];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{label: string, color: ?string, is_default: bool, status: int, sort: int}
     */
    private function itemDraft(array $body): array
    {
        $label = OrgInput::requiredText($body, 'label');
        if (mb_strlen($label) > 20) {
            throw DictException::invalid(['label' => ['选项名称最多 20 个字']]);
        }
        $color = OrgInput::optionalText($body, 'color');
        if ($color !== null && !in_array($color, self::COLORS, true)) {
            throw DictException::invalid(['color' => ['颜色不在预设里']]);
        }
        $isDefault = false;
        if (array_key_exists('is_default', $body)) {
            $flag = $body['is_default'];
            if (!is_bool($flag)) {
                throw DictException::invalid(['is_default' => ['格式不正确']]);
            }
            $isDefault = $flag;
        }

        return [
            'label' => $label,
            'color' => $color,
            'is_default' => $isDefault,
            'status' => OrgInput::status($body, 1),
            'sort' => OrgInput::sort($body),
        ];
    }

    /** @return array<string, mixed> */
    private function requireType(int $id): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT t.id, t.name, t.code, t.remark, t.is_system, t.extension_key,'
            . ' (SELECT COUNT(*) FROM sand_system_dict_item i WHERE i.type_id = t.id) AS item_count'
            . ' FROM sand_system_dict_type t WHERE t.id = ?',
            [$id],
        );
        if ($row === false) {
            throw DictException::typeNotFound();
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function requireItem(int $id): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT i.id, i.type_id, i.label, i.value, i.color, i.is_default, i.is_system, i.sort, i.status, i.create_time,'
            . ' t.extension_key,'
            . ' (SELECT COUNT(*) FROM sand_system_dict_item_ref r WHERE r.item_id = i.id) AS ref_count'
            . ' FROM sand_system_dict_item i'
            . ' JOIN sand_system_dict_type t ON t.id = i.type_id'
            . ' WHERE i.id = ?',
            [$id],
        );
        if ($row === false) {
            throw DictException::itemNotFound();
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function typeItem(array $row): array
    {
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1 || ($row['is_system'] ?? null) === '1';

        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'name' => OrgSql::str($row['name'] ?? null),
            'code' => OrgSql::str($row['code'] ?? null),
            'remark' => ($row['remark'] ?? null) === null ? null : OrgSql::str($row['remark']),
            'is_system' => $isSystem,
            'extension_key' => ($row['extension_key'] ?? null) === null ? null : OrgSql::str($row['extension_key']),
            'item_count' => OrgSql::int($row['item_count'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        $isDefault = ($row['is_default'] ?? null) === true || ($row['is_default'] ?? null) === 't' || ($row['is_default'] ?? null) === 1 || ($row['is_default'] ?? null) === '1';
        $isSystem = ($row['is_system'] ?? null) === true || ($row['is_system'] ?? null) === 't' || ($row['is_system'] ?? null) === 1 || ($row['is_system'] ?? null) === '1';

        return [
            'id' => (string) OrgSql::int($row['id'] ?? null),
            'type_id' => (string) OrgSql::int($row['type_id'] ?? null),
            'label' => OrgSql::str($row['label'] ?? null),
            'value' => OrgSql::str($row['value'] ?? null),
            'color' => ($row['color'] ?? null) === null ? null : OrgSql::str($row['color']),
            'is_default' => $isDefault,
            'is_system' => $isSystem,
            'sort' => OrgSql::int($row['sort'] ?? 0),
            'status' => OrgSql::statusName(OrgSql::int($row['status'] ?? 1)),
            'used' => $this->usedFlag($row),
            'create_time' => OrgSql::time($row['create_time'] ?? null) ?? '',
        ];
    }

    /**
     * 有引用行就是已使用。插件组查不到引用时返回 null，调用方按已使用提醒。
     *
     * @param array<string, mixed> $row
     */
    private function usedFlag(array $row): ?bool
    {
        if (OrgSql::int($row['ref_count'] ?? 0) > 0) {
            return true;
        }
        $extensionKey = $row['extension_key'] ?? null;
        if (is_string($extensionKey) && $extensionKey !== '') {
            return null;
        }

        return false;
    }

    private function map(\Throwable $error): never
    {
        if ($error instanceof DictException || $error instanceof OrgException) {
            throw $error;
        }
        $text = $error->getMessage();
        if (str_contains($text, 'uk_sand_system_dict_type_name') || str_contains($text, 'uk_sand_system_dict_type_code')) {
            throw DictException::typeTaken();
        }
        if (str_contains($text, 'uk_sand_system_dict_item_type_label') || str_contains($text, 'uk_sand_system_dict_item_type_value')) {
            throw DictException::itemTaken();
        }
        throw $error;
    }
}
