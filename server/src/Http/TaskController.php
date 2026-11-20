<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Http;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Infra\Db\DbalConnection;
// SAND-DEV-BEGIN
use Sandadmin\Task\DevTaskActorProvider;
// SAND-DEV-END
use Sandadmin\Task\StoredExportResolver;
use Sandadmin\Task\TaskActor;
use Sandadmin\Task\TaskActorProvider;
use Sandadmin\Task\TaskArtifact;
use Sandadmin\Task\TaskArtifactResolver;
use Sandadmin\Task\TaskHttp;
use Sandadmin\Task\TaskRuntime;
use Sandadmin\Task\UnavailableTaskActorProvider;
use Sandadmin\Task\UnavailableArtifactResolver;
use support\Request;
use support\Response;
use Throwable;

/**
 * /api/admin/v1/tasks 轮询接口。鉴权由路由默认中间件收口；
 * 任务可见性（发起人本人或 system:task:index 等效）在 TaskHttp 判定。
 * request_id 与全站统一走 RequestContextStore::ensure（x-request-id 透传
 * 或生成），不再单独生成。
 */
final class TaskController
{
    private readonly RequestContextStore $contexts;

    public function __construct(?RequestContextStore $contexts = null)
    {
        $this->contexts = $contexts ?? new RequestContextStore();
    }

    public function index(Request $request): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = TaskRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', $requestId);
            }
            $query = $request->get();
            $outcome = TaskHttp::index(
                $runtime->store,
                $this->actor($request),
                is_array($query) ? $query : [],
                $requestId,
            );

            return $this->respond($outcome['status'], $outcome['body'], $requestId);
        } catch (Throwable $exception) {
            return $this->unexpected($exception, $requestId);
        }
    }

    public function show(Request $request, string $task_id): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = TaskRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', $requestId);
            }
            $outcome = TaskHttp::show($runtime->store, $this->actor($request), $task_id, $requestId);

            return $this->respond($outcome['status'], $outcome['body'], $requestId);
        } catch (Throwable $exception) {
            return $this->unexpected($exception, $requestId);
        }
    }

    public function download(Request $request, string $task_id): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = TaskRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', $requestId);
            }
            $outcome = TaskHttp::download(
                $runtime->store,
                $this->actor($request),
                $task_id,
                $this->artifacts(),
                $requestId,
            );
            $artifact = $outcome['artifact'] ?? null;
            if ($artifact instanceof TaskArtifact) {
                return $this->respondArtifact($artifact, $requestId);
            }
            $body = $outcome['body'] ?? [];

            return $this->respond($outcome['status'], $body, $requestId);
        } catch (Throwable $exception) {
            return $this->unexpected($exception, $requestId);
        }
    }

    private function actor(Request $request): ?TaskActor
    {
        return $this->actorProvider()->actor($request);
    }

    // 哨兵块里的身份提供方只存在于开发构建，发布打包时整段剔除。
    private function actorProvider(): TaskActorProvider
    {
        // SAND-DEV-BEGIN
        $connection = $this->connection();
        if ($connection !== null && class_exists(DevTaskActorProvider::class)) {
            return new DevTaskActorProvider($connection);
        }
        // SAND-DEV-END

        return new UnavailableTaskActorProvider();
    }

    private function artifacts(): TaskArtifactResolver
    {
        $connection = $this->connection();
        if ($connection === null) {
            return new UnavailableArtifactResolver();
        }

        return new StoredExportResolver($connection);
    }

    private function connection(): ?\Doctrine\DBAL\Connection
    {
        if (!function_exists('config')) {
            return null;
        }
        $database = config('database.connections.pgsql');
        if (!is_array($database)) {
            return null;
        }
        try {
            return DbalConnection::connection($database);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 统一 request_id 入口：已有请求上下文直接复用，否则按
     * x-request-id 头解析/生成并绑定日志作用域。
     */
    private function requestId(Request $request): string
    {
        return $this->contexts->ensure($request)->requestId;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function respond(int $status, array $body, string $requestId): Response
    {
        return new Response($status, ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $requestId], (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function respondArtifact(TaskArtifact $artifact, string $requestId): Response
    {
        if (!is_file($artifact->path) || !is_readable($artifact->path)) {
            // 解析后文件被清理：按契约返回不可下载错误，任务存在故用 409
            $failure = TaskHttp::failure(
                409,
                ErrorCodeRegistry::TASK_RUN_NOT_DOWNLOADABLE,
                '结果文件已过期或不存在',
                [],
                $requestId,
            );

            return $this->respond($failure['status'], $failure['body'], $requestId);
        }
        $mediaType = preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*$~i', $artifact->mediaType) === 1
            ? $artifact->mediaType
            : 'application/octet-stream';
        $response = new Response(200, [
            'Content-Type' => $mediaType,
            'X-Request-Id' => $requestId,
        ], '');

        return $response->download($artifact->path, $artifact->name);
    }

    private function failure(int $status, string $code, string $message, string $requestId): Response
    {
        return $this->respond(
            $status,
            TaskHttp::failure($status, $code, $message, [], $requestId)['body'],
            $requestId,
        );
    }

    private function unexpected(Throwable $exception, string $requestId): Response
    {
        error_log('TaskController 异常 ' . $exception::class . ': ' . $exception->getMessage());

        return $this->failure(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', $requestId);
    }
}
