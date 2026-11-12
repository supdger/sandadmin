<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Org\OrgException;
use support\Request;
use support\Response;
use Throwable;

/** 工作台只读接口。列表一律是数组，没有数据时给空列表。 */
final class DashboardController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function onboarding(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::onboarding());
    }

    public function summary(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::summary());
    }

    public function todos(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::todos());
    }

    public function allTodos(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::allTodos($this->page($request), $this->pageSize($request)));
    }

    public function charts(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::charts());
    }

    public function activities(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::activities());
    }

    public function notices(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::notices());
    }

    public function shortcuts(Request $request): Response
    {
        return $this->sendRead($request, DashboardRead::shortcuts());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendRead(Request $request, array $data): Response
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

            return $this->send(ApiOutcome::ok($data, $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('DashboardController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
        }
    }

    private function page(Request $request): int
    {
        $raw = $request->get('page');

        return is_numeric($raw) ? max(1, (int) $raw) : 1;
    }

    private function pageSize(Request $request): int
    {
        $raw = $request->get('page_size');
        $size = is_numeric($raw) ? (int) $raw : 20;

        return min(100, max(1, $size));
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
