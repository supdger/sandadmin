<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Http;

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
 * request_id 暂由控制器生成，B2 统一请求上下文合入后改从上下文取。
 */
final class TaskController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function index(Request $request): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = TaskRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', $requestId);
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
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', $requestId);
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
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', $requestId);
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

    private function actorProvider(): TaskActorProvider
    {
        // B2 的 RequestContext/权限清单合入后，这里改为装配真实实现；
        // 默认实现一律返回 null（未登录），接口保持拒绝。
        return new UnavailableTaskActorProvider();
    }

    private function artifacts(): TaskArtifactResolver
    {
        // B6 文件存储合入后改为真实解析器；默认实现恒返回 null。
        return new UnavailableArtifactResolver();
    }

    /**
     * X-Request-Id：沿用合法请求头，否则生成；与错误/成功信封内
     * request_id 保持一致，B2 合入后由统一中间件接管。
     */
    private function requestId(Request $request): string
    {
        $incoming = $request->header('x-request-id');
        if (is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return 'req_' . bin2hex(random_bytes(8));
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
            // 解析后文件被清理：按契约返回不可下载错误
            $failure = TaskHttp::failure(
                404,
                'TASK_RUN_NOT_DOWNLOADABLE',
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

        return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', $requestId);
    }
}
