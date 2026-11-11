<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileActor;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FileQuery;
use Sandadmin\Domain\File\FileUsageState;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Domain\File\UsageAccess;
use Sandadmin\Domain\File\UsageDraft;

/**
 * /api/admin/v1/files 契约形状的组装层：入参校验、权限判定、
 * FileObject/PageFile/TaskAccepted/ErrorEnvelope 输出，不含 HTTP 传输细节。
 * 成功信封 {data,request_id}、失败信封 {code,message,fields,request_id}；
 * 错误码只用 api-conventions §2.4 总表。B2 统一错误实现（#56）合入后，
 * 控制器改调其 ErrorResponder/JsonErrorResponse，本类输出形状不变。
 */
final class FileHttp
{
    private const FILE_ID_PATTERN = '/\A\d{1,18}\z/';

    private const PERMISSION_INDEX = 'system:file:index';

    private const PERMISSION_DELETE = 'system:file:delete';

    private const PERMISSION_CLEANUP = 'system:file:cleanup';

    private const IDEMPOTENCY_PATTERN = '/^[A-Za-z0-9._~-]{1,128}$/';

    /**
     * POST /files。multipart 的 file 字段由控制器提取为流后传入；
     * visibility/usage 为可选表单字段，Idempotency-Key 防重复上传。
     *
     * @param resource $stream
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function upload(
        UploadService $uploads,
        ?FileActor $actor,
        mixed $stream,
        ?int $contentLength,
        string $originalName,
        mixed $visibilityParam,
        mixed $usageParam,
        ?string $idempotencyKey,
        string $requestId,
    ): array {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        $fields = [];
        $keyError = self::idempotencyKeyError($idempotencyKey);
        if ($keyError !== null) {
            $fields['idempotency_key'] = [$keyError];
        }
        $visibility = self::visibility($visibilityParam, $fields);
        $usage = self::usage($usageParam, $fields);
        if ($fields !== []) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
        }
        try {
            $record = $uploads->upload(
                $stream,
                $contentLength,
                $originalName,
                $visibility ?? FileVisibility::Private,
                $usage,
                $actor->userId,
                $idempotencyKey,
            );

            return self::success($record->toApi(), $requestId);
        } catch (IdempotentReplay $replay) {
            return self::success($replay->response, $requestId);
        } catch (FileException $error) {
            return self::failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        }
    }

    /**
     * GET /files
     *
     * @param array<array-key, mixed> $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(FileService $files, ?FileActor $actor, array $query, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (!$actor->can(self::PERMISSION_INDEX)) {
            return self::failure(403, 'PERMISSION_DENIED', '你没有权限做这个操作', [], $requestId);
        }
        $fields = [];
        $fileQuery = self::fileQuery($query, $fields);
        if ($fields !== []) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
        }
        try {
            $page = $files->search($fileQuery);

            return self::success([
                'items' => array_map(static fn ($record) => $record->toApi(), $page->items),
                'total' => $page->total,
                'page' => $page->page,
                'page_size' => $page->pageSize,
            ], $requestId);
        } catch (FileException $error) {
            return self::failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        }
    }

    /**
     * GET /files/{file_id}
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function show(FileService $files, ?FileActor $actor, string $fileId, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (!$actor->can(self::PERMISSION_INDEX)) {
            return self::failure(403, 'PERMISSION_DENIED', '你没有权限做这个操作', [], $requestId);
        }
        $id = self::fileId($fileId);
        if ($id === null) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['file_id' => ['文件编号不正确']], $requestId);
        }
        try {
            return self::success($files->findForManagement($id)->toApi(), $requestId);
        } catch (FileException $error) {
            return self::failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        }
    }

    /**
     * DELETE /files/{file_id}
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function destroy(FileService $files, ?FileActor $actor, string $fileId, ?string $idempotencyKey, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (!$actor->can(self::PERMISSION_DELETE)) {
            return self::failure(403, 'PERMISSION_DENIED', '你没有权限做这个操作', [], $requestId);
        }
        $fields = [];
        $keyError = self::idempotencyKeyError($idempotencyKey);
        if ($keyError !== null) {
            $fields['idempotency_key'] = [$keyError];
        }
        $id = self::fileId($fileId);
        if ($id === null) {
            $fields['file_id'] = ['文件编号不正确'];
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
        }
        if ($fields !== []) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
        }
        try {
            $files->remove($id, $actor->userId, $idempotencyKey);

            return self::success(new \stdClass(), $requestId);
        } catch (IdempotentReplay $replay) {
            return self::success($replay->response, $requestId);
        } catch (FileException $error) {
            return self::failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        }
    }

    /**
     * GET /files/{file_id}/download
     *
     * @return array{status: int, body?: array<string, mixed>, file?: array{path: string, name: string, media_type: string}}
     */
    public static function download(FileService $files, UsageAccess $access, ?FileActor $actor, string $fileId, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        $id = self::fileId($fileId);
        if ($id === null) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', ['file_id' => ['文件编号不正确']], $requestId);
        }
        try {
            // 下载分流：只服务 purpose=attachment，export/package/plugin 按 FILE_NOT_FOUND
            $record = $files->findForManagement($id);
            $files->assertDownloadable($record, $actor, $access);
            $path = $files->downloadPath($record);

            return [
                'status' => 200,
                'file' => [
                    'path' => $path,
                    'name' => $record->originalName,
                    'media_type' => $record->mediaType,
                ],
            ];
        } catch (FileException $error) {
            return self::failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        }
    }

    /**
     * POST /files/cleanup → TaskAccepted{task_id,state:queued}
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function cleanup(FileService $files, ?FileActor $actor, ?string $idempotencyKey, string $requestId): array
    {
        if ($actor === null) {
            return self::failure(401, 'UNAUTHENTICATED', '请先登录', [], $requestId);
        }
        if (!$actor->can(self::PERMISSION_CLEANUP)) {
            return self::failure(403, 'PERMISSION_DENIED', '你没有权限做这个操作', [], $requestId);
        }
        $fields = [];
        $keyError = self::idempotencyKeyError($idempotencyKey);
        if ($keyError !== null) {
            $fields['idempotency_key'] = [$keyError];
        }
        if ($fields !== []) {
            return self::failure(400, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', $fields, $requestId);
        }
        try {
            return self::success($files->cleanup($actor->userId, $idempotencyKey), $requestId);
        } catch (FileException $error) {
            return self::failure($error->status, $error->errorCode, $error->getMessage(), $error->fields, $requestId);
        }
    }

    /**
     * @param array<string, list<string>> $fields 出参：收集字段级错误
     */
    private static function visibility(mixed $value, array &$fields): ?FileVisibility
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === 'private') {
            return FileVisibility::Private;
        }
        if ($value === 'public') {
            return FileVisibility::Public;
        }
        $fields['visibility'] = ['取值必须是 private 或 public'];

        return null;
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private static function usage(mixed $value, array &$fields): ?UsageDraft
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            $fields['usage'] = ['用途标识格式应为 类型:编号'];

            return null;
        }
        try {
            return UsageDraft::parse($value);
        } catch (FileException) {
            $fields['usage'] = ['用途标识格式应为 类型:编号'];

            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, list<string>> $fields
     */
    private static function fileQuery(array $query, array &$fields): FileQuery
    {
        $keyword = self::stringParam($query, 'keyword');
        if ($keyword !== null && mb_strlen($keyword) > 100) {
            $fields['keyword'] = ['关键词过长'];
        }
        $kind = null;
        $kindParam = self::stringParam($query, 'kind');
        if ($kindParam !== null) {
            $kind = FileKind::tryFrom($kindParam);
            if ($kind === null) {
                $fields['kind'] = ['文件类型不正确'];
            }
        }
        $usageState = null;
        $stateParam = self::stringParam($query, 'usage_state');
        if ($stateParam !== null) {
            $usageState = FileUsageState::tryFrom($stateParam);
            if ($usageState === null) {
                $fields['usage_state'] = ['使用情况取值不正确'];
            }
        }
        $uploaderId = self::intParam($query, 'uploader_id', null, 1, PHP_INT_MAX, 'uploader_id', $fields);
        $from = self::timeParam($query, 'create_time_from', $fields);
        $to = self::timeParam($query, 'create_time_to', $fields);
        $page = self::intParam($query, 'page', 1, 1, 1000000, 'page', $fields) ?? 1;
        $pageSize = self::intParam($query, 'page_size', 20, 1, 100, 'page_size', $fields) ?? 20;

        return new FileQuery(
            keyword: $keyword,
            kind: $kind,
            usageState: $usageState,
            uploaderId: $uploaderId,
            createTimeFrom: $from,
            createTimeTo: $to,
            page: $page,
            pageSize: $pageSize,
        );
    }

    private static function fileId(string $fileId): ?int
    {
        if (preg_match(self::FILE_ID_PATTERN, $fileId) !== 1) {
            return null;
        }
        $id = (int) $fileId;

        return $id > 0 ? $id : null;
    }

    private static function idempotencyKeyError(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }
        if (preg_match(self::IDEMPOTENCY_PATTERN, $key) !== 1) {
            return '幂等键不正确';
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function stringParam(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, list<string>> $fields
     */
    private static function intParam(
        array $query,
        string $key,
        ?int $default,
        int $min,
        int $max,
        string $field,
        array &$fields,
    ): ?int {
        $value = $query[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_string($value) && !is_int($value)) {
            $fields[$field] = ['必须是整数'];

            return $default;
        }
        if (!preg_match('/^-?\d+$/', (string) $value)) {
            $fields[$field] = ['必须是整数'];

            return $default;
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            $fields[$field] = ['取值超出允许范围'];

            return $default;
        }

        return $number;
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, list<string>> $fields
     */
    private static function timeParam(array $query, string $key, array &$fields): ?\DateTimeImmutable
    {
        $value = $query[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?\z/', $value) !== 1) {
            $fields[$key] = ['时间格式应为 ISO 8601'];

            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            $fields[$key] = ['时间格式应为 ISO 8601'];

            return null;
        }
    }

    /**
     * data 为契约 JSON 值：对象、数组、标量或 null（幂等重放快照原样返回）。
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function success(mixed $data, string $requestId): array
    {
        return [
            'status' => 200,
            'body' => ['data' => $data, 'request_id' => $requestId],
        ];
    }

    /**
     * @param array<string, list<string>> $fields
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function failure(int $status, string $code, string $message, array $fields, string $requestId): array
    {
        FileException::assertCode($code);

        return [
            'status' => $status,
            'body' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields === [] ? new \stdClass() : $fields,
                'request_id' => $requestId,
            ],
        ];
    }
}
