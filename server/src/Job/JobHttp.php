<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class JobHttp
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function show(JobStore $store, string $id, string $requestId): array
    {
        if (!JobId::isValid($id)) {
            return self::error(400, 'invalid_job_id', '任务标识不正确', $requestId);
        }
        try {
            $job = $store->find($id);
        } catch (MissingJobSchemaException) {
            return self::error(503, 'job_store_unavailable', '任务存储尚未就绪', $requestId);
        } catch (\Throwable) {
            return self::error(500, 'job_store_error', '任务状态暂时无法读取', $requestId);
        }
        if ($job === null) {
            return self::error(404, 'job_not_found', '任务不存在', $requestId);
        }

        return [
            'status' => 200,
            'body' => self::resource($job),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(JobRun $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'stage' => $job->stage,
            'progress' => $job->progress,
            'result' => $job->result,
            'error' => $job->error,
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function error(int $status, string $code, string $message, string $requestId): array
    {
        return [
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => new \stdClass(),
                'request_id' => $requestId,
            ],
        ];
    }
}
