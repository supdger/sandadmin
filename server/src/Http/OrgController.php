<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgInput;
use Sandadmin\Module\Org\OrgSql;
use support\Request;
use support\Response;
use Throwable;

/** 用户、部门、角色、岗位的 /api/admin/v1 入口。 */
final class OrgController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function usersIndex(Request $request): Response
    {
        return $this->run($request, 'system:user:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            unset($actor);

            return ApiOutcome::ok($kernel->users->search($this->query($request)), $requestId);
        });
    }

    public function usersStore(Request $request): Response
    {
        return $this->run($request, 'system:user:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->users->create($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function usersShow(Request $request, string $user_id): Response
    {
        return $this->run($request, 'system:user:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->users->show(OrgInput::pathId($user_id, 'user_id')), $requestId));
    }

    public function usersUpdate(Request $request, string $user_id): Response
    {
        return $this->run($request, 'system:user:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->users->update(OrgInput::pathId($user_id, 'user_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function usersDestroy(Request $request, string $user_id): Response
    {
        return $this->run($request, 'system:user:delete', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($user_id): array {
            $kernel->users->remove(OrgInput::pathId($user_id, 'user_id'), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function usersStatus(Request $request, string $user_id): Response
    {
        return $this->run($request, 'system:user:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($user_id): array {
            $body = $this->body($request);
            $kernel->users->setStatus(OrgInput::pathId($user_id, 'user_id'), OrgInput::status($body), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function usersDisableCheck(Request $request, string $user_id): Response
    {
        return $this->runAny($request, ['system:user:update', 'system:user:delete'], fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->users->disableCheck(OrgInput::pathId($user_id, 'user_id')), $requestId));
    }

    public function usersResetPassword(Request $request, string $user_id): Response
    {
        return $this->run($request, 'system:user:reset-password', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($user_id): array {
            $password = $this->body($request)['password'] ?? null;
            if (!is_string($password)) {
                throw OrgException::invalid(['password' => ['请填写临时密码']]);
            }
            $kernel->users->resetPassword(OrgInput::pathId($user_id, 'user_id'), $password, $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function usersRoles(Request $request, string $user_id): Response
    {
        return $this->run($request, 'system:user:assign-role', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($user_id): array {
            $kernel->users->assignRoles(OrgInput::pathId($user_id, 'user_id'), OrgInput::idList($this->body($request), 'role_ids', true), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function usersExport(Request $request): Response
    {
        return $this->run($request, 'system:user:export', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->users->export($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function userOptions(Request $request): Response
    {
        return $this->runAuth($request, function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            unset($actor);
            $query = $this->query($request);
            $keyword = is_string($query['keyword'] ?? null) ? $query['keyword'] : null;
            $rawDept = $query['dept_id'] ?? null;
            $dept = is_scalar($rawDept) && (string) $rawDept !== '' ? OrgInput::pathId(OrgSql::str($rawDept), 'dept_id') : null;

            return ApiOutcome::ok($kernel->users->options($keyword, $dept), $requestId);
        });
    }

    public function deptOptions(Request $request): Response
    {
        return $this->runAuth($request, fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->depts->options(), $requestId));
    }

    public function deptsTree(Request $request): Response
    {
        return $this->run($request, 'system:dept:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            unset($actor);
            $query = $this->query($request);
            $keyword = is_string($query['keyword'] ?? null) ? $query['keyword'] : null;
            $status = match ($query['status'] ?? null) {
                'enabled' => 1,
                'disabled' => 2,
                null, '' => null,
                default => throw OrgException::invalid(['status' => ['状态不正确']]),
            };

            return ApiOutcome::ok($kernel->depts->tree($keyword, $status), $requestId);
        });
    }

    public function deptsStore(Request $request): Response
    {
        return $this->run($request, 'system:dept:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->depts->create($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function deptsUpdate(Request $request, string $dept_id): Response
    {
        return $this->run($request, 'system:dept:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->depts->update(OrgInput::pathId($dept_id, 'dept_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function deptsDestroy(Request $request, string $dept_id): Response
    {
        return $this->run($request, 'system:dept:delete', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($dept_id): array {
            $kernel->depts->remove(OrgInput::pathId($dept_id, 'dept_id'), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function deptsDeleteCheck(Request $request, string $dept_id): Response
    {
        return $this->run($request, 'system:dept:delete', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->depts->deleteCheck(OrgInput::pathId($dept_id, 'dept_id')), $requestId));
    }

    public function rolesIndex(Request $request): Response
    {
        return $this->run($request, 'system:role:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->search($this->query($request)), $requestId));
    }

    public function rolesStore(Request $request): Response
    {
        return $this->run($request, 'system:role:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->create($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function rolesShow(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->show(OrgInput::pathId($role_id, 'role_id')), $requestId));
    }

    public function rolesUpdate(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->update(OrgInput::pathId($role_id, 'role_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function rolesDestroy(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:delete', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->remove(OrgInput::pathId($role_id, 'role_id'), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function rolesCopy(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->copy(OrgInput::pathId($role_id, 'role_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function rolesStatus(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($role_id): array {
            $kernel->roles->setStatus(OrgInput::pathId($role_id, 'role_id'), OrgInput::status($this->body($request)), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function rolesPermissions(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->permissions(OrgInput::pathId($role_id, 'role_id')), $requestId));
    }

    public function rolesSavePermissions(Request $request, string $role_id): Response
    {
        return $this->run($request, 'system:role:permission:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->savePermissions(OrgInput::pathId($role_id, 'role_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function permissionTree(Request $request): Response
    {
        return $this->run($request, 'system:role:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->roles->permissionTree(), $requestId));
    }

    public function postsIndex(Request $request): Response
    {
        return $this->run($request, 'system:post:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->posts->search($this->query($request)), $requestId));
    }

    public function postsStore(Request $request): Response
    {
        return $this->run($request, 'system:post:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->posts->create($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function postsUpdate(Request $request, string $post_id): Response
    {
        return $this->run($request, 'system:post:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->posts->update(OrgInput::pathId($post_id, 'post_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function postsDestroy(Request $request, string $post_id): Response
    {
        return $this->run($request, 'system:post:delete', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($post_id): array {
            $kernel->posts->remove(OrgInput::pathId($post_id, 'post_id'), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    /**
     * @param callable(BizKernel, OrgActor, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, string $permission, callable $work): Response
    {
        return $this->execute($request, function (BizKernel $kernel, Request $request, string $requestId) use ($permission, $work): array {
            $actor = $this->permit($kernel, $request, $permission);

            return $work($kernel, $actor, $request, $requestId);
        });
    }

    /**
     * @param list<string> $permissions
     * @param callable(BizKernel, OrgActor, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function runAny(Request $request, array $permissions, callable $work): Response
    {
        return $this->execute($request, function (BizKernel $kernel, Request $request, string $requestId) use ($permissions, $work): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }
            if (!$actor->canAny($permissions)) {
                throw OrgException::denied();
            }

            return $work($kernel, $actor, $request, $requestId);
        });
    }

    /**
     * @param callable(BizKernel, OrgActor, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function runAuth(Request $request, callable $work): Response
    {
        return $this->execute($request, function (BizKernel $kernel, Request $request, string $requestId) use ($work): array {
            $actor = $kernel->principal->resolve($request);
            if ($actor === null) {
                throw OrgException::unauthenticated();
            }

            return $work($kernel, $actor, $request, $requestId);
        });
    }

    /**
     * @param callable(BizKernel, Request, string): array{status: int, body: array<string, mixed>} $work
     */
    private function execute(Request $request, callable $work): Response
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
        } catch (DictException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('OrgController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
        }
    }

    private function permit(BizKernel $kernel, Request $request, string $permission): OrgActor
    {
        $actor = $kernel->principal->resolve($request);
        if ($actor === null) {
            throw OrgException::unauthenticated();
        }
        if (!$actor->can($permission)) {
            throw OrgException::denied();
        }

        return $actor;
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
