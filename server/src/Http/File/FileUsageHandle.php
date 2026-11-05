<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

use Doctrine\DBAL\Connection;

final readonly class FileUsageHandle
{
    public function __construct(
        public FileUsageHttp $http,
        public Connection $connection,
    ) {
    }

    public function close(): void
    {
        $this->connection->close();
    }
}
