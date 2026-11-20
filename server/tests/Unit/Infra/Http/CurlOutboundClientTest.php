<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Http\OutboundException;
use Sandadmin\Domain\Http\OutboundRequest;
use Sandadmin\Domain\Http\TraceContextResolver;
use Sandadmin\Infra\Http\CurlOutboundClient;
use Sandadmin\Infra\Http\RequestTraceResolver;
use Sandadmin\Tests\Support\FakeHttpServer;
use Webman\Http\Request;

/**
 * 统一出站客户端：假服务覆盖正常应答、超时、截断、重试、追踪头与 SSRF。
 * connect 超时（SYN 黑洞）在本机回环环境无法稳定复现——任何外联地址都会被
 * 代理/DNS 接管，改用「连接拒绝」覆盖传输错误路径，connect_timeout_ms 的
 * 生效由配置透传与代码路径保证，见 IMPLEMENTATION.md 说明。
 */
final class CurlOutboundClientTest extends TestCase
{
    /** @var list<FakeHttpServer> */
    private array $servers = [];

    /** @var list<int> */
    private array $sleeps = [];

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            $server->stop();
        }
        $this->servers = [];
        $this->sleeps = [];
    }

    /** @param array<string, mixed> $options */
    private function server(array $options = []): FakeHttpServer
    {
        $server = FakeHttpServer::start($options);
        $this->servers[] = $server;

        return $server;
    }

    private function url(FakeHttpServer $server, string $path = '/probe'): string
    {
        return 'http://127.0.0.1:' . $server->port . $path;
    }

    /** @param array<string, mixed> $config */
    private function client(array $config = [], ?TraceContextResolver $traces = null): CurlOutboundClient
    {
        return new CurlOutboundClient(array_merge([
            'connect_timeout_ms' => 1000,
            'read_timeout_ms' => 5000,
            'total_timeout_ms' => 10000,
            'max_attempts' => 1,
            'backoff_base_ms' => 20,
            'backoff_max_ms' => 200,
            'max_body_bytes' => 1024 * 1024,
            'retry_statuses' => [502, 503, 504],
            'allow_private_hosts' => ['127.0.0.1'],
        ], $config), null, $traces, function (int $us): void {
            $this->sleeps[] = $us;
        }, static fn (int $max): int => 0);
    }

    public function testGetReturnsStatusHeadersBody(): void
    {
        $server = $this->server(['status' => 201, 'headers' => ['X-Test' => 'yes'], 'body' => 'hello']);
        $response = $this->client()->request(new OutboundRequest('GET', $this->url($server, '/path?q=1')));

        self::assertSame(201, $response->status);
        self::assertSame('hello', $response->body);
        self::assertSame('yes', $response->header('x-test'));
        self::assertFalse($response->truncated);
        $requests = $server->requests();
        self::assertCount(1, $requests);
        self::assertStringContainsString('GET /path?q=1 HTTP/1.1', $requests[0]['head']);
    }

    public function testPostSendsMethodBodyAndHeaders(): void
    {
        $server = $this->server();
        $this->client()->request(new OutboundRequest(
            'post',
            $this->url($server, '/submit'),
            ['X-Custom' => 'v1'],
            '{"a":1}',
        ));

        $requests = $server->requests();
        self::assertCount(1, $requests);
        self::assertStringContainsString('POST /submit HTTP/1.1', $requests[0]['head']);
        self::assertStringContainsString('X-Custom: v1', $requests[0]['head']);
        self::assertSame('{"a":1}', $requests[0]['body']);
    }

    public function testTraceHeadersGeneratedWithoutRequestContext(): void
    {
        $server = $this->server();
        // 无 webman 请求上下文（Task/CLI 场景）：自行生成 traceparent 与 x-request-id
        $this->client()->request(new OutboundRequest('GET', $this->url($server)));

        $head = $server->requests()[0]['head'];
        self::assertMatchesRegularExpression('/traceparent: 00-[0-9a-f]{32}-[0-9a-f]{16}-01/', $head);
        self::assertMatchesRegularExpression('/x-request-id: [0-9a-f]{32}/', $head);
        self::assertStringNotContainsString('tracestate:', $head);
    }

    public function testInboundTraceparentAndRequestIdPassedThrough(): void
    {
        $server = $this->server();
        $traceId = str_repeat('a', 32);
        $inbound = new Request("GET / HTTP/1.1\r\nHost: t\r\n"
            . 'traceparent: 00-' . $traceId . '-' . str_repeat('b', 16) . '-03' . "\r\n"
            . 'tracestate: vendor=abc' . "\r\n"
            . 'x-request-id: req-inbound-1' . "\r\n\r\n");
        $traces = new RequestTraceResolver(null, null, null, static fn (): Request => $inbound);
        $this->client([], $traces)->request(new OutboundRequest('GET', $this->url($server)));

        $head = $server->requests()[0]['head'];
        // trace-id 续接、parent-id 换新、flags 保留；tracestate 与 request id 透传
        self::assertMatchesRegularExpression(
            '/traceparent: 00-' . $traceId . '-(?!' . str_repeat('b', 16) . ')[0-9a-f]{16}-03/',
            $head,
        );
        self::assertStringContainsString('tracestate: vendor=abc', $head);
        self::assertStringContainsString('x-request-id: req-inbound-1', $head);
    }

    public function testExplicitRequestHeadersOverrideTraceDefaults(): void
    {
        $server = $this->server();
        $this->client()->request(new OutboundRequest(
            'GET',
            $this->url($server),
            ['x-request-id' => 'explicit-id-1'],
        ));

        $head = $server->requests()[0]['head'];
        self::assertStringContainsString('x-request-id: explicit-id-1', $head);
    }

    public function testNon2xxReturnedAsResponse(): void
    {
        $server = $this->server(['status' => 404, 'body' => 'missing']);
        $response = $this->client()->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(404, $response->status);
        self::assertSame('missing', $response->body);
    }

    public function testRedirectNotFollowed(): void
    {
        $server = $this->server([
            'status' => 302,
            'headers' => ['Location' => '/next'],
        ]);
        $response = $this->client()->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(302, $response->status);
        self::assertSame('/next', $response->header('location'));
        self::assertSame(1, $server->requestCount(), '不应跟随重定向发起第二次请求');
    }

    public function testChunkedResponseBodyDecoded(): void
    {
        $server = $this->server(['body' => 'chunked-body-content', 'chunked' => true]);
        $response = $this->client()->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(200, $response->status);
        self::assertSame('chunked-body-content', $response->body);
    }

    public function testMaxBodyBytesTruncates(): void
    {
        $server = $this->server(['body_size' => 2 * 1024 * 1024]);
        $response = $this->client(['max_body_bytes' => 4096])
            ->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(200, $response->status);
        self::assertTrue($response->truncated);
        self::assertLessThanOrEqual(4096, strlen($response->body));
    }

    public function testReadTimeoutAbortsStalledResponse(): void
    {
        $server = $this->server(['hang' => true]);
        // read_timeout_ms=2000 → LOW_SPEED_TIME=2s；total 放宽保证是停滞判定先触发
        $started = microtime(true);
        try {
            $this->client([
                'read_timeout_ms' => 2000,
                'total_timeout_ms' => 30000,
            ])->request(new OutboundRequest('GET', $this->url($server)));
            self::fail('停滞响应未触发读超时');
        } catch (OutboundException $error) {
            self::assertSame('timeout', $error->kind());
        }
        self::assertLessThan(20.0, microtime(true) - $started);
    }

    public function testTotalTimeoutAbortsSlowResponse(): void
    {
        $server = $this->server(['delay' => 5.0, 'body' => 'late']);
        // 服务 5 秒后才应答，整体超时 800ms 先触发
        try {
            $this->client([
                'total_timeout_ms' => 800,
                'read_timeout_ms' => 20000,
            ])->request(new OutboundRequest('GET', $this->url($server)));
            self::fail('慢响应未触发整体超时');
        } catch (OutboundException $error) {
            self::assertSame('timeout', $error->kind());
        }
    }

    public function testConnectRefusedIsTransportError(): void
    {
        // 真实 SYN 黑洞在回环环境不可复现，改用必然拒绝的空端口覆盖传输错误路径
        $server = $this->server();
        $port = $server->port;
        $server->stop();
        $this->servers = array_values(array_filter($this->servers, static fn ($s) => $s !== $server));

        try {
            $this->client()->request(new OutboundRequest('GET', 'http://127.0.0.1:' . $port . '/'));
            self::fail('连接拒绝未抛异常');
        } catch (OutboundException $error) {
            self::assertSame('transport', $error->kind());
        }
    }

    public function testSsrfBlockedBeforeAnyRequest(): void
    {
        $server = $this->server();
        try {
            $this->client()->request(new OutboundRequest('GET', 'http://169.254.169.254/latest'));
            self::fail('SSRF 目标未被拦截');
        } catch (OutboundException $error) {
            self::assertSame('ssrf', $error->kind());
        }
        // 白名单只放行 127.0.0.1，假服务未收到任何请求
        self::assertSame(0, $server->requestCount());
    }

    public function testRetryOnClosedConnectionThenSucceeds(): void
    {
        $server = $this->server(['close_empty' => 1, 'body' => 'recovered']);
        $response = $this->client(['max_attempts' => 3])->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(200, $response->status);
        self::assertSame('recovered', $response->body);
        self::assertSame(2, $server->requestCount());
        // 第一次重试退避 = backoff_base_ms(20) * 2^0 + jitter(0) = 20ms = 20000us
        self::assertSame([20000], $this->sleeps);
    }

    public function testRetryOnConfiguredStatus(): void
    {
        $server = $this->server(['status' => [503, 503, 200], 'body' => 'ok']);
        $response = $this->client(['max_attempts' => 3])->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(200, $response->status);
        self::assertSame(3, $server->requestCount());
        self::assertSame([20000, 40000], $this->sleeps, '指数退避：20ms、40ms');
    }

    public function testUnlistedStatusNotRetried(): void
    {
        $server = $this->server(['status' => 500]);
        $response = $this->client(['max_attempts' => 3])->request(new OutboundRequest('GET', $this->url($server)));

        self::assertSame(500, $response->status);
        self::assertSame(1, $server->requestCount());
        self::assertSame([], $this->sleeps);
    }

    public function testPostNotRetriedByDefault(): void
    {
        $server = $this->server(['status' => [503, 200]]);
        $response = $this->client(['max_attempts' => 3])
            ->request(new OutboundRequest('POST', $this->url($server), [], 'payload'));

        self::assertSame(503, $response->status);
        self::assertSame(1, $server->requestCount(), '非幂等方法默认不重试');
    }

    public function testRetryOverrideAllowsPostRetry(): void
    {
        $server = $this->server(['status' => [503, 200]]);
        $response = $this->client(['max_attempts' => 3])
            ->request(new OutboundRequest('POST', $this->url($server), [], 'payload', null, true));

        self::assertSame(200, $response->status);
        self::assertSame(2, $server->requestCount());
    }

    public function testRetryFalseDisablesGetRetry(): void
    {
        $server = $this->server(['status' => [503, 200]]);
        $response = $this->client(['max_attempts' => 3])
            ->request(new OutboundRequest('GET', $this->url($server), [], null, null, false));

        self::assertSame(503, $response->status);
        self::assertSame(1, $server->requestCount());
    }

    public function testAttemptsExhaustedThrows(): void
    {
        $server = $this->server(['close_empty' => 10]);
        try {
            $this->client(['max_attempts' => 3])->request(new OutboundRequest('GET', $this->url($server)));
            self::fail('传输错误耗尽重试后未抛异常');
        } catch (OutboundException $error) {
            self::assertSame('transport', $error->kind());
        }
        self::assertSame(3, $server->requestCount());
    }

    public function testHostnamePinnedThroughWhitelist(): void
    {
        $server = $this->server(['body' => 'via-localhost']);
        // localhost 解析到 127.0.0.1/::1 属非公网，须白名单放行；
        // 同时验证 CURLOPT_RESOLVE 钉住路径（主机名而非 IP 字面量）
        $client = new CurlOutboundClient([
            'connect_timeout_ms' => 1000,
            'read_timeout_ms' => 5000,
            'total_timeout_ms' => 10000,
            'max_attempts' => 1,
            'allow_private_hosts' => ['localhost'],
        ]);
        $response = $client->request(new OutboundRequest('GET', 'http://localhost:' . $server->port . '/'));

        self::assertSame(200, $response->status);
        self::assertSame('via-localhost', $response->body);
    }

    public function testProxyAddressRoutesThroughProxy(): void
    {
        // 假代理收到 absolute-form 请求行即证明 CURLOPT_PROXY 生效；
        // 目标服务器应零请求（流量全部进代理）
        $proxy = $this->server();
        $target = $this->server(['body' => 'direct']);
        $client = $this->client(['proxy' => 'http://127.0.0.1:' . $proxy->port]);
        $response = $client->request(new OutboundRequest('GET', $this->url($target, '/via-proxy')));

        self::assertSame(200, $response->status);
        $requests = $proxy->requests();
        self::assertCount(1, $requests);
        self::assertStringContainsString(
            'GET http://127.0.0.1:' . $target->port . '/via-proxy HTTP/1.1',
            $requests[0]['head'],
        );
        self::assertSame(0, $target->requestCount());
    }

    public function testEmptyProxyDisablesEnvironmentProxy(): void
    {
        // 环境变量代理指向死端口：proxy='' 必须显式禁用，否则回环探测等
        // 场景会被代理转发出去（SSRF/语义回归）
        putenv('http_proxy=http://127.0.0.1:9');
        putenv('no_proxy=');
        try {
            $server = $this->server();
            $response = $this->client(['proxy' => ''])
                ->request(new OutboundRequest('GET', $this->url($server)));

            self::assertSame(200, $response->status);
            self::assertSame(1, $server->requestCount());

            // 对照：不设 proxy 时 curl 读环境变量代理，死代理应产生传输错误
            try {
                $this->client()->request(new OutboundRequest('GET', $this->url($server)));
                self::fail('环境变量代理未按 curl 默认生效');
            } catch (OutboundException $error) {
                self::assertSame('transport', $error->kind());
            }
        } finally {
            putenv('http_proxy');
            putenv('no_proxy');
        }
    }
}
