<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db\Query;

enum SortDirection: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}