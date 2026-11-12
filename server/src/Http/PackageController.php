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
use Webman\Http\UploadFile;

/** 插件安装、升级、卸载和系统升级。生产环境在 PackageGate 里直接拒绝。 */
final class PackageController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    public function install(Request $request, string $plugin_code): Response
    {
        return $this->run($request, fn (PackageOps $ops, \Sandadmin\Module\Org\OrgActor $actor, string $requestId): array => $ops->install($plugin_code, $actor, $requestId));
    }

    public function upgrade(Request $request, string $plugin_code): Response
    {
        return $this->run($request, fn (PackageOps $ops, \Sandadmin\Module\Org\OrgActor $actor, string $requestId): array => $ops->upgrade($plugin_code, $actor, $requestId));
    }

    public function uninstall(Request $request, string $plugin_code): Response
    {
        $body = $request->post();
        $purge = is_array($body) && ($body['purge_data'] ?? null) === true;

        return $this->run($request, fn (PackageOps $ops, \Sandadmin\Module\Org\OrgActor $actor, string $requestId): array => $ops->uninstall($plugin_code, $purge, $actor, $requestId));
    }

    public function upgradeAll(Request $request): Response
    {
        return $this->run($request, fn (PackageOps $ops, \Sandadmin\Module\Org\OrgActor $actor, string $requestId): array => $ops->upgradeAll($actor, $requestId));
    }

    public function upload(Request $request): Response
    {
        $upload = $request->file('package');
        if (!$upload instanceof UploadFile || !$upload->isValid()) {
            return $this->failEarly($request, 422, 'PLUGIN_PACKAGE_INVALID', '这个文件不是能用的安装包，请检查后再试');
        }
        $name = $upload->getUploadName();
        $bytes = file_get_contents($upload->getPathname());
        if (!is_string($bytes)) {
            return $this->failEarly($request, 422, 'PLUGIN_PACKAGE_INVALID', '这个文件不是能用的安装包，请检查后再试');
        }
        $fileName = is_string($name) && $name !== '' ? $name : 'package';

        return $this->run($request, fn (PackageOps $ops, \Sandadmin\Module\Org\OrgActor $actor, string $requestId): array => $ops->upload($fileName, $bytes, $actor, $requestId));
    }

    public function show(Request $request, string $plugin_code): Response
    {
        return $this->read($request, fn (PackageOps $ops, string $requestId): array => $ops->showPlugin($plugin_code, $requestId));
    }

    public function startUpdate(Request $request): Response
    {
        return $this->run($request, fn (PackageOps $ops, \Sandadmin\Module\Org\OrgActor $actor, string $requestId): array => $ops->startUpdate($actor, $requestId));
    }

    /**
     * @param callable(PackageOps, \Sandadmin\Module\Org\OrgActor, string): array{status: int, body: array<string, mixed>} $work
     */
    private function run(Request $request, callable $work): Response
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

            return $this->send($work(new PackageOps($kernel->connection), $actor, $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('PackageController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
        }
    }

    /**
     * @param callable(PackageOps, string): array{status: int, body: array<string, mixed>} $work
     */
    private function read(Request $request, callable $work): Response
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

            return $this->send($work(new PackageOps($kernel->connection), $requestId));
        } catch (OrgException $error) {
            return $this->send(ApiOutcome::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId));
        } catch (Throwable $error) {
            error_log('PackageController 异常 ' . $error::class . ': ' . $error->getMessage());

            return $this->send(ApiOutcome::fail(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId));
        }
    }

    private function failEarly(Request $request, int $status, string $code, string $message): Response
    {
        return $this->send(ApiOutcome::fail($status, $code, $message, [], $this->requestId($request)));
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
