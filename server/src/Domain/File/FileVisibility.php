<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** 文件可见性，对应 sand_system_file.visibility。 */
enum FileVisibility: string
{
    case Private = 'private';
    case Public = 'public';
}
