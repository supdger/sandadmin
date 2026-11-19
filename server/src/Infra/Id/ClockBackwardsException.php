<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Id;

/** 时钟回拨达到拒发阈值，或时钟早于发号器纪元；继续发号会产生重复号，直接拒绝。 */
final class ClockBackwardsException extends \RuntimeException
{
}
