<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db;

/**
 * 结果行的类型化读取：列缺失或类型不符抛 UnexpectedValueException。
 * 仓储 hydrate() 用它逐列取值，代替手写 is_* 判断。
 */
final readonly class Row
{
    /** @param array<string, mixed> $row */
    public function __construct(private array $row)
    {
    }

    public function int(string $column): int
    {
        $value = $this->value($column);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw new \UnexpectedValueException("列 {$column} 不是整数");
    }

    public function float(string $column): float
    {
        $value = $this->value($column);
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }
        throw new \UnexpectedValueException("列 {$column} 不是数值");
    }

    public function bool(string $column): bool
    {
        $value = $this->value($column);
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 't' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'f' || $value === 'false') {
            return false;
        }
        throw new \UnexpectedValueException("列 {$column} 不是布尔值");
    }

    public function string(string $column): string
    {
        $value = $this->value($column);
        if (is_string($value)) {
            return $value;
        }
        throw new \UnexpectedValueException("列 {$column} 不是字符串");
    }

    public function dateTime(string $column): \DateTimeImmutable
    {
        $value = $this->value($column);
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception $e) {
                throw new \UnexpectedValueException("列 {$column} 不是时间", 0, $e);
            }
        }
        throw new \UnexpectedValueException("列 {$column} 不是时间");
    }

    public function isNull(string $column): bool
    {
        return $this->value($column) === null;
    }

    /** @return mixed */
    private function value(string $column): mixed
    {
        if (!array_key_exists($column, $this->row)) {
            throw new \UnexpectedValueException("结果缺少列 {$column}");
        }

        return $this->row[$column];
    }
}
