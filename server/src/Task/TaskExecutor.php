<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

use Psr\Log\LoggerInterface;
use Sandadmin\Http\RequestIds;
use Sandadmin\Infra\Log\RequestScope;

/**
 * 单轮执行：认领一个任务、交给对应处理器、按结果回写终态。
 * 租约、并发上限、重试次数由 TaskClaimPolicy 在认领阶段保证；
 * 超时由 TaskProgress 心跳与执行器兜底双重判定。
 *
 * 可观测：认领后把 payload.__context.request_id（TaskDispatcher 写入的
 * 发起请求编号）连同 task_id、attempt 绑进 RequestScope，本任务期间的
 * Log 记录自动带这三个字段；结束 finally 清空，不串到下一任务。
 * __context 是保留键，handler 约定不读。
 */
final class TaskExecutor
{
    /** result 摘要上限，防止超大 JSON 写库。 */
    private const RESULT_MAX_BYTES = 262144;

    /** payload 中由 dispatcher 写入的执行上下文保留键。 */
    private const CONTEXT_KEY = '__context';

    public function __construct(
        private readonly TaskStore $store,
        private readonly TaskHandlerFactory $handlers,
        private readonly Clock $clock,
        private readonly string $workerId,
        private readonly TaskConfig $config,
        private readonly ?RequestScope $scope = null,
        private readonly ?LoggerInterface $logger = null,
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
        $scope = $this->scope ?? new RequestScope();
        $scope->bind($this->contextRequestId($claimed->payload), $publicId, $claimed->attempt);

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
        } finally {
            $scope->clear();
        }

        return $this->store->findByPublicId($publicId);
    }

    /**
     * 读 payload.__context.request_id；payload 是落库 JSON，可能被手工
     * 改过，格式不合法的一律按无 request_id 处理，不阻断任务执行。
     *
     * @param array<string, mixed> $payload
     */
    private function contextRequestId(array $payload): ?string
    {
        $context = $payload[self::CONTEXT_KEY] ?? null;
        if (!is_array($context)) {
            return null;
        }
        $requestId = $context['request_id'] ?? null;
        if (!is_string($requestId) || preg_match(RequestIds::PATTERN, $requestId) !== 1) {
            return null;
        }

        return $requestId;
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
        $message = sprintf(
            'task_run %s 执行异常 %s: %s',
            $publicId,
            $exception::class,
            $exception->getMessage()
        );
        $logger = $this->logger;
        if ($logger !== null) {
            // request_id/task_id/attempt 由 RequestScope processor 自动注入
            $logger->error($message, ['exception' => $exception::class]);

            return;
        }
        error_log($message);
    }
}
