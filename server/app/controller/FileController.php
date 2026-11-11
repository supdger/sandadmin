<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\controller;

use Sandadmin\Application\File\FileHttp;
use Sandadmin\Application\File\FileRuntime;
use Sandadmin\Application\File\StorageConfig;
use Sandadmin\Domain\File\FileException;
use support\Request;
use support\Response;
use Webman\Http\UploadFile;

/**
 * /api/admin/v1/files 契约端点。鉴权由路由默认中间件收口（B2 合入后）；
 * 身份解析走 FileActorProvider 接缝，底座未合入前默认拒绝（401）。
 * request_id 暂由控制器生成，B2 统一请求上下文合入后改从上下文取。
 */
final class FileController
{
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9_-]{8,128}$/';

    public function store(Request $request): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = $this->runtime();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }
            $upload = $request->file('file');
            if (is_array($upload)) {
                return $this->failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['file' => ['一次只能上传一个文件']], $requestId);
            }
            if (!$upload instanceof UploadFile) {
                return $this->failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['file' => ['请使用 file 字段上传文件']], $requestId);
            }
            if (!$upload->isValid()) {
                return $this->failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['file' => ['上传未完成']], $requestId);
            }
            $stream = fopen($upload->getPathname(), 'rb');
            if ($stream === false) {
                return $this->failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['file' => ['无法读取上传文件']], $requestId);
            }
            try {
                $size = $upload->getSize();
                $name = $upload->getUploadName();
                $outcome = FileHttp::upload(
                    $runtime->uploads,
                    $runtime->actors->actor($request),
                    $stream,
                    is_int($size) ? $size : null,
                    is_string($name) && $name !== '' ? $name : 'file',
                    $request->post('visibility'),
                    $request->post('usage'),
                    $this->idempotencyKey($request),
                    $requestId,
                );

                return $this->respond($outcome, $requestId);
            } finally {
                fclose($stream);
            }
        } catch (FileException $error) {
            return $this->failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        } catch (\Throwable $error) {
            return $this->unexpected($error, $requestId);
        }
    }

    public function index(Request $request): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = $this->runtime();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }
            $query = $request->get();

            return $this->respondJson(
                FileHttp::index($runtime->files, $runtime->actors->actor($request), is_array($query) ? $query : [], $requestId),
                $requestId,
            );
        } catch (\Throwable $error) {
            return $this->unexpected($error, $requestId);
        }
    }

    public function show(Request $request, string $file_id): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = $this->runtime();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }

            return $this->respondJson(
                FileHttp::show($runtime->files, $runtime->actors->actor($request), $file_id, $requestId),
                $requestId,
            );
        } catch (\Throwable $error) {
            return $this->unexpected($error, $requestId);
        }
    }

    public function destroy(Request $request, string $file_id): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = $this->runtime();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }

            return $this->respondJson(
                FileHttp::destroy($runtime->files, $runtime->actors->actor($request), $file_id, $this->idempotencyKey($request), $requestId),
                $requestId,
            );
        } catch (\Throwable $error) {
            return $this->unexpected($error, $requestId);
        }
    }

    public function download(Request $request, string $file_id): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = $this->runtime();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }
            $outcome = FileHttp::download(
                $runtime->files,
                $runtime->usageAccess,
                $runtime->actors->actor($request),
                $file_id,
                $requestId,
            );
            $file = $outcome['file'] ?? null;
            if (is_array($file)) {
                return $this->respondFile($file, $requestId);
            }

            return $this->respondJson($outcome, $requestId);
        } catch (\Throwable $error) {
            return $this->unexpected($error, $requestId);
        }
    }

    public function cleanup(Request $request): Response
    {
        $requestId = $this->requestId($request);
        try {
            $runtime = $this->runtime();
            if ($runtime === null) {
                return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
            }
            $key = $this->idempotencyKey($request);

            return $this->respondJson(
                FileHttp::cleanup(
                    $runtime->files,
                    $runtime->actors->actor($request),
                    $key,
                    $requestId,
                ),
                $requestId,
            );
        } catch (\Throwable $error) {
            return $this->unexpected($error, $requestId);
        }
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('idempotency-key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function runtime(): ?FileRuntime
    {
        try {
            return FileRuntime::fromWebman();
        } catch (\Throwable $error) {
            error_log('file 运行时初始化失败 ' . $error::class . ': ' . $error->getMessage());

            return null;
        }
    }

    /**
     * X-Request-Id：沿用合法请求头，否则生成；与错误/成功信封内
     * request_id 保持一致，B2 合入后由统一中间件接管。
     */
    private function requestId(Request $request): string
    {
        $incoming = $request->header('x-request-id');
        if (is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return bin2hex(random_bytes(16));
    }

    /**
     * @param array{status: int, body?: array<string, mixed>, file?: array{path: string, name: string, media_type: string}} $outcome
     */
    private function respondJson(array $outcome, string $requestId): Response
    {
        $body = $outcome['body'] ?? null;
        if (!is_array($body)) {
            return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
        }

        return new Response(
            $outcome['status'],
            ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $requestId],
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array{status: int, body?: array<string, mixed>, file?: array{path: string, name: string, media_type: string}} $outcome
     */
    private function respond(array $outcome, string $requestId): Response
    {
        $file = $outcome['file'] ?? null;
        if (is_array($file)) {
            return $this->respondFile($file, $requestId);
        }

        return $this->respondJson($outcome, $requestId);
    }

    /**
     * @param array{path: string, name: string, media_type: string} $file
     */
    private function respondFile(array $file, string $requestId): Response
    {
        $name = $file['name'];
        $mediaType = preg_match(StorageConfig::MIME_PATTERN, $file['media_type']) === 1
            ? $file['media_type']
            : 'application/octet-stream';
        $response = new Response(200, [
            'Content-Type' => $mediaType,
            'Content-Disposition' => 'attachment; filename="' . $this->asciiName($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'X-Request-Id' => $requestId,
        ], '');

        return $response->withFile($file['path']);
    }

    private function asciiName(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        $ascii = is_string($ascii) && $ascii !== '' ? $ascii : 'file';

        return substr($ascii, 0, 100);
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private function failure(int $status, string $code, string $message, array $fields, string $requestId): Response
    {
        return $this->respondJson([
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields === [] ? new \stdClass() : $fields,
                'request_id' => $requestId,
            ],
        ], $requestId);
    }

    private function unexpected(\Throwable $error, string $requestId): Response
    {
        error_log('FileController 异常 ' . $error::class . ': ' . $error->getMessage());

        return $this->failure(500, 'INTERNAL_ERROR', '系统出了点问题，请稍后再试', [], $requestId);
    }
}
