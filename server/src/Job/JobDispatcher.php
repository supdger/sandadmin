<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class JobDispatcher
{
    public function __construct(
        private readonly JobStore $store,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $type, array $payload = []): JobRun
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $type) !== 1) {
            throw new InvalidJobException('任务类型不正确');
        }
        foreach (array_keys($payload) as $key) {
            if (!is_string($key)) {
                throw new InvalidJobException('任务载荷必须是 JSON 对象');
            }
        }
        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new InvalidJobException('任务载荷无法编码为 JSON', 0, $e);
        }
        if (strlen($encoded) > 262144) {
            throw new InvalidJobException('任务载荷过大');
        }
        $now = $this->clock->now();
        $job = new JobRun(
            id: $this->ids->next(),
            type: $type,
            status: JobStatus::Pending,
            stage: 'queued',
            progress: 0,
            result: null,
            error: null,
            payload: $payload,
            createdAt: $now,
            updatedAt: $now,
            startedAt: null,
            finishedAt: null,
            lockedBy: null,
        );
        $this->store->insert($job);

        return $job;
    }
}
