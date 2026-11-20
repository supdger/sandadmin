<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Org\OrgException;
use support\Request;
use support\Response;
use Throwable;

/** 当前登录人与侧栏菜单。开发本机登录时返回超级管理员。 */
final class MeController
{
    private readonly RequestContextStore $contexts;

    public function __construct(?RequestContextStore $contexts = null)
    {
        $this->contexts = $contexts ?? new RequestContextStore();
    }

    public function me(Request $request): Response
    {
        return $this->run($request, function (BizKernel $kernel, Request $request, string $requestId): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }

            return ApiOutcome::ok($kernel->users->show($actor->userId), $requestId);
        });
    }

    public function profile(Request $request): Response
    {
        return $this->run($request, function (BizKernel $kernel, Request $request, string $requestId): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }

            return ApiOutcome::ok($kernel->users->profile($actor->userId), $requestId);
        });
    }

    public function passwordPolicy(Request $request): Response
    {
        return $this->run($request, function (BizKernel $kernel, Request $request, string $requestId): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }

            return ApiOutcome::ok($kernel->users->passwordPolicy($actor->userId), $requestId);
        });
    }

    public function changePassword(Request $request): Response
    {
        return $this->run($request, function (BizKernel $kernel, Request $request, string $requestId): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }
            $body = $this->body($request);
            $oldPassword = is_string($body['old_password'] ?? null) ? $body['old_password'] : '';
            $newPassword = is_string($body['new_password'] ?? null) ? $body['new_password'] : '';

            return ApiOutcome::ok(
                $kernel->users->changePassword($actor->userId, $oldPassword, $newPassword, $request->getRemoteIp(), $this->device($request)),
                $requestId,
            );
        });
    }

    public function updateProfile(Request $request): Response
    {
        return $this->run($request, function (BizKernel $kernel, Request $request, string $requestId): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }

            return ApiOutcome::ok(
                $kernel->users->updateProfile($actor->userId, $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)),
                $requestId,
            );
        });
    }

    public function menus(Request $request): Response
    {
        return $this->run($request, function (BizKernel $kernel, Request $request, string $requestId): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }

            return ApiOutcome::ok([
                'menu_version' => $kernel->menus->loadTree(null, null)['menu_version'],
                'items' => $kernel->menus->mergeSidebar($kernel->menus->sidebarItems($actor)),
            ], $requestId);
        });
    }

    /**
     * @param callable(BizKernel, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, callable $work): Response
    {
        $requestId = $this->contexts->ensure($request)->requestId;
        try {
            $kernel = BizKernel::fromWebman();
            if ($kernel === null) {
                return $this->send(ApiOutcome::fail(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', [], $requestId));
            }

            return $this->send($work($kernel, $request, $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('MeController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试', [], $requestId));
        }
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $posted = $request->post();
        $fromPost = is_array($posted) ? $this->stringKeys($posted) : [];
        if ($fromPost !== []) {
            return $fromPost;
        }
        $raw = $request->rawBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $this->stringKeys($decoded) : [];
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

    private function device(Request $request): ?string
    {
        $agent = $request->header('user-agent');

        return is_string($agent) ? mb_substr($agent, 0, 64) : null;
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
