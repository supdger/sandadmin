<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Package\PackageOps;
use support\Request;
use support\Response;
use Throwable;

/** 插件概览与系统更新状态。安装入口在 PackageController，定时任务在 ToolsController。 */
final class HostReadController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function pluginsOverview(Request $request): Response
    {
        return $this->run($request, fn (string $requestId): array => $this->packages()->overview($requestId));
    }

    public function pluginsMarket(Request $request): Response
    {
        return $this->run($request, fn (string $requestId): array => $this->packages()->market($requestId));
    }

    public function pluginsInstalled(Request $request): Response
    {
        return $this->run($request, fn (string $requestId): array => $this->packages()->installed($requestId));
    }

    public function updateStatus(Request $request): Response
    {
        return $this->run($request, fn (string $requestId): array => $this->packages()->updateStatus($requestId));
    }

    public function updateCheck(Request $request): Response
    {
        return $this->run($request, fn (string $requestId): array => $this->packages()->updateStatus($requestId));
    }

    private function packages(): PackageOps
    {
        $kernel = BizKernel::fromWebman();
        if ($kernel === null) {
            throw OrgException::invalid([], '系统出了点问题，请稍后再试');
        }

        return new PackageOps($kernel->connection);
    }

    /**
     * @param callable(string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, callable $work): Response
    {
        $requestId = $this->requestId($request);
        try {
            $kernel = BizKernel::fromWebman();
            if ($kernel === null) {
                return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
            }
            if ($kernel->principal->resolve($request) === null) {
                throw OrgException::unauthenticated();
            }

            return $this->send($work($requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('HostReadController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
        }
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
