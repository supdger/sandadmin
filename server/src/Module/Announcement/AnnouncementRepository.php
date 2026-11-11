<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Db\Query\Page;
use Sandadmin\Infra\Db\Repository;
use Sandadmin\Infra\Db\Row;

/**
 * sand_system_announcement 仓储。公告本体只负责主表行；
 * 部门范围、已读、图片引用、应收人数经同一连接在同事务里读写。
 *
 * @extends Repository<Announcement>
 */
final class AnnouncementRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_system_announcement';
    }

    protected function columns(): array
    {
        return [
            'id', 'title', 'content', 'scope', 'level', 'pinned', 'state',
            'published_time', 'expire_time', 'create_time', 'update_time',
            'created_by', 'updated_by',
        ];
    }

    protected function fieldMap(): array
    {
        return [
            'id' => 'id',
            'title' => 'title',
            'scope' => 'scope',
            'level' => 'level',
            'pinned' => 'pinned',
            'state' => 'state',
            'published_time' => 'published_time',
            'expire_time' => 'expire_time',
            'create_time' => 'create_time',
            'update_time' => 'update_time',
        ];
    }

    protected function likeFields(): array
    {
        return ['title'];
    }

    protected function defaultOrder(): array
    {
        return ['pinned' => 'DESC', 'id' => 'DESC'];
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function findById(int|string $id): ?Announcement
    {
        return $this->findOneBy('id', $id);
    }

    /** @return Page<Announcement> */
    public function search(AnnouncementQuery $query): Page
    {
        $builder = $this->queryBuilder()->from($this->table());
        $this->whereLikePrefix($builder, 'title', $query->keyword, 'kw');
        if ($query->state === AnnouncementState::DERIVED_EXPIRED) {
            $builder->andWhere('expire_time IS NOT NULL AND expire_time <= :now');
            $builder->setParameter('now', new \DateTimeImmutable()->format(DATE_ATOM));
        } elseif ($query->state !== null) {
            $this->whereEq($builder, 'state', $query->state, 'st');
        }

        return $this->paginate($query, $builder);
    }

    public function create(AnnouncementDraft $draft, int $userId): int
    {
        // insert() 只回受影响行数，identity 主键要用 RETURNING 取
        $now = new \DateTimeImmutable();
        $id = $this->connection->fetchOne(
            'INSERT INTO sand_system_announcement'
            . ' (title, content, scope, level, pinned, state, expire_time,'
            . '  create_time, update_time, created_by, updated_by)'
            . ' VALUES (:title, :content, :scope, :level, :pinned, :state, :expire_time,'
            . '  :create_time, :update_time, :created_by, :updated_by)'
            . ' RETURNING id',
            [
                'title' => $draft->title,
                'content' => $draft->content,
                'scope' => $draft->scope->value,
                'level' => $draft->level->value,
                'pinned' => $draft->pinned ? 'true' : 'false',
                'state' => AnnouncementState::Draft->value,
                'expire_time' => $draft->expireTime?->format(DATE_ATOM),
                'create_time' => $now->format(DATE_ATOM),
                'update_time' => $now->format(DATE_ATOM),
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
        if (!is_numeric($id)) {
            throw new \RuntimeException('公告创建后未取到编号');
        }

        return (int) $id;
    }

    public function update(int $id, AnnouncementDraft $draft, int $userId): void
    {
        $this->updateRow($id, [
            'title' => $draft->title,
            'content' => $draft->content,
            'scope' => $draft->scope->value,
            'level' => $draft->level->value,
            'pinned' => $draft->pinned,
            'expire_time' => $draft->expireTime,
            'update_time' => new \DateTimeImmutable(),
            'updated_by' => $userId,
        ]);
    }

    public function publish(int $id, int $userId): void
    {
        // 重新发布保留首次发布时间：已读记录不清空，老收件人不再弹窗
        $firstPublished = $this->connection->fetchOne(
            'SELECT published_time FROM sand_system_announcement WHERE id = :id',
            ['id' => $id],
        );
        $this->updateRow($id, [
            'state' => AnnouncementState::Published->value,
            'published_time' => is_string($firstPublished) && $firstPublished !== ''
                ? $firstPublished
                : new \DateTimeImmutable(),
            'update_time' => new \DateTimeImmutable(),
            'updated_by' => $userId,
        ]);
    }

    public function withdraw(int $id, int $userId): void
    {
        $this->updateRow($id, [
            'state' => AnnouncementState::Withdrawn->value,
            'update_time' => new \DateTimeImmutable(),
            'updated_by' => $userId,
        ]);
    }

    public function remove(int $id): void
    {
        // 已读记录与部门范围由外键级联清理
        $this->deleteRow($id);
    }

    /** @return list<int> */
    public function deptIdsFor(int $announcementId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT dept_id FROM sand_system_announcement_dept WHERE announcement_id = :id ORDER BY dept_id',
            ['id' => $announcementId],
        );

        return self::toIntList($rows);
    }

    /**
     * @param list<int> $deptIds
     *
     * @return list<string>
     */
    public function deptNames(array $deptIds): array
    {
        if ($deptIds === []) {
            return [];
        }
        $rows = $this->connection->fetchFirstColumn(
            'SELECT name FROM sand_system_dept WHERE id IN (:ids) ORDER BY id',
            ['ids' => $deptIds],
            ['ids' => ArrayParameterType::INTEGER],
        );

        return self::toStringList($rows);
    }

    /** @param list<int> $deptIds */
    public function replaceDepts(int $announcementId, array $deptIds): void
    {
        $this->connection->delete('sand_system_announcement_dept', ['announcement_id' => $announcementId]);
        foreach ($deptIds as $deptId) {
            $this->connection->insert('sand_system_announcement_dept', [
                'announcement_id' => $announcementId,
                'dept_id' => $deptId,
            ]);
        }
    }

    /**
     * @param list<int> $deptIds
     *
     * @return list<int> 实际存在的部门编号
     */
    public function existingDeptIds(array $deptIds): array
    {
        if ($deptIds === []) {
            return [];
        }
        $rows = $this->connection->fetchFirstColumn(
            'SELECT id FROM sand_system_dept WHERE id IN (:ids)',
            ['ids' => $deptIds],
            ['ids' => ArrayParameterType::INTEGER],
        );

        return self::toIntList($rows);
    }

    /**
     * @param list<int> $fileIds
     *
     * @return list<int> 存在且未软删的文件编号
     */
    public function existingFileIds(array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }
        $rows = $this->connection->fetchFirstColumn(
            'SELECT id FROM sand_system_file WHERE delete_time IS NULL AND id IN (:ids)',
            ['ids' => $fileIds],
            ['ids' => ArrayParameterType::INTEGER],
        );

        return self::toIntList($rows);
    }

    /** @param list<int> $fileIds */
    public function replaceImageUsages(int $announcementId, array $fileIds, string $title): void
    {
        $this->connection->delete('sand_system_file_usage', [
            'subject_type' => 'announcement',
            'subject_id' => (string) $announcementId,
        ]);
        foreach ($fileIds as $fileId) {
            $this->connection->insert('sand_system_file_usage', [
                'file_id' => $fileId,
                'subject_type' => 'announcement',
                'subject_id' => (string) $announcementId,
                'label' => '公告《' . mb_substr($title, 0, 40) . '》',
                'create_time' => new \DateTimeImmutable()->format(DATE_ATOM),
            ]);
        }
    }

    public function removeImageUsages(int $announcementId): void
    {
        $this->connection->delete('sand_system_file_usage', [
            'subject_type' => 'announcement',
            'subject_id' => (string) $announcementId,
        ]);
    }

    /** @return list<int> */
    public function imageFileIdsFor(int $announcementId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            "SELECT file_id FROM sand_system_file_usage WHERE subject_type = 'announcement' AND subject_id = :id ORDER BY file_id",
            ['id' => (string) $announcementId],
        );

        return self::toIntList($rows);
    }

    public function readCountFor(int $announcementId): int
    {
        return self::countOf($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_announcement_read WHERE announcement_id = :id',
            ['id' => $announcementId],
        ));
    }

    /**
     * 应收人数：启用且未软删的用户；scope=dept 只算清单内部门（不含下级）。
     *
     * @param list<int> $deptIds
     */
    public function recipientCount(AnnouncementScope $scope, array $deptIds): int
    {
        if ($scope === AnnouncementScope::All) {
            return self::countOf($this->connection->fetchOne(
                'SELECT COUNT(*) FROM sand_system_user WHERE status = 1 AND delete_time IS NULL',
            ));
        }
        if ($deptIds === []) {
            return 0;
        }

        return self::countOf($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user'
            . ' WHERE status = 1 AND delete_time IS NULL AND dept_id IN (:ids)',
            ['ids' => $deptIds],
            ['ids' => ArrayParameterType::INTEGER],
        ));
    }

    /** 收件人是否已读。 */
    public function hasRead(int $announcementId, int $userId): bool
    {
        return self::countOf($this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_announcement_read'
            . ' WHERE announcement_id = :aid AND user_id = :uid',
            ['aid' => $announcementId, 'uid' => $userId],
        )) > 0;
    }

    public function markRead(int $announcementId, int $userId): void
    {
        // 主键冲突即已读过，幂等
        $this->connection->executeStatement(
            'INSERT INTO sand_system_announcement_read (announcement_id, user_id, read_time)'
            . ' VALUES (:aid, :uid, :time) ON CONFLICT DO NOTHING',
            ['aid' => $announcementId, 'uid' => $userId, 'time' => new \DateTimeImmutable()->format(DATE_ATOM)],
        );
    }

    /**
     * 已读统计明细：收件人（当前有效用户）LEFT JOIN 已读记录。
     *
     * @param list<int> $deptIds
     *
     * @return list<array{user_id: int, username: string, nickname: string, read_time: ?string}>
     */
    public function readStats(AnnouncementScope $scope, array $deptIds, int $announcementId): array
    {
        $params = ['aid' => $announcementId];
        $types = [];
        $scopeSql = '';
        if ($scope === AnnouncementScope::Dept) {
            if ($deptIds === []) {
                return [];
            }
            $scopeSql = ' AND u.dept_id IN (:depts)';
            $params['depts'] = $deptIds;
            $types['depts'] = ArrayParameterType::INTEGER;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.id AS user_id, COALESCE(u.username, \'\') AS username, u.nickname,'
            . ' r.read_time AS read_time'
            . ' FROM sand_system_user u'
            . ' LEFT JOIN sand_system_announcement_read r'
            . '   ON r.user_id = u.id AND r.announcement_id = :aid'
            . ' WHERE u.status = 1 AND u.delete_time IS NULL' . $scopeSql
            . ' ORDER BY r.read_time IS NULL, r.read_time DESC, u.id',
            $params,
            $types,
        );
        $items = [];
        foreach ($rows as $row) {
            $readTime = $row['read_time'];
            $items[] = [
                'user_id' => self::countOf($row['user_id'] ?? 0),
                'username' => is_string($row['username']) ? $row['username'] : '',
                'nickname' => is_string($row['nickname']) ? $row['nickname'] : '',
                'read_time' => is_string($readTime) ? (new \DateTimeImmutable($readTime))->format(DATE_ATOM) : null,
            ];
        }

        return $items;
    }

    /**
     * 重要公告弹窗候选：已发布、未过期、important、本人未读、在本人范围。
     *
     * @return list<Announcement>
     */
    public function popupFor(AnnouncementActor $actor, \DateTimeImmutable $now): array
    {
        $params = [
            'now' => $now->format(DATE_ATOM),
            'uid' => $actor->userId,
        ];
        $types = [];
        $scopeSql = '';
        if ($actor->deptId === null) {
            $scopeSql = " AND a.scope = 'all'";
        } else {
            $scopeSql = " AND (a.scope = 'all' OR EXISTS ("
                . 'SELECT 1 FROM sand_system_announcement_dept d'
                . ' WHERE d.announcement_id = a.id AND d.dept_id = :dept))';
            $params['dept'] = $actor->deptId;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.' . implode(', a.', $this->columns())
            . ' FROM sand_system_announcement a'
            . " WHERE a.state = 'published' AND a.level = 'important'"
            . ' AND (a.expire_time IS NULL OR a.expire_time > :now)'
            . ' AND NOT EXISTS (SELECT 1 FROM sand_system_announcement_read r'
            . '  WHERE r.announcement_id = a.id AND r.user_id = :uid)'
            . $scopeSql
            . ' ORDER BY a.pinned DESC, a.published_time DESC, a.id DESC',
            $params,
            $types,
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrate(new Row($row));
        }

        return $items;
    }

    public function creatorName(?int $createdBy): ?string
    {
        if ($createdBy === null) {
            return null;
        }
        $name = $this->connection->fetchOne(
            'SELECT nickname FROM sand_system_user WHERE id = :id',
            ['id' => $createdBy],
        );

        return is_string($name) ? $name : null;
    }

    /**
     * 行内不含派生字段；dept/read/recipient/image 由服务层装配，
     * hydrate 先给占位值，toApi 前用 withDerived 补全。
     */
    protected function hydrate(Row $row): object
    {
        return new Announcement(
            id: $row->int('id'),
            title: $row->string('title'),
            content: $row->string('content'),
            scope: AnnouncementScope::from($row->string('scope')),
            deptIds: [],
            deptNames: [],
            readCount: 0,
            recipientCount: 0,
            level: AnnouncementLevel::from($row->string('level')),
            pinned: $row->bool('pinned'),
            state: AnnouncementState::from($row->string('state')),
            imageFileIds: [],
            publishedTime: self::dateOrNull($row, 'published_time'),
            expireTime: self::dateOrNull($row, 'expire_time'),
            createdByName: null,
            createTime: $row->dateTime('create_time'),
            updateTime: $row->dateTime('update_time'),
            createdBy: self::intOrNull($row, 'created_by'),
            updatedBy: self::intOrNull($row, 'updated_by'),
        );
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<int>
     */
    private static function toIntList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $result[] = $value;
            } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                $result[] = (int) $value;
            }
        }

        return $result;
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<string>
     */
    private static function toStringList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    private static function countOf(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function dateOrNull(Row $row, string $column): ?\DateTimeImmutable
    {
        return $row->isNull($column) ? null : $row->dateTime($column);
    }

    private static function intOrNull(Row $row, string $column): ?int
    {
        return $row->isNull($column) ? null : $row->int($column);
    }
}
