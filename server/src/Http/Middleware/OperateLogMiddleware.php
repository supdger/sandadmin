<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Module\AuditLog\OperateLogWriter;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 操作日志结果落库：插件 SystemLog 中间件在业务处理前 emit
 * user.operateLog，只够给请求打待记录标记。本中间件挂在 '@' 全局
 * 层（含插件请求的最外层），等响应生成后按真实结果写唯一一行；
 * 下游异常逃逸出链时先记失败再原样抛出。
 */
final class OperateLogMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        try {
            $response = $handler($request);
        } catch (\Throwable $error) {
            OperateLogWriter::recordOutcome($request, null);
            throw $error;
        }
        if (!$response instanceof Response) {
            OperateLogWriter::recordOutcome($request, null);
            throw new \RuntimeException('操作日志中间件下游必须返回 Response');
        }
        OperateLogWriter::recordOutcome($request, $response);

        return $response;
    }
}
