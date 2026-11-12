<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Org\OrgException;
use support\Request;
use support\Response;
use Throwable;

/** 当前登录人与侧栏菜单。开发本机登录时返回超级管理员。 */
final class MeController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

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
            if ($kernel->principal->resolve($request) === null) {
                throw OrgException::unauthenticated();
            }

            return ApiOutcome::ok([
                'menu_version' => $kernel->menus->loadTree(null, null)['menu_version'],
                'items' => $kernel->menus->mergeSidebar($this->items($kernel)),
            ], $requestId);
        });
    }

    /** @return list<array<string, mixed>> */
    private function items(BizKernel $kernel): array
    {
        $children = [
            self::page('system:user', '用户管理', 'user', '/system/user'),
            self::page('system:dept', '部门管理', 'dept', '/system/dept'),
            self::page('system:post', '岗位管理', 'post', '/system/post'),
            self::page('system:role', '角色管理', 'role', '/system/role'),
            self::page('system:dict', '选项管理', 'dict', '/data/dict'),
            self::page('system:announcement', '公告管理', 'notice', '/data/notice'),
            self::page('system:menu', '菜单管理', 'menu', '/system/menu'),
            self::page('system:plugin', '插件管理', 'plugin', '/system/plugins'),
            self::page('system:update', '系统更新', 'refresh', '/system/update'),
        ];
        $rows = $kernel->connection->fetchAllAssociative(
            'SELECT code, name FROM sand_system_plugin WHERE state = \'installed\' ORDER BY name',
        );
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            $name = $row['name'] ?? null;
            if (!is_string($code) || !is_string($name) || $code === '') {
                continue;
            }
            $children[] = self::page('plugin:' . $code, $name, 'plugin', '/plugin/' . $code);
        }

        return [
            self::page('home', '工作台', 'home', '/'),
            self::group('system', '系统', 'setting', $children),
        ];
    }

    /** @return array<string, mixed> */
    private static function page(string $key, string $title, string $icon, string $path): array
    {
        return ['key' => $key, 'type' => 'page', 'title' => $title, 'icon' => $icon, 'path' => $path, 'url' => null];
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private static function group(string $key, string $title, string $icon, array $children): array
    {
        return ['key' => $key, 'type' => 'group', 'title' => $title, 'icon' => $icon, 'path' => null, 'url' => null, 'children' => $children];
    }

    /**
     * @param callable(BizKernel, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, callable $work): Response
    {
        $requestId = $this->requestId($request);
        try {
            $kernel = BizKernel::fromWebman();
            if ($kernel === null) {
                return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
            }

            return $this->send($work($kernel, $request, $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('MeController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
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
