<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\controller;

use Sandadmin\Application\File\UploadRequest;
use Sandadmin\Http\File\FileStackFactory;
use Sandadmin\Http\File\FileUsageHandle;
use Sandadmin\Http\File\HttpErrorMapper;
use Sandadmin\Http\File\HttpResult;
use Sandadmin\Http\File\RequestId;
use Sandadmin\Http\File\UploadHttp;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\StorageConfig;
use support\Request;
use support\Response;
use Webman\Http\UploadFile;

final class FileController
{
    public function store(Request $request): Response
    {
        $requestId = $this->requestId($request);
        $upload = $request->file('file');
        if (is_array($upload)) {
            return $this->respond(HttpResult::error(422, 'invalid_upload', '一次只能上传一个文件', ['file' => '一次只能上传一个文件'], $requestId));
        }
        if (!$upload instanceof UploadFile) {
            return $this->respond(HttpResult::error(422, 'invalid_upload', '请使用 file 字段上传文件', ['file' => '缺少上传文件'], $requestId));
        }
        if (!$upload->isValid()) {
            return $this->respond(HttpResult::error(422, 'invalid_upload', '上传失败', ['file' => '上传未完成'], $requestId));
        }
        $stream = fopen($upload->getPathname(), 'rb');
        if ($stream === false) {
            return $this->respond(HttpResult::error(422, 'invalid_upload', '无法读取上传文件', ['file' => '无法读取上传文件'], $requestId));
        }
        try {
            $http = new UploadHttp(FileStackFactory::uploadService($this->storageConfig()), new HttpErrorMapper());
            $size = $upload->getSize();
            $name = $upload->getUploadName();

            return $this->respond($http->upload(new UploadRequest(
                $stream,
                is_int($size) ? $size : null,
                is_string($name) && $name !== '' ? $name : 'file',
            ), $requestId));
        } catch (StorageException $error) {
            return $this->respond((new HttpErrorMapper())->map($error, $requestId));
        } catch (\Throwable) {
            return $this->respond(HttpResult::error(500, 'internal_error', '处理请求时发生错误', [], $requestId));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function registerUsage(Request $request, string $id): Response
    {
        return $this->usage($request, $id, 'register');
    }

    public function unregisterUsage(Request $request, string $id): Response
    {
        return $this->usage($request, $id, 'unregister');
    }

    public function destroy(Request $request, string $id): Response
    {
        return $this->usage($request, $id, 'delete');
    }

    private function usage(Request $request, string $id, string $operation): Response
    {
        $requestId = $this->requestId($request);
        $payload = null;
        if ($operation !== 'delete') {
            $payload = $this->payload($request);
            if ($payload === null) {
                return $this->respond(HttpResult::error(
                    422,
                    'invalid_usage',
                    '请求体必须是 JSON 对象',
                    ['body' => '请求体必须是 JSON 对象'],
                    $requestId,
                ));
            }
        }
        try {
            $handle = $this->openUsage();
        } catch (StorageException $error) {
            return $this->respond((new HttpErrorMapper())->map($error, $requestId));
        } catch (\Throwable) {
            return $this->respond(HttpResult::error(500, 'internal_error', '处理请求时发生错误', [], $requestId));
        }
        try {
            if ($operation === 'delete') {
                $result = $handle->http->delete($id, $requestId);
            } elseif ($payload === null) {
                $result = HttpResult::error(422, 'invalid_usage', '请求体必须是 JSON 对象', ['body' => '请求体必须是 JSON 对象'], $requestId);
            } elseif ($operation === 'register') {
                $result = $handle->http->register($id, $payload, $requestId);
            } else {
                $result = $handle->http->unregister($id, $payload, $requestId);
            }

            return $this->respond($result);
        } finally {
            $handle->close();
        }
    }

    private function openUsage(): FileUsageHandle
    {
        $connection = FileStackFactory::connectionFromDatabaseConfig($this->databaseConfig());
        try {
            return FileStackFactory::usageHandle($this->storageConfig(), $connection);
        } catch (\Throwable $error) {
            $connection->close();
            throw $error;
        }
    }

    private function storageConfig(): StorageConfig
    {
        return StorageConfig::fromArray($this->namedConfig(config('file'), '存储配置无效'));
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseConfig(): array
    {
        $value = config('database.connections.pgsql');
        if (!is_array($value)) {
            return [];
        }

        return $this->stringKeyed($value, '数据库尚未配置');
    }

    /**
     * @return array<string, mixed>
     */
    private function namedConfig(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new StorageException('invalid_config', $message);
        }

        return $this->stringKeyed($value, $message);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $value, string $message): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new StorageException('invalid_config', $message);
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(Request $request): ?array
    {
        $decoded = json_decode($request->rawBody(), true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            return null;
        }
        $result = [];
        foreach ($decoded as $key => $item) {
            if (!is_string($key)) {
                return null;
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private function requestId(Request $request): string
    {
        $header = $request->header('x-request-id');

        return RequestId::fromHeader(is_string($header) ? $header : null);
    }

    private function respond(HttpResult $result): Response
    {
        return new Response($result->status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Request-Id' => $result->requestId,
        ], $result->json());
    }
}
