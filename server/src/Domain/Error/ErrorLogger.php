<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Error;

/** 记录不可展示给调用方的异常。实现位于 Infra，且不得记录凭证。 */
interface ErrorLogger
{
    public function unexpected(\Throwable $error, string $requestId): void;
}
