<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Notification\NotificationException;
use Sandadmin\Module\Notification\NotificationHttp;
use Sandadmin\Module\Notification\NotificationRuntime;
use support\Request;
use support\Response;
use Throwable;

/** /api/admin/v1/notifications。鉴权由默认中间件收口，这里只组装消息列表。 */
final class NotificationController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function index(Request $request): Response
    {
        return $this->handle($request, static function (NotificationRuntime $runtime, Request $request, string $requestId): array {
            $query = $request->get();

            return NotificationHttp::index(
                $runtime->inbox,
                $runtime->actors->actor($request),
                is_array($query) ? $query : [],
                $requestId,
            );
        });
    }

    public function unreadCount(Request $request): Response
    {
        return $this->handle($request, static fn (NotificationRuntime $runtime, Request $request, string $requestId): array => NotificationHttp::unreadCount(
            $runtime->inbox,
            $runtime->actors->actor($request),
            $requestId,
        ));
    }

    public function markRead(Request $request, string $notification_id): Response
    {
        return $this->handle($request, fn (NotificationRuntime $runtime, Request $request, string $requestId): array => NotificationHttp::markRead(
            $runtime->inbox,
            $runtime->actors->actor($request),
            $notification_id,
            $this->idempotencyKey($request),
            $requestId,
        ));
    }

    public function markAllRead(Request $request): Response
    {
        return $this->handle($request, fn (NotificationRuntime $runtime, Request $request, string $requestId): array => NotificationHttp::markAllRead(
            $runtime->inbox,
            $runtime->actors->actor($request),
            $this->idempotencyKey($request),
            $requestId,
        ));
    }

    /**
     * @param callable(NotificationRuntime, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function handle(Request $request, callable $work): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = NotificationRuntime::fromWebman();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }
            $outcome = $work($runtime, $request, $requestId);

            return $this->respond($outcome['status'], $outcome['body'], $requestId);
        } catch (NotificationException $error) {
            return $this->failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        } catch (Throwable $error) {
            error_log('NotificationController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
        }
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('idempotency-key');

        return is_string($key) ? $key : null;
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->header('x-request-id');
        if (is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return 'req_' . bin2hex(random_bytes(8));
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
        $outcome = NotificationHttp::failure($status, $code, $message, $fields, $requestId);

        return $this->respond($outcome['status'], $outcome['body'], $requestId);
    }
}
