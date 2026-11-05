<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\Filter;
use Sandadmin\Infra\Db\Query\FilterOperator;
use Sandadmin\Infra\Db\Query\ListQuery;
use Sandadmin\Infra\Db\Query\Page;

/**
 * DBAL 仓储基类（无 ORM / EntityManager / Active Record）。
 *
 * 约定：
 * - 子类 per-table，`final`；只暴露返回 VO / Page / ?VO / int / void 的公有方法，不返回裸数组。
 * - 对外字段名（API 字段）→ 列名 映射由 fieldMap() 白名单给出；排序/筛选只允许白名单字段，防注入。
 * - 无可变状态：仅持有构造注入的 Connection（由 Pool 按请求借出）；每次调用新建 QueryBuilder。
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
     * API 字段 => 列名；可排序/筛选字段白名单。
     *
     * @return array<string, string>
     */
    abstract protected function fieldMap(): array;

    /**
     * @param array<string, mixed> $row
     *
     * @return T
     */
    abstract protected function hydrate(array $row): object;

    /**
     * 未传 sort 时的默认排序（列名 => ASC|DESC）。
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    protected function defaultOrder(): array
    {
        return [$this->primaryKey() => 'DESC'];
    }

    protected function qb(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }

    /** @return T|null */
    protected function findOneBy(string $column, int|string $value): ?object
    {
        $row = $this->qb()->select('*')->from($this->table())
            ->where($this->connection->quoteSingleIdentifier($column) . ' = :v')
            ->setParameter('v', $value)->setMaxResults(1)
            ->executeQuery()->fetchAssociative();

        return $row === false ? null : $this->hydrate($row);
    }

    /** @return T|null */
    protected function findById(int|string $id): ?object
    {
        return $this->findOneBy($this->primaryKey(), $id);
    }

    /** @return Page<T> */
    protected function paginate(ListQuery $query, ?QueryBuilder $base = null): Page
    {
        $base ??= $this->qb()->from($this->table());
        $this->applyFilters($base, $query);

        $count = (clone $base)->select('COUNT(*)')->executeQuery()->fetchOne();
        $total = is_numeric($count) ? (int) $count : 0;

        $list = (clone $base)->select('*');
        $this->applySort($list, $query);
        $list->setFirstResult($query->offset())->setMaxResults($query->pageSize);

        $items = [];
        foreach ($list->executeQuery()->iterateAssociative() as $row) {
            $items[] = $this->hydrate($row);
        }

        return new Page($items, $total, $query->page, $query->pageSize);
    }

    /** @param array<string, mixed> $data 列名 => 值 */
    protected function insertRow(array $data): int
    {
        return (int) $this->connection->insert($this->table(), $data);
    }

    /** @param array<string, mixed> $data */
    protected function updateRow(int|string $id, array $data): int
    {
        return (int) $this->connection->update($this->table(), $data, [$this->primaryKey() => $id]);
    }

    protected function deleteRow(int|string $id): int
    {
        return (int) $this->connection->delete($this->table(), [$this->primaryKey() => $id]);
    }

    private function column(string $field, string $kind): string
    {
        $map = $this->fieldMap();
        if (!isset($map[$field])) {
            throw new InvalidQueryException("不允许的{$kind}字段：{$field}", [$kind === '排序' ? 'sort' : "filters.$field" => 'not_allowed']);
        }

        return $this->connection->quoteSingleIdentifier($map[$field]);
    }

    private function applySort(QueryBuilder $qb, ListQuery $query): void
    {
        if ($query->sort === []) {
            foreach ($this->defaultOrder() as $col => $dir) {
                $qb->addOrderBy($this->connection->quoteSingleIdentifier($col), $dir);
            }

            return;
        }
        foreach ($query->sort as $sort) {
            $qb->addOrderBy($this->column($sort->field, '排序'), $sort->direction->value);
        }
    }

    private function applyFilters(QueryBuilder $qb, ListQuery $query): void
    {
        foreach ($query->filters as $i => $filter) {
            $qb->andWhere($this->filterExpr($qb, $filter, 'f' . $i));
        }
    }

    private function filterExpr(QueryBuilder $qb, Filter $f, string $param): string
    {
        $col = $this->column($f->field, '筛选');
        $expr = $qb->expr();
        if ($f->operator === FilterOperator::In) {
            $qb->setParameter($param, (array) $f->value, ArrayParameterType::STRING);

            return $expr->in($col, ':' . $param);
        }
        if ($f->operator === FilterOperator::IsNull) {
            return $expr->isNull($col);
        }
        if ($f->operator === FilterOperator::NotNull) {
            return $expr->isNotNull($col);
        }
        if (!is_scalar($f->value)) {
            throw new InvalidQueryException('筛选值必须为标量', ["filters.{$f->field}" => 'scalar']);
        }
        $value = $f->operator === FilterOperator::Like
            ? '%' . addcslashes((string) $f->value, '%_\\') . '%'
            : $f->value;
        $qb->setParameter($param, $value);
        $p = ':' . $param;

        return match ($f->operator) {
            FilterOperator::Eq => $expr->eq($col, $p),
            FilterOperator::Neq => $expr->neq($col, $p),
            FilterOperator::Gt => $expr->gt($col, $p),
            FilterOperator::Gte => $expr->gte($col, $p),
            FilterOperator::Lt => $expr->lt($col, $p),
            FilterOperator::Lte => $expr->lte($col, $p),
            FilterOperator::Like => $col . " LIKE $p ESCAPE '\\'",
        };
    }
}