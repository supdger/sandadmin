<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Application\Mcp\McpHttpBridge;
use Sandadmin\Application\Mcp\McpKernel;
use Sandadmin\Application\Mcp\McpServerFactory;
use Sandadmin\Application\Mcp\ToolCatalog;
use Sandadmin\Infra\Mcp\Dev\DevCatalog;
use Sandadmin\Infra\Mcp\DevTokenVerifier;
use Sandadmin\Infra\Mcp\LogAuditSink;
use Sandadmin\Infra\Mcp\McpGate;
use Sandadmin\Infra\Mcp\WindowRateLimiter;
use support\Request;
use support\Response;
use Webman\Route;

/**
 * 开发构建 MCP 端点（POST /api/admin/mcp，无状态 Streamable HTTP）。
 * 挂出前提全部满足才存在：.sand-dev-build 标记、SAND_MCP_DEV_TOKEN、
 * 回环对端、无转发头、Origin 本机白名单；缺一即当端点不存在（404），
 * 不向调用方暴露「这里有 MCP」这一信息。凭据 → 限流 → SDK 协议层 →
 * McpKernel 收口（权限码/三档/幂等/审计）。
 */
final class McpController
{
    public function handle(Request $request): Response
    {
        if (!DevTokenVerifier::configured() || !McpGate::remoteAllowed($request)) {
            return $this->plain(404, 'Not Found');
        }
        $origin = $request->header('origin');
        if (!McpGate::originAllowed(is_string($origin) ? $origin : null)) {
            return $this->json(403, 'MCP_ORIGIN_REJECTED', 'Origin 不在本机白名单');
        }
        $authorization = $request->header('authorization');
        $token = $this->bearerToken(is_string($authorization) ? $authorization : null);
        $identity = $token !== null ? (new DevTokenVerifier())->verify($token) : null;
        if ($identity === null) {
            return $this->json(401, 'MCP_UNAUTHORIZED', '凭据无效或未配置')
                ->withHeader('WWW-Authenticate', 'Bearer realm="mcp"');
        }
        $incomingId = $request->header('x-request-id');
        $requestId = (new RequestIds())->resolve(is_string($incomingId) ? $incomingId : null);
        $limiter = new WindowRateLimiter();
        $window = self::intConfig('mcp.dev.window_seconds', 60);
        if (!$limiter->hit('mcp:credential:' . $identity->credential, self::intConfig('mcp.dev.credential_limit', 60), $window)) {
            return $this->json(429, 'MCP_RATE_LIMITED', '调用频率超限，稍后再试');
        }
        $kernel = new McpKernel(
            $identity,
            $limiter,
            new LogAuditSink(\support\Log::channel('default')),
            $requestId,
            $request->getRemoteIp(),
            self::intConfig('mcp.dev.tool_limit', 30),
            $window,
        );
        $transport = (new McpServerFactory(
            'sandadmin',
            '1.x',
            new \Sandadmin\Infra\Log\SdkQuietLogger(\support\Log::channel('default')),
        ))->build(new ToolCatalog($this->devSpecs()), $kernel);

        return McpHttpBridge::fromPsr7(
            $transport->handle(McpHttpBridge::toPsr7($request, $identity)),
        );
    }

    /** @return list<\Sandadmin\Domain\Mcp\ToolSpec> */
    private function devSpecs(): array
    {
        $serverRoot = base_path();
        $repoRoot = dirname($serverRoot);
        $timeout = self::intConfig('mcp.dev.test_timeout', 120);
        $gateTimeout = self::intConfig('mcp.dev.gate_timeout', 180);

        return DevCatalog::specs(
            $serverRoot,
            $repoRoot,
            runtime_path('logs'),
            [
                $serverRoot . '/plugin/sandadmin/basic/contracts/ModelInterface.php',
                $serverRoot . '/plugin/sandadmin/basic/contracts/LogicInterface.php',
            ],
            self::routeReader(...),
            $timeout,
            $gateTimeout,
        );
    }

    /** @return list<array{methods: list<string>, path: string, callback: string}> */
    private static function routeReader(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $methods = [];
            foreach ($route->getMethods() as $method) {
                if (is_string($method)) {
                    $methods[] = strtoupper($method);
                }
            }
            $routes[] = [
                'methods' => $methods,
                'path' => $route->getPath(),
                'callback' => self::callbackName($route->getCallback()),
            ];
        }

        return $routes;
    }

    private static function callbackName(mixed $callback): string
    {
        if (is_array($callback) && count($callback) === 2) {
            [$class, $method] = $callback;
            $className = is_object($class) ? $class::class : (is_string($class) ? $class : get_debug_type($class));

            return $className . '::' . (is_string($method) ? $method : get_debug_type($method));
        }
        if ($callback instanceof \Closure) {
            return 'closure';
        }

        return is_string($callback) ? $callback : get_debug_type($callback);
    }

    /** config() 返回 mixed；非整数或数值串一律回落默认值，配置写坏也不改语义。 */
    private static function intConfig(string $key, int $default): int
    {
        $value = config($key);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function bearerToken(?string $header): ?string
    {
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    private function plain(int $status, string $body): Response
    {
        return new Response($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    private function json(int $status, string $code, string $message): Response
    {
        return new Response($status, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(
            ['code' => $code, 'message' => $message],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }
}
