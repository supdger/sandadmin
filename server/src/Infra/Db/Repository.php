<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\ListQuery;
use Sandadmin\Infra\Db\Query\Page;

/**
 * DBAL 仓储基类（无 ORM / EntityManager / Active Record）。
 *
 * 约定：子类 per-table 且 final；公有方法只返回 VO/Page/?VO/int/bool；查询列由
 * columns() 显式列出（禁 SELECT *），hydrate() 用 Row 取类型化值；排序/筛选字段
 * 走 fieldMap() 白名单，筛选运算符由服务端固定，具名参数由各模块查询对象声明；
 * 模糊匹配仅 likeFields() 声明的列且只做前缀；无可变状态，仅持有构造注入的 Connection。
 *
 * @template T of object
 */
abstract class Repository
{
    public function __construct(protected readonly Connection $connection)
    {
    }

    abstract protected function table(): string;

    protected function primaryKey(): string
    {
        return 'id';
    }

    /**
     * SELECT 显式列（禁止 SELECT *）。
     *
     * @return list<string>
     */
    abstract protected function columns(): array;

    /**
     * 排序/筛选字段白名单：接口字段名 => 列名。
     *
     * @return array<string, string>
     */
    abstract protected function fieldMap(): array;

    /**
     * 允许前缀模糊匹配的白名单字段（键与 fieldMap 一致）；只允许有索引支撑的列。
     *
     * @return list<string>
     */
    protected function likeFields(): array
    {
        return [];
    }

    /**
     * @return T
     */
    abstract protected function hydrate(Row $row): object;

    /**
     * 未传 sort 时的默认排序（列名 => ASC|DESC）。
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    protected function defaultOrder(): array
    {
        return [$this->primaryKey() => 'DESC'];
    }

    protected function queryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }

    /** @return T|null */
    protected function findOneBy(string $column, int|string $value): ?object
    {
        $queryBuilder = $this->queryBuilder()->select(...$this->columns())->from($this->table());
        $row = $queryBuilder->where($queryBuilder->expr()->eq($this->connection->quoteSingleIdentifier($column), ':v'))
            ->setParameter('v', $value)->setMaxResults(1)
            ->executeQuery()->fetchAssociative();

        return $row === false ? null : $this->hydrate(new Row($row));
    }

    /** @return T|null */
    protected function findById(int|string $id): ?object
    {
        return $this->findOneBy($this->primaryKey(), $id);
    }

    /** @return Page<T> */
    protected function paginate(ListQuery $query, ?QueryBuilder $base = null): Page
    {
        $base ??= $this->queryBuilder()->from($this->table());

        $count = (clone $base)->select('COUNT(*)')->executeQuery()->fetchOne();
        $total = is_numeric($count) ? (int) $count : 0;

        $list = (clone $base)->select(...$this->columns());
        $this->applySort($list, $query);
        $list->setFirstResult($query->offset())->setMaxResults($query->pageSize);

        $items = [];
        foreach ($list->executeQuery()->iterateAssociative() as $row) {
            $items[] = $this->hydrate(new Row($row));
        }

        return new Page($items, $total, $query->page, $query->pageSize);
    }

    /** @param array<string, mixed> $data 列名 => 值 */
    protected function insertRow(array $data): int
    {
        return (int) $this->connection->insert($this->table(), $this->bindValues($data));
    }

    /** @param array<string, mixed> $data */
    protected function updateRow(int|string $id, array $data): int
    {
        return (int) $this->connection->update($this->table(), $this->bindValues($data), [$this->primaryKey() => $id]);
    }

    protected function deleteRow(int|string $id): int
    {
        return (int) $this->connection->delete($this->table(), [$this->primaryKey() => $id]);
    }

    /**
     * 等值筛选；$field 为 fieldMap 白名单键，$param 为绑定参数名（同一 QueryBuilder 内不可重复）。
     * 值为 null 时不加条件。
     *
     * @param scalar|\DateTimeInterface|null $value
     */
    protected function whereEq(QueryBuilder $queryBuilder, string $field, string|int|float|bool|\DateTimeInterface|null $value, string $param): void
    {
        $this->compare($queryBuilder, $field, $param, 'eq', $value);
    }

    /** @param scalar|\DateTimeInterface|null $value */
    protected function whereNeq(QueryBuilder $queryBuilder, string $field, string|int|float|bool|\DateTimeInterface|null $value, string $param): void
    {
        $this->compare($queryBuilder, $field, $param, 'neq', $value);
    }

    /** @param scalar|\DateTimeInterface|null $value */
    protected function whereGt(QueryBuilder $queryBuilder, string $field, string|int|float|bool|\DateTimeInterface|null $value, string $param): void
    {
        $this->compare($queryBuilder, $field, $param, 'gt', $value);
    }

    /** @param scalar|\DateTimeInterface|null $value */
    protected function whereGte(QueryBuilder $queryBuilder, string $field, string|int|float|bool|\DateTimeInterface|null $value, string $param): void
    {
        $this->compare($queryBuilder, $field, $param, 'gte', $value);
    }

    /** @param scalar|\DateTimeInterface|null $value */
    protected function whereLt(QueryBuilder $queryBuilder, string $field, string|int|float|bool|\DateTimeInterface|null $value, string $param): void
    {
        $this->compare($queryBuilder, $field, $param, 'lt', $value);
    }

    /** @param scalar|\DateTimeInterface|null $value */
    protected function whereLte(QueryBuilder $queryBuilder, string $field, string|int|float|bool|\DateTimeInterface|null $value, string $param): void
    {
        $this->compare($queryBuilder, $field, $param, 'lte', $value);
    }

    /**
     * 前缀模糊筛选（LIKE '值%'，可走索引）。仅 likeFields() 声明的字段可用，值里的 % _ \ 会被转义。
     */
    protected function whereLikePrefix(QueryBuilder $queryBuilder, string $field, ?string $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!in_array($field, $this->likeFields(), true)) {
            throw new InvalidQueryException("字段不支持模糊查询：{$field}", [$field => 'like_not_allowed']);
        }
        $queryBuilder->setParameter($param, addcslashes($value, '%_\\') . '%');
        $queryBuilder->andWhere($queryBuilder->expr()->like($this->column($field, '筛选'), ':' . $param, "'\\'"));
    }

    /**
     * IN 列表筛选；$values 为空不加条件。
     *
     * @param list<scalar>|null $values
     */
    protected function whereIn(QueryBuilder $queryBuilder, string $field, ?array $values, string $param): void
    {
        if ($values === null || $values === []) {
            return;
        }
        $queryBuilder->andWhere($queryBuilder->expr()->in($this->column($field, '筛选'), ':' . $param));
        $queryBuilder->setParameter($param, $values, ArrayParameterType::STRING);
    }

    /**
     * 比较类筛选的公共实现：运算符由 $op 固定映射，值绑定参数；null 不加条件。
     *
     * @param scalar|\DateTimeInterface|null $value
     */
    private function compare(QueryBuilder $queryBuilder, string $field, string $param, string $op, string|int|float|bool|\DateTimeInterface|null $value): void
    {
        if ($value === null) {
            return;
        }
        $expr = $queryBuilder->expr();
        $col = $this->column($field, '筛选');
        $placeholder = ':' . $param;
        $queryBuilder->setParameter($param, $this->bindValue($value));
        $queryBuilder->andWhere(match ($op) {
            'eq' => $expr->eq($col, $placeholder),
            'neq' => $expr->neq($col, $placeholder),
            'gt' => $expr->gt($col, $placeholder),
            'gte' => $expr->gte($col, $placeholder),
            'lt' => $expr->lt($col, $placeholder),
            'lte' => $expr->lte($col, $placeholder),
            default => throw new \LogicException("未知筛选运算：{$op}"),
        });
    }

    /**
     * DBAL 把未显式声明类型的参数按字符串绑定，DateTimeInterface 需先转成
     * 带时区偏移的 ISO 8601（timestamptz 按字面量解析，不能丢时区）。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function bindValues(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->bindValue($value);
        }

        return $data;
    }

    private function bindValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        // pgsql 布尔列不接受空串：PHP false 未声明类型会绑定成 ''，显式转字面量
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    /**
     * 白名单键 => 引用列名；键未声明抛 InvalidQueryException。
     */
    private function column(string $field, string $kind): string
    {
        $map = $this->fieldMap();
        if (!isset($map[$field])) {
            throw new InvalidQueryException("不允许的{$kind}字段：{$field}", [$kind === '排序' ? 'sort' : $field => 'not_allowed']);
        }

        return $this->connection->quoteSingleIdentifier($map[$field]);
    }

    private function applySort(QueryBuilder $queryBuilder, ListQuery $query): void
    {
        if ($query->sort === []) {
            foreach ($this->defaultOrder() as $col => $dir) {
                $queryBuilder->addOrderBy($this->connection->quoteSingleIdentifier($col), $dir);
            }

            return;
        }
        foreach ($query->sort as $sort) {
            $queryBuilder->addOrderBy($this->column($sort->field, '排序'), $sort->direction->value);
        }
    }
}
