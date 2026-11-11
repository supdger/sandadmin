<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

use Sandadmin\Task\TaskState;

/**
 * 生命周期申请详情：对外编号一律 public_id（uuid），不暴露内部自增 id。
 * cancelled 态原样带出，前端据此显示「已取消，系统没有改动」。
 */
final readonly class LifecycleRequest
{
    /** @param list<array{code: string, from_version: ?string, to_version: ?string, state: string}>|null $items */
    public function __construct(
        public string $publicId,
        public LifecycleKind $kind,
        public string $targetCode,
        public string $source,
        public ?string $fromVersion,
        public ?string $toVersion,
        public bool $purgeData,
        public ?array $items,
        public TaskState $state,
        public ?string $stage,
        public ?LifecycleStage $stageKey,
        public int $progress,
        public bool $rolledBack,
        public ?string $errorCode,
        public ?string $errorMessage,
        public int $requestedBy,
        public \DateTimeImmutable $createTime,
        public ?\DateTimeImmutable $startTime,
        public ?\DateTimeImmutable $finishTime,
    ) {
    }

    public function visibleTo(LifecycleActor $actor): bool
    {
        return $actor->viewAll || $this->requestedBy === $actor->userId;
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->publicId,
            'kind' => $this->kind->value,
            'target_code' => $this->targetCode,
            'source' => $this->source,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'purge_data' => $this->purgeData,
            'items' => $this->items,
            'state' => $this->state->value,
            'stage' => $this->stage,
            'stage_key' => $this->stageKey?->value,
            'progress' => $this->progress,
            'rolled_back' => $this->rolledBack,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'create_time' => $this->createTime->format(DATE_ATOM),
            'start_time' => $this->startTime?->format(DATE_ATOM),
            'finish_time' => $this->finishTime?->format(DATE_ATOM),
        ];
    }
}
