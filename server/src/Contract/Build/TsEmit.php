<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\Build;

use Sandadmin\Contract\SourceHash;

/**
 * 前端 TS 产物发射：web/src/api/v1/contract.generated.ts。
 * 出三样东西——信封类型、错误码/权限码/工具名联合类型、OPERATIONS 常量表。
 * OPERATIONS 带运行时值，前端 mock 与 vitest 能直接引用契约事实。
 *
 * @phpstan-import-type Operation from ContractDoc
 * @phpstan-import-type McpSpec from ContractDoc
 * @phpstan-import-type ErrorEntry from ContractDoc
 */
final class TsEmit
{
    /**
     * @param list<Operation> $operations
     * @param array<string, ErrorEntry> $catalog
     * @param array<string, string> $permissions
     * @param list<McpSpec> $tools
     * @param list<string> $sourceLabels
     */
    public static function emit(
        array $operations,
        array $catalog,
        array $permissions,
        array $tools,
        array $sourceLabels,
        string $sourceHash,
    ): string {
        $lines = [];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';
        $lines[] = '/** 成功信封：code 固定字符串 OK，成功判定只看这一个字段 */';
        $lines[] = 'export interface ApiSuccess<T = unknown> {';
        $lines[] = "  code: 'OK'";
        $lines[] = '  message: string';
        $lines[] = '  data: T';
        $lines[] = '  request_id: string';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '/** 失败信封：code 为契约登记的错误码，fields 是字段级错误 */';
        $lines[] = 'export interface ApiFailure {';
        $lines[] = '  code: ErrorCode';
        $lines[] = '  message: string';
        $lines[] = '  fields: Record<string, string[]>';
        $lines[] = '  request_id: string';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'export type ApiEnvelope<T = unknown> = ApiSuccess<T> | ApiFailure';
        $lines[] = '';
        $lines[] = '/** 契约登记的错误码（x-error-catalog 全集） */';
        $lines[] = 'export type ErrorCode =';
        $codes = array_keys($catalog);
        foreach ($codes as $index => $code) {
            $sep = $index === count($codes) - 1 ? ';' : '';
            $lines[] = "  | " . var_export($code, true) . $sep;
        }
        if ($codes === []) {
            $lines[] = '  never;';
        }
        $lines[] = '';
        $lines[] = "/** 成功码：与后端 ErrorCodeRegistry::OK 一致 */";
        $lines[] = "export const SUCCESS_CODE = 'OK' as const";
        $lines[] = '';
        $lines[] = '/** 契约声明的权限码全集 */';
        $lines[] = 'export type PermissionCode =';
        $permCodes = array_keys($permissions);
        foreach ($permCodes as $index => $code) {
            $sep = $index === count($permCodes) - 1 ? ';' : '';
            $lines[] = "  | " . var_export($code, true) . $sep;
        }
        if ($permCodes === []) {
            $lines[] = '  never;';
        }
        $lines[] = '';
        $lines[] = '/** MCP 工具名全集 */';
        $lines[] = 'export type McpToolName =';
        $toolNames = [];
        foreach ($tools as $tool) {
            $toolNames[] = $tool['name'];
        }
        foreach ($toolNames as $index => $name) {
            $sep = $index === count($toolNames) - 1 ? ';' : '';
            $lines[] = "  | " . var_export($name, true) . $sep;
        }
        if ($toolNames === []) {
            $lines[] = '  never;';
        }
        $lines[] = '';
        $lines[] = 'export type PermissionKind =';
        $lines[] = "  | 'single' | 'any_of' | 'all_of' | 'authenticated' | 'public' | 'super'";
        $lines[] = '';
        $lines[] = 'export interface ContractOperation {';
        $lines[] = '  method: string';
        $lines[] = '  path: string';
        $lines[] = '  operationId: string';
        $lines[] = '  permissionKind: PermissionKind';
        $lines[] = '  permissionCodes: readonly PermissionCode[]';
        $lines[] = '  errorCodes: readonly ErrorCode[]';
        $lines[] = '  tags: readonly string[]';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '/** 契约 operation 全集（方法+路径即路由表事实） */';
        $lines[] = 'export const OPERATIONS: readonly ContractOperation[] = [';
        foreach ($operations as $op) {
            $permCodesLit = self::tsList($op['permission_codes']);
            $errCodesLit = self::tsList($op['error_codes']);
            $tagsLit = self::tsList($op['tags']);
            $lines[] = '  {';
            $lines[] = '    method: ' . self::tsString($op['method']) . ',';
            $lines[] = '    path: ' . self::tsString($op['path']) . ',';
            $lines[] = '    operationId: ' . self::tsString($op['operation_id']) . ',';
            $lines[] = '    permissionKind: ' . self::tsString($op['permission_kind']) . ',';
            $lines[] = '    permissionCodes: ' . $permCodesLit . ',';
            $lines[] = '    errorCodes: ' . $errCodesLit . ',';
            $lines[] = '    tags: ' . $tagsLit . ',';
            $lines[] = '  },';
        }
        $lines[] = ']';
        $lines[] = '';
        $lines[] = '/** method + path 的联合查询键 */';
        $lines[] = 'export type RouteKey = `${string} ${string}`';
        $lines[] = '';
        $lines[] = 'export function findOperation(method: string, path: string): ContractOperation | undefined {';
        $lines[] = '  const upper = method.toUpperCase()';
        $lines[] = '  return OPERATIONS.find((op) => op.method === upper && op.path === path)';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param list<string> $values */
    private static function tsList(array $values): string
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = self::tsString($value);
        }

        return '[' . implode(', ', $items) . ']';
    }

    private static function tsString(string $value): string
    {
        // TS 源码用单引号字符串，转义 \ 与 '
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
