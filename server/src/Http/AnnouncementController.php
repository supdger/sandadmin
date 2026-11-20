<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Announcement\AnnouncementException;
use Sandadmin\Module\Announcement\AnnouncementHttp;
use Sandadmin\Module\Announcement\AnnouncementRuntime;
use support\Request;
use support\Response;
use Throwable;

/**
 * /api/admin/v1/announcements 与 /me/announcements/popup。
 * 鉴权由路由默认中间件收口（B2）；权限码在 AnnouncementHttp 内判定。
 * request_id 与全站统一走 RequestContextStore::ensure，不再单独生成。
 */
final class AnnouncementController
{
    private readonly RequestContextStore $contexts;

    public function __construct(?RequestContextStore $contexts = null)
    {
        $this->contexts = $contexts ?? new RequestContextStore();
    }

    public function index(Request $request): Response
    {
        return $this->handle($request, static function (AnnouncementRuntime $runtime, Request $request, string $requestId): array {
            $query = $request->get();

            return AnnouncementHttp::index(
                $runtime->service,
                $runtime->actors->actor($request),
                is_array($query) ? $query : [],
                $requestId,
            );
        });
    }

    public function show(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, static fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::show($runtime->service, $runtime->actors->actor($request), $announcement_id, $requestId));
    }

    public function store(Request $request): Response
    {
        return $this->handle($request, fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::create(
            $runtime->service,
            $runtime->actors->actor($request),
            $this->body($request),
            $this->idempotencyKey($request),
            $requestId,
            $request->getRemoteIp(),
            $this->deviceLabel($request),
        ));
    }

    public function update(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::update(
            $runtime->service,
            $runtime->actors->actor($request),
            $announcement_id,
            $this->body($request),
            $this->idempotencyKey($request),
            $requestId,
            $request->getRemoteIp(),
            $this->deviceLabel($request),
        ));
    }

    public function destroy(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::destroy(
            $runtime->service,
            $runtime->actors->actor($request),
            $announcement_id,
            $this->idempotencyKey($request),
            $requestId,
            $request->getRemoteIp(),
            $this->deviceLabel($request),
        ));
    }

    public function publish(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::publish(
            $runtime->service,
            $runtime->actors->actor($request),
            $announcement_id,
            $this->idempotencyKey($request),
            $requestId,
            $request->getRemoteIp(),
            $this->deviceLabel($request),
        ));
    }

    public function revoke(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::revoke(
            $runtime->service,
            $runtime->actors->actor($request),
            $announcement_id,
            $this->idempotencyKey($request),
            $requestId,
            $request->getRemoteIp(),
            $this->deviceLabel($request),
        ));
    }

    public function markRead(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::markRead(
            $runtime->service,
            $runtime->actors->actor($request),
            $announcement_id,
            $this->idempotencyKey($request),
            $requestId,
        ));
    }

    public function readStats(Request $request, string $announcement_id): Response
    {
        return $this->handle($request, static fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::readStats($runtime->service, $runtime->actors->actor($request), $announcement_id, $requestId));
    }

    public function popup(Request $request): Response
    {
        return $this->handle($request, static fn (AnnouncementRuntime $runtime, Request $request, string $requestId): array => AnnouncementHttp::popup($runtime->service, $runtime->actors->actor($request), $requestId));
    }

    /**
     * @param callable(AnnouncementRuntime, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function handle(Request $request, callable $work): Response
    {
        $requestId = $this->contexts->ensure($request)->requestId;
        try {
            $runtime = AnnouncementRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', [], $requestId);
            }
            $outcome = $work($runtime, $request, $requestId);

            return $this->respond($outcome['status'], $outcome['body'], $requestId);
        } catch (AnnouncementException $error) {
            return $this->failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        } catch (Throwable $error) {
            error_log('AnnouncementController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->failure(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', [], $requestId);
        }
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $body = $request->post();
        if (!is_array($body)) {
            return [];
        }
        $result = [];
        foreach ($body as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('idempotency-key');

        return is_string($key) ? $key : null;
    }

    private function deviceLabel(Request $request): ?string
    {
        $agent = $request->header('user-agent');

        return is_string($agent) ? mb_substr($agent, 0, 64) : null;
    }

    /** @param array<string, mixed> $body */
    private function respond(int $status, array $body, string $requestId): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $requestId],
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /** @param array<string, list<string>> $fields */
    private function failure(int $status, string $code, string $message, array $fields, string $requestId): Response
    {
        $outcome = AnnouncementHttp::failure($status, $code, $message, $fields, $requestId);

        return $this->respond($outcome['status'], $outcome['body'], $requestId);
    }
}
