<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Mcp;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;
use Sandadmin\Domain\Mcp\ToolSpec;

/**
 * SDK 显式工具处理器与内核 McpKernel 之间的桥。
 * 每个请求新建（身份随 kernel 一起装订），不进任何共享状态。
 */
final class SdkToolHandler implements ToolHandlerInterface
{
    public function __construct(
        private readonly McpKernel $kernel,
        private readonly ToolSpec $spec,
    ) {
    }

    /** @param array<string, mixed> $arguments */
    public function execute(array $arguments, ClientGateway $gateway): CallToolResult
    {
        $outcome = $this->kernel->call($this->spec, $arguments);
        $structured = is_array($outcome->payload) ? $outcome->payload : null;

        return new CallToolResult(
            [new TextContent($outcome->payload)],
            isError: !$outcome->succeeded,
            structuredContent: $structured,
        );
    }
}
