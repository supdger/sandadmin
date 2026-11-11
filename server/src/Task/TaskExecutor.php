<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 单轮执行：认领一个任务、交给对应处理器、按结果回写终态。
 * 租约、并发上限、重试次数由 TaskClaimPolicy 在认领阶段保证；
 * 超时由 TaskProgress 心跳与执行器兜底双重判定。
 */
final class TaskExecutor
{
    /** result 摘要上限，防止超大 JSON 写库。 */
    private const RESULT_MAX_BYTES = 262144;

    public function __construct(
        private readonly TaskStore $store,
        private readonly TaskHandlerFactory $handlers,
        private readonly Clock $clock,
        private readonly string $workerId,
        private readonly TaskConfig $config,
    ) {
    }

    /**
     * 执行一个任务并返回其最新状态；没有可认领任务时返回 null。
     */
    public function tick(): ?TaskRun
    {
        $now = $this->clock->now();
        $claimed = $this->store->claimNext($this->workerId, $now, $this->config->claimPolicy());
        if ($claimed === null) {
            return null;
        }
        $publicId = $claimed->publicId;

        try {
            $handler = $this->handlers->forType($claimed->type);
            $timeout = $this->config->timeoutFor($claimed->type);
            $modified = $timeout > 0 ? $now->modify(sprintf('+%d seconds', $timeout)) : null;
            $deadline = $modified instanceof \DateTimeImmutable ? $modified : null;
            $progress = new TaskProgress(
                $this->store,
                $this->clock,
                $publicId,
                $this->workerId,
                $this->config->leaseSeconds,
                $deadline,
            );
            $result = $handler->handle($claimed, $progress);
            $this->assertResult($result);
            if ($deadline !== null && $this->clock->now() >= $deadline) {
                $this->store->markFailed($publicId, $this->workerId, 'timeout', '任务执行超时', $this->clock->now());
            } else {
                $this->store->markSucceeded($publicId, $this->workerId, $result, $this->clock->now());
            }
        } catch (TaskTimeoutException) {
            $this->finishFailed($publicId, 'timeout', '任务执行超时');
        } catch (UnknownTaskTypeException) {
            $this->finishFailed($publicId, 'unknown_type', '任务类型未注册');
        } catch (InvalidTaskException) {
            $this->finishFailed($publicId, 'invalid_result', '任务结果不正确');
        } catch (\Throwable $exception) {
            $this->report($exception, $publicId);
            $this->finishFailed($publicId, 'handler_failed', '任务执行失败');
        }

        return $this->store->findByPublicId($publicId);
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private function assertResult(?array $result): void
    {
        if ($result === null) {
            return;
        }
        try {
            $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidTaskException('任务结果不是合法 JSON', 0, $exception);
        }
        if (strlen($encoded) > self::RESULT_MAX_BYTES) {
            throw new InvalidTaskException('任务结果超过大小上限');
        }
    }

    private function finishFailed(string $publicId, string $errorCode, string $errorMessage): void
    {
        try {
            $this->store->markFailed($publicId, $this->workerId, $errorCode, $errorMessage, $this->clock->now());
        } catch (TaskStorageException $exception) {
            // 租约已易主：记录已由新认领者接管，不再覆盖
            $this->report($exception, $publicId);
        }
    }

    private function report(\Throwable $exception, string $publicId): void
    {
        error_log(sprintf(
            'task_run %s 执行异常 %s: %s',
            $publicId,
            $exception::class,
            $exception->getMessage()
        ));
    }
}
