<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgInput;
use support\Request;
use support\Response;
use Throwable;

/** 选项组与选项。 */
final class DictController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function index(Request $request): Response
    {
        return $this->run($request, 'system:dict:index', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            unset($actor);
            $keyword = $this->query($request)['keyword'] ?? null;

            return ApiOutcome::ok($kernel->dicts->listTypes(is_string($keyword) ? $keyword : null), $requestId);
        });
    }

    public function show(Request $request, string $type_id): Response
    {
        return $this->run($request, 'system:dict:index', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->dicts->showType(OrgInput::pathId($type_id, 'type_id')), $requestId));
    }

    public function store(Request $request): Response
    {
        return $this->run($request, 'system:dict:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->dicts->createType($this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function update(Request $request, string $type_id): Response
    {
        return $this->run($request, 'system:dict:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->dicts->updateType(OrgInput::pathId($type_id, 'type_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function destroy(Request $request, string $type_id): Response
    {
        return $this->run($request, 'system:dict:delete', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($type_id): array {
            $kernel->dicts->removeType(OrgInput::pathId($type_id, 'type_id'), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function storeItem(Request $request, string $type_id): Response
    {
        return $this->run($request, 'system:dict:create', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->dicts->createItem(OrgInput::pathId($type_id, 'type_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function updateItem(Request $request, string $item_id): Response
    {
        return $this->run($request, 'system:dict:update', fn (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array => ApiOutcome::ok($kernel->dicts->updateItem(OrgInput::pathId($item_id, 'item_id'), $this->body($request), $actor, $requestId, $request->getRemoteIp(), $this->device($request)), $requestId));
    }

    public function destroyItem(Request $request, string $item_id): Response
    {
        return $this->run($request, 'system:dict:delete', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($item_id): array {
            $kernel->dicts->removeItem(OrgInput::pathId($item_id, 'item_id'), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function itemStatus(Request $request, string $item_id): Response
    {
        return $this->run($request, 'system:dict:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId) use ($item_id): array {
            $kernel->dicts->setItemStatus(OrgInput::pathId($item_id, 'item_id'), OrgInput::status($this->body($request)), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
    }

    public function sortItems(Request $request): Response
    {
        return $this->run($request, 'system:dict:update', function (BizKernel $kernel, OrgActor $actor, Request $request, string $requestId): array {
            $body = $this->body($request);
            $typeId = OrgInput::requiredId($body, 'type_id');
            $kernel->dicts->sortItems($typeId, OrgInput::idList($body, 'item_ids', true), $actor, $requestId, $request->getRemoteIp(), $this->device($request));

            return ApiOutcome::ok(new \stdClass(), $requestId);
        });
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
        } catch (DictException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('DictController 异常 ' . $error::class . ': ' . $error->getMessage());

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
