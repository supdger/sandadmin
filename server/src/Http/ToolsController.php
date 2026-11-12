<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Module\AuditLog\AuditLogService;
use Sandadmin\Module\LoginLog\LoginLogService;
use Sandadmin\Module\Monitor\MonitorService;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Search\SearchService;
use Sandadmin\Module\Setting\SettingService;
use support\Request;
use support\Response;
use Throwable;

/**
 * 监控和工具批次（M1/M2/J10/J11 + 系统设置 + 全局搜索）的 HTTP 端点。
 * 权限码在方法内按契约 x-permission 判定；响应走统一信封。
 */
final class ToolsController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function monitorServer(Request $request): Response
    {
        return $this->run($request, 'system:monitor:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok((new MonitorService($kernel->connection))->server(), $requestId);
        });
    }

    public function monitorSeries(Request $request): Response
    {
        return $this->run($request, 'system:monitor:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            $query = $this->query($request);
            $metric = is_string($query['metric'] ?? null) ? $query['metric'] : '';
            $range = is_string($query['range'] ?? null) ? $query['range'] : '1h';
            if ($metric === '') {
                throw OrgException::invalid(['metric' => ['请选要看的指标']]);
            }

            return ApiOutcome::ok((new MonitorService($kernel->connection))->series($metric, $range), $requestId);
        });
    }

    public function loginLogs(Request $request): Response
    {
        return $this->run($request, 'system:login-log:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok($this->loginLogService($kernel)->page($this->query($request)), $requestId);
        });
    }

    public function loginLogShow(Request $request, string $log_id): Response
    {
        return $this->run($request, 'system:login-log:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($log_id): array {
            return ApiOutcome::ok($this->loginLogService($kernel)->detail($this->pathId($log_id)), $requestId);
        });
    }

    public function loginLogExport(Request $request): Response
    {
        return $this->run($request, 'system:login-log:export', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok(
                $this->loginLogService($kernel)->export($this->body($request), $actor, $requestId, $request->getRemoteIp(), RequestDevice::label($request)),
                $requestId,
            );
        });
    }

    public function auditLogs(Request $request): Response
    {
        return $this->run($request, 'system:audit-log:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok($this->auditLogService($kernel)->page($this->query($request)), $requestId);
        });
    }

    public function auditLogModuleOptions(Request $request): Response
    {
        return $this->run($request, 'system:audit-log:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok($this->auditLogService($kernel)->moduleOptions(), $requestId);
        });
    }

    public function auditLogShow(Request $request, string $log_id): Response
    {
        return $this->run($request, 'system:audit-log:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($log_id): array {
            return ApiOutcome::ok($this->auditLogService($kernel)->detail($this->pathId($log_id)), $requestId);
        });
    }

    public function auditLogExport(Request $request): Response
    {
        return $this->run($request, 'system:audit-log:export', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok(
                $this->auditLogService($kernel)->export($this->body($request), $actor, $requestId, $request->getRemoteIp(), RequestDevice::label($request)),
                $requestId,
            );
        });
    }

    public function scheduledTasks(Request $request): Response
    {
        return $this->run($request, 'system:cron:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return (new ScheduleHttp($kernel->connection))->index($this->query($request), $requestId);
        });
    }

    public function scheduledTaskUpdate(Request $request, string $code): Response
    {
        return $this->run($request, 'system:cron:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($code): array {
            return (new ScheduleHttp($kernel->connection))->update($code, $this->body($request), $requestId, $actor, $request->getRemoteIp(), RequestDevice::label($request));
        });
    }

    public function scheduledTaskRun(Request $request, string $code): Response
    {
        return $this->run($request, 'system:cron:run', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($code): array {
            return (new ScheduleHttp($kernel->connection))->run($code, $requestId, $actor, $request->getRemoteIp(), RequestDevice::label($request));
        });
    }

    public function scheduledTaskRuns(Request $request, string $code): Response
    {
        return $this->run($request, 'system:cron:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($code): array {
            return (new ScheduleHttp($kernel->connection))->runs($code, $this->query($request), $requestId);
        });
    }

    public function settingGroups(Request $request): Response
    {
        return $this->run($request, 'system:config:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            return ApiOutcome::ok(['items' => $this->settingService($kernel)->groups()], $requestId);
        });
    }

    public function settingShow(Request $request, string $group): Response
    {
        return $this->run($request, 'system:config:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($group): array {
            return ApiOutcome::ok($this->settingService($kernel)->read($group), $requestId);
        });
    }

    public function settingUpdate(Request $request, string $group): Response
    {
        return $this->run($request, 'system:config:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($group): array {
            $values = $this->body($request)['values'] ?? null;
            if (!is_array($values)) {
                throw OrgException::invalid(['values' => ['请提交要保存的设置']]);
            }

            return ApiOutcome::ok(
                $this->settingService($kernel)->update($group, $this->stringKeys($values), $actor, $requestId, $request->getRemoteIp(), RequestDevice::label($request)),
                $requestId,
            );
        });
    }

    public function settingLogo(Request $request): Response
    {
        return $this->run($request, 'system:config:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            $file = $request->file('file');
            if (!$file instanceof \Webman\Http\UploadFile) {
                throw OrgException::invalid(['file' => ['请选一张图片']], '请选一张图片');
            }
            $upload = [
                'name' => $file->getUploadName(),
                'type' => $file->getUploadMimeType(),
                'tmp_name' => $file->getPathname(),
                'size' => $file->getSize(),
                'error' => $file->getUploadErrorCode(),
            ];

            return ApiOutcome::ok(
                $this->settingService($kernel)->uploadLogo($upload, $actor, $requestId, $request->getRemoteIp(), RequestDevice::label($request)),
                $requestId,
            );
        });
    }

    public function search(Request $request): Response
    {
        return $this->run($request, null, function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            $query = $this->query($request);
            $keyword = is_string($query['q'] ?? null) ? trim($query['q']) : '';
            if ($keyword === '' || mb_strlen($keyword) > 100) {
                throw OrgException::invalid(['q' => ['请输入要搜的内容']]);
            }
            $types = is_string($query['types'] ?? null) ? $query['types'] : null;

            return ApiOutcome::ok((new SearchService($kernel->connection))->search($actor, $keyword, $types), $requestId);
        });
    }

    private function loginLogService(BizKernel $kernel): LoginLogService
    {
        return new LoginLogService($kernel->connection, new \Sandadmin\Module\Org\OrgAudit($kernel->connection));
    }

    private function auditLogService(BizKernel $kernel): AuditLogService
    {
        return new AuditLogService($kernel->connection, new \Sandadmin\Module\Org\OrgAudit($kernel->connection));
    }

    private function settingService(BizKernel $kernel): SettingService
    {
        return new SettingService($kernel->connection, new \Sandadmin\Module\Org\OrgAudit($kernel->connection));
    }

    private function pathId(string $raw): int
    {
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw <= 0) {
            throw OrgException::notFound();
        }

        return (int) $raw;
    }

    /**
     * @param callable(BizKernel, OrgActor, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, ?string $permission, callable $work): Response
    {
        $requestId = $this->requestId($request);
        try {
            $kernel = BizKernel::fromWebman();
            if ($kernel === null) {
                return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
            }
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }
            if ($permission !== null && !$actor->can($permission)) {
                throw OrgException::denied();
            }

            return $this->send($work($kernel, $actor, $request, $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (InvalidExtensionException) {
            return $this->send(ApiOutcome::fail(422, 'TASK_SCHEDULE_INVALID', '执行时间填写不正确', ['schedule' => ['执行时间填写不正确']], $requestId));
        } catch (Throwable $error) {
            error_log('ToolsController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
        }
    }

    /** @return array<string, mixed> */
    private function query(Request $request): array
    {
        $query = $request->get();

        return is_array($query) ? $this->stringKeys($query) : [];
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $body = $request->post();

        return is_array($body) ? $this->stringKeys($body) : [];
    }

    /**
     * @param array<mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function stringKeys(array $raw): array
    {
        $result = [];
        foreach ($raw as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->header('x-request-id');
        if (is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return 'req_' . bin2hex(random_bytes(8));
    }

    /** @param array{status: int, body: array<string, mixed>} $outcome */
    private function send(array $outcome): Response
    {
        $requestId = $outcome['body']['request_id'] ?? '';

        return new Response(
            $outcome['status'],
            ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => is_string($requestId) ? $requestId : ''],
            (string) json_encode($outcome['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
