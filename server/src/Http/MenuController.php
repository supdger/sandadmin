<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Menu\MenuException;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use support\Request;
use support\Response;
use Throwable;

/** 菜单管理：分组、外部链接的新建、修改和删除。失败按错误状态返回，不返回 200。 */
final class MenuController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function tree(Request $request): Response
    {
        return $this->run($request, 'system:menu:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            unset($actor);
            $query = $this->query($request);
            $keyword = is_string($query['keyword'] ?? null) ? $query['keyword'] : null;
            $source = is_string($query['source'] ?? null) ? $query['source'] : null;

            return ApiOutcome::ok($kernel->menus->loadTree($keyword, $source), $requestId);
        });
    }

    public function storeGroup(Request $request): Response
    {
        return $this->run($request, 'system:menu:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->menus->createGroup($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request), $this->idempotencyKey($request)), $requestId));
    }

    public function storeLink(Request $request): Response
    {
        return $this->run($request, 'system:menu:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->menus->createLink($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request), $this->idempotencyKey($request)), $requestId));
    }

    public function update(Request $request, string $menu_key): Response
    {
        return $this->run($request, 'system:menu:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($menu_key): array {
            return ApiOutcome::ok($kernel->menus->updateNode(rawurldecode($menu_key), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId);
        });
    }

    public function destroy(Request $request, string $menu_key): Response
    {
        return $this->run($request, 'system:menu:delete', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($menu_key): array {
            $kernel->menus->removeNode(rawurldecode($menu_key), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function move(Request $request, string $menu_key): Response
    {
        return $this->run($request, 'system:menu:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($menu_key): array {
            $kernel->menus->moveNode(rawurldecode($menu_key), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function visible(Request $request, string $menu_key): Response
    {
        return $this->run($request, 'system:menu:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($menu_key): array {
            $kernel->menus->setVisible(rawurldecode($menu_key), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function reset(Request $request): Response
    {
        return $this->run($request, 'system:menu:reset', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->menus->resetTree($actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    /**
     * @param callable(BizKernel, OrgActor, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, string $permission, callable $work): Response
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
            if (!$actor->can($permission)) {
                throw OrgException::denied();
            }

            return $this->send($work($kernel, $actor, $request, $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (MenuException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('MenuController 异常 ' . $error::class . ': ' . $error->getMessage());

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

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('idempotency-key');

        return is_string($key) ? $key : null;
    }

    private function device(Request $request): ?string
    {
        $agent = $request->header('user-agent');

        return is_string($agent) ? mb_substr($agent, 0, 64) : null;
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
