<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Mcp;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Transport\StatelessHttpTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sandadmin\Domain\Mcp\ToolTier;

/**
 * 按登记表组装 MCP 无状态协议端（Streamable HTTP 无状态修订版，U-3/M-03）：
 * 每个请求自带凭据，不建会话、不依赖长连接。每个请求重新构建一份，
 * 身份随 McpKernel 装订进处理器；构建成本对开发/管理面调用可忽略。
 * 有状态会话模式本期不做（将来上机器令牌时再接 SessionStoreInterface 实现）。
 */
final class McpServerFactory
{
    public function __construct(
        private readonly string $serverName,
        private readonly string $serverVersion,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function build(ToolCatalog $catalog, McpKernel $kernel): StatelessHttpTransport
    {
        $builder = Server::builder()
            ->setServerInfo($this->serverName, $this->serverVersion)
            ->setLogger($this->logger ?? new NullLogger());
        foreach ($catalog->all() as $spec) {
            $builder->add(self::definition($spec), new SdkToolHandler($kernel, $spec));
        }

        return new StatelessHttpTransport(
            $builder->buildStateless(),
            logger: $this->logger ?? new NullLogger(),
        );
    }

    private static function definition(\Sandadmin\Domain\Mcp\ToolSpec $spec): Tool
    {
        return new Tool(
            $spec->name,
            $spec->title === '' ? null : $spec->title,
            $spec->inputSchema,
            $spec->description === '' ? null : $spec->description,
            new ToolAnnotations(
                readOnlyHint: $spec->tier === ToolTier::Read,
                destructiveHint: $spec->tier === ToolTier::HighRisk,
                idempotentHint: $spec->tier === ToolTier::Write,
            ),
        );
    }
}
