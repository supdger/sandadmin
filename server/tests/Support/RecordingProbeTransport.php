<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Module\Package\ProbeTransport;

/** 探测传输替身：记调用次数与目标地址，回固定应答或抛错。 */
final class RecordingProbeTransport implements ProbeTransport
{
    /** @var list<string> */
    private array $urls = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly string $body = '{}',
        private readonly ?\Throwable $error = null,
    ) {
    }

    public function get(string $url, array $headers): array
    {
        $this->urls[] = $url;
        if ($this->error !== null) {
            throw $this->error;
        }

        return ['status' => $this->status, 'body' => $this->body];
    }

    public function calls(): int
    {
        return count($this->urls);
    }

    /** @return list<string> */
    public function urls(): array
    {
        return $this->urls;
    }
}
