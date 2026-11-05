<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class JobExecutor
{
    public function __construct(
        private readonly JobStore $store,
        private readonly JobHandlerFactory $handlers,
        private readonly Clock $clock,
        private readonly string $workerId,
    ) {
    }

    public function tick(): ?JobRun
    {
        $claimed = $this->store->claimNext($this->workerId, $this->clock->now());
        if ($claimed === null) {
            return null;
        }
        try {
            $handler = $this->handlers->forType($claimed->type);
            $result = $handler->handle(
                $claimed,
                new JobProgress($this->store, $this->clock, $claimed->id),
            );
            $this->assertResult($result);
            $this->store->markSucceeded($claimed->id, $result, $this->clock->now());
        } catch (\Throwable $e) {
            $this->store->markFailed($claimed->id, $this->safeMessage($e), $this->clock->now());
        }

        return $this->store->find($claimed->id);
    }

    /**
     * @param array<mixed>|null $result
     */
    private function assertResult(?array $result): void
    {
        if ($result === null) {
            return;
        }
        try {
            $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new InvalidJobException('任务结果无法编码为 JSON', 0, $e);
        }
        if (strlen($encoded) > 262144) {
            throw new InvalidJobException('任务结果过大');
        }
    }

    private function safeMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            $message = '任务执行失败';
        }
        $message = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message);
        if (!is_string($message) || $message === '') {
            $message = '任务执行失败';
        }
        if (strlen($message) > 2000) {
            $message = substr($message, 0, 2000);
        }

        return $message;
    }
}
