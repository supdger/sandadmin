<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db\Query;

/** 单个筛选条件。$value：In 为 list<scalar>；IsNull/NotNull 为 null；其余为 scalar。 */
final readonly class Filter
{
    /** @param scalar|list<scalar>|null $value */
    public function __construct(
        public string $field,
        public FilterOperator $operator,
        public string|int|float|bool|array|null $value = null,
    ) {
    }
}