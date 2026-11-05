<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

interface FileUsageRegistry
{
    public function register(FileUsageRef $ref): void;

    public function unregister(FileUsageRef $ref): void;

    public function countFor(string $fileId): int;

    public function listFor(string $fileId): FileUsageList;
}
