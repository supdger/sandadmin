<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Biz;

use Sandadmin\Domain\Mcp\BizGateway;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Domain\Mcp\ToolOutcome;
use Sandadmin\Domain\Mcp\ToolRunner;
use Sandadmin\Http\RequestIds;

/**
 * 业务工具的统一执行器：只把「操作键 + 参数 + 调用上下文」交给网关，
 * 权限、派发、错误码全在网关一侧。context 缺省时补一份自造上下文——
 * 仅在绕过内核的测试直调里发生，正常链路恒由 McpKernel 传入。
 */
final class BizToolRunner implements ToolRunner
{
    public function __construct(
        private readonly BizGateway $gateway,
        private readonly string $operation,
    ) {
    }

    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments, ?CallContext $context = null): ToolOutcome
    {
        $context ??= new CallContext((new RequestIds())->resolve(null), null, McpIdentity::CHANNEL);
        $reply = $this->gateway->call($identity, $this->operation, $arguments, $context);
        if ($reply->succeeded()) {
            return ToolOutcome::ok($reply->data);
        }

        return ToolOutcome::fail($reply->code, ['message' => $reply->message, 'fields' => $reply->fields]);
    }
}
