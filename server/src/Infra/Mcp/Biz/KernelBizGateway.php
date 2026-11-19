<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Biz;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Domain\Mcp\BizGateway;
use Sandadmin\Domain\Mcp\BizReply;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Domain\Mcp\McpActorSource;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Http\BizKernel;
use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Menu\MenuException;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;

/**
 * 业务工具网关（U-3/M-01）：MCP 身份 → OrgActor → 与 v1 控制器同一套
 * 权限判定 → 显式映射表派发 → 契约错误码回包。McpKernel 的凭据 scope
 * 检查是外闸，这里的 actor->can 是与人工操作同一判定的内闸，两层都过
 * 才执行；身份落不到有效账号按未认证拒绝。
 * kernelFactory 延迟求值：构造时不连库，调用时才取 BizKernel——
 * 库不可用时回 INTERNAL_ERROR，不拖垮工具清单本身。
 */
final class KernelBizGateway implements BizGateway
{
    /** @var \Closure(): ?BizKernel */
    private readonly \Closure $kernelFactory;

    /** @var \Closure(BizKernel, OrgActor, string, array<string, mixed>, CallContext): BizReply */
    private readonly \Closure $dispatcher;

    /**
     * @param \Closure(): ?BizKernel                                                            $kernelFactory
     * @param null|\Closure(BizKernel, OrgActor, string, array<string, mixed>, CallContext): BizReply $dispatcher
     *        测试替身注入口；生产恒为 BizDispatch::call
     */
    public function __construct(
        \Closure $kernelFactory,
        private readonly McpActorSource $actors,
        ?\Closure $dispatcher = null,
    ) {
        $this->kernelFactory = $kernelFactory;
        $this->dispatcher = $dispatcher ?? BizDispatch::call(...);
    }

    /** @param array<string, mixed> $arguments */
    public function call(McpIdentity $identity, string $operation, array $arguments, CallContext $context): BizReply
    {
        $permission = BizDispatch::permissions()[$operation] ?? null;
        if ($permission === null) {
            return BizReply::fail(500, ErrorCodeRegistry::MCP_TOOL_FAILED, '工具执行失败，详情见服务端日志');
        }
        try {
            $kernel = ($this->kernelFactory)();
            if (!$kernel instanceof BizKernel) {
                return BizReply::fail(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试');
            }
            $actor = $this->actors->resolve($identity, $kernel->connection);
            if ($actor === null) {
                return BizReply::fail(401, ErrorCodeRegistry::UNAUTHENTICATED, '请先登录');
            }
            if (!$actor->can($permission)) {
                return BizReply::fail(403, ErrorCodeRegistry::PERMISSION_DENIED, '你没有权限做这个操作');
            }

            return ($this->dispatcher)($kernel, $actor, $operation, $arguments, $context);
        } catch (OrgException | DictException | MenuException $error) {
            return BizReply::fail($error->status, $error->errorCode, $error->getMessage(), $error->fields);
        } catch (\Throwable $error) {
            error_log('MCP 业务工具异常 ' . $error::class . ': ' . $error->getMessage());

            return BizReply::fail(500, ErrorCodeRegistry::INTERNAL_ERROR, '系统出了点问题，请稍后再试');
        }
    }
}
