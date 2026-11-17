<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 流式连接的断开原因。指标按这里取值计数；errorCode() 给出断开前
 * 随错误事件发向客户端的契约码（错误码总表登记项），null 表示不发码。
 */
enum StreamCloseReason: string
{
    case Completed = 'completed';
    case ClientGone = 'client_gone';
    case Idle = 'idle';
    case DurationLimit = 'duration_limit';
    case Revoked = 'revoked';
    case Failed = 'failed';

    public function errorCode(): ?string
    {
        return match ($this) {
            self::Idle => 'STREAM_IDLE_TIMEOUT',
            self::DurationLimit => 'STREAM_DURATION_LIMIT',
            self::Revoked => 'STREAM_AUTHZ_REVOKED',
            self::Failed => 'INTERNAL_ERROR',
            self::Completed, self::ClientGone => null,
        };
    }
}
