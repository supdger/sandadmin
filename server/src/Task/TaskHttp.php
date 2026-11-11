<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * /api/admin/v1/tasks 契约形状的组装层：入参校验、可见性判定、
 * Task/PageTask/ErrorEnvelope 输出。不含 HTTP 传输细节。
 *
 * 错误信封与 request_id 的最终实现归 B2 统一错误中间件；
 * 本类先按同一契约形状输出，B2 合入后由控制器改调统一实现。
 */
final class TaskHttp
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    private const TYPE_PATTERN = '/^[a-z][a-z0-9_.]{0,63}$/';

    /**
     * GET /tasks
     *
     * @param array<array-key, mixed> $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(TaskStore $store, ?TaskActor $actor, array $query, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        $fields = [];
        $type = self::stringParam($query, 'type');
        if ($type !== null && preg_match(self::TYPE_PATTERN, $type) !== 1) {
            $fields['type'] = ['任务类型不正确'];
        }
        $state = self::stringParam($query, 'state');
        if ($state !== null && !self::isTaskState($state)) {
            $fields['state'] = ['任务状态不正确'];
        }
        $mine = self::boolParam($query, 'mine', $fields);
        $page = self::intParam($query, 'page', 1, 1, 1000000, 'page', $fields);
        $pageSize = self::intParam($query, 'page_size', 20, 1, 100, 'page_size', $fields);
        if ($fields !== []) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
        }

        // 可见范围：管理员（system:task:index 等效 viewAll）看全部；
        // 普通用户无论 mine 取值都只能看自己发起的任务。
        $createdBy = ($actor->viewAll && $mine !== true) ? null : $actor->userId;
        $result = $store->search($createdBy, $type, $state, $page, $pageSize);

        return self::success([
            'items' => array_map(self::task(...), $result->items),
            'total' => $result->total,
            'page' => $result->page,
            'page_size' => $result->pageSize,
        ], $requestId);
    }

    /**
     * GET /tasks/{task_id}
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function show(TaskStore $store, ?TaskActor $actor, string $taskId, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (preg_match(self::UUID_PATTERN, $taskId) !== 1) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['task_id' => ['任务编号不正确']], $requestId);
        }
        $task = $store->findByPublicId($taskId);
        if ($task === null || !self::visible($task, $actor)) {
            // 不可见与不存在同样返回 404，不泄露任务是否存在
            return self::failure(404, 'TASK_RUN_NOT_FOUND', '任务不存在或已过期', [], $requestId);
        }

        return self::success(self::task($task), $requestId);
    }

    /**
     * GET /tasks/{task_id}/download
     *
     * @return array{status: int, body?: array<string, mixed>, artifact?: TaskArtifact}
     */
    public static function download(
        TaskStore $store,
        ?TaskActor $actor,
        string $taskId,
        TaskArtifactResolver $resolver,
        string $requestId,
    ): array {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (preg_match(self::UUID_PATTERN, $taskId) !== 1) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['task_id' => ['任务编号不正确']], $requestId);
        }
        $task = $store->findByPublicId($taskId);
        if ($task === null || !self::visible($task, $actor)) {
            return self::failure(404, 'TASK_RUN_NOT_FOUND', '任务不存在或已过期', [], $requestId);
        }
        $artifact = $task->state === TaskState::Succeeded ? $resolver->resolve($task) : null;
        if ($artifact === null) {
            // 未成功、产物已清理、无产物统一按不可下载处理
            return self::failure(404, 'TASK_RUN_NOT_DOWNLOADABLE', '结果文件已过期或不存在', [], $requestId);
        }

        return ['status' => 200, 'artifact' => $artifact];
    }

    private static function visible(TaskRun $task, TaskActor $actor): bool
    {
        return $actor->viewAll || $task->createdBy === $actor->userId;
    }

    /**
     * Task schema：id 即 public_id；result 原样透出 {file_id} 等摘要。
     *
     * @return array<string, mixed>
     */
    public static function task(TaskRun $task): array
    {
        return [
            'id' => $task->publicId,
            'type' => $task->type,
            'state' => $task->state->value,
            'progress' => $task->progress,
            'stage' => $task->stage,
            'result' => $task->result === null ? null : self::jsonObject($task->result),
            'error' => $task->errorCode === null && $task->errorMessage === null
                ? null
                : [
                    'code' => $task->errorCode,
                    'message' => $task->errorMessage,
                ],
            'create_time' => $task->createTime->format(\DateTimeInterface::ATOM),
            'start_time' => $task->startTime?->format(\DateTimeInterface::ATOM),
            'finish_time' => $task->finishTime?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function success(array $data, string $requestId): array
    {
        return [
            'status' => 200,
            'body' => ['data' => $data, 'request_id' => $requestId],
        ];
    }

    /**
     * @param array<string, list<string>> $fields
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function failure(int $status, string $code, string $message, array $fields, string $requestId): array
    {
        return [
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields === [] ? new \stdClass() : $fields,
                'request_id' => $requestId,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function stringParam(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, list<string>> $fields
     */
    private static function boolParam(array $query, string $key, array &$fields): ?bool
    {
        $value = $query[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if ($value === 'true' || $value === '1' || $value === true) {
            return true;
        }
        if ($value === 'false' || $value === '0' || $value === false) {
            return false;
        }
        $fields[$key] = ['取值必须是 true 或 false'];

        return null;
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, list<string>> $fields
     */
    private static function intParam(
        array $query,
        string $key,
        int $default,
        int $min,
        int $max,
        string $field,
        array &$fields,
    ): int {
        $value = $query[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (!is_string($value) && !is_int($value)) {
            $fields[$field] = ['必须是整数'];

            return $default;
        }
        if (!preg_match('/^-?\d+$/', (string) $value)) {
            $fields[$field] = ['必须是整数'];

            return $default;
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            $fields[$field] = [sprintf('必须在 %d 到 %d 之间', $min, $max)];
        }

        return $number;
    }

    private static function isTaskState(string $state): bool
    {
        foreach (TaskState::cases() as $case) {
            if ($case->value === $state) {
                return true;
            }
        }

        return false;
    }

    /**
     * result 在契约里是 object；空数组按 {} 输出。
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>|\stdClass
     */
    private static function jsonObject(array $result): array|\stdClass
    {
        return $result === [] ? new \stdClass() : $result;
    }
}
