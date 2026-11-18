<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\KernelApi\KernelApiVersion;
use Sandadmin\Module\Package\CurlProbeTransport;
use Sandadmin\Module\Package\ProbeTransport;
use Sandadmin\Module\Package\UpdateChallenge;
use Sandadmin\Module\Package\UpdateProbe;
use Sandadmin\Tests\Support\FileKit;
use Sandadmin\Tests\Support\RecordingProbeTransport;

/**
 * 系统更新离线回环探测（无库）：临时目录当运行目录，替身传输层
 * 模拟应答端。替身与真实应答端走同一个 UpdateChallenge::answer，
 * 成功路径的回环语义与线上一致；非本机目标断言一律不发请求。
 */
final class UpdateProbeTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = FileKit::scratchRoot();
        if (!mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            self::fail('无法创建临时运行目录 ' . $this->dir);
        }
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            FileKit::removeTree($this->dir);
            $this->dir = '';
        }
    }

    public function testRunSucceedsWithLoopbackEcho(): void
    {
        $file = $this->challengeFile();
        $report = $this->probe($this->loopbackServer($file))->run();

        self::assertTrue($report['ok'], $this->details($report));
        self::assertSame(['target', 'challenge', 'loopback', 'echo'], array_column($report['checks'], 'name'));
        foreach ($report['checks'] as $check) {
            self::assertTrue($check['ok'], $check['detail']);
        }
        self::assertNotSame('', $report['checked_time']);
        // 挑战文件用后必须清掉，不能留在运行目录里
        self::assertFileDoesNotExist($file);
    }

    public function testRunFailsWhenEchoTokenDiffers(): void
    {
        $file = $this->challengeFile();
        $transport = new RecordingProbeTransport(200, (string) json_encode(['token' => str_repeat('0', 64), 'kernel_api' => '1.0.0']));

        $report = $this->probe($transport)->run();

        self::assertFalse($report['ok']);
        self::assertFalse($this->checkOf($report, 'echo')['ok']);
        self::assertFileDoesNotExist($file);
    }

    public function testRunFailsWhenBuildDiffers(): void
    {
        $file = $this->challengeFile();
        $transport = new class ($file) implements ProbeTransport {
            public function __construct(private readonly string $file)
            {
            }

            public function get(string $url, array $headers): array
            {
                $payload = (new UpdateChallenge($this->file))->answer('127.0.0.1', $headers[UpdateProbe::TOKEN_HEADER] ?? null);
                if ($payload === null) {
                    return ['status' => 404, 'body' => 'Not Found'];
                }
                $payload['kernel_api'] = '0.9.9';

                return ['status' => 200, 'body' => (string) json_encode($payload)];
            }
        };

        $report = $this->probe($transport)->run();

        self::assertFalse($report['ok']);
        $echo = $this->checkOf($report, 'echo');
        self::assertFalse($echo['ok']);
        self::assertStringContainsString('0.9.9', $echo['detail']);
    }

    public function testRunFailsOnHttpError(): void
    {
        $report = $this->probe(new RecordingProbeTransport(503, 'down'))->run();

        self::assertFalse($report['ok']);
        $loopback = $this->checkOf($report, 'loopback');
        self::assertFalse($loopback['ok']);
        self::assertStringContainsString('503', $loopback['detail']);
    }

    public function testRunFailsOnNonJsonReply(): void
    {
        $report = $this->probe(new RecordingProbeTransport(200, '<html>not json</html>'))->run();

        self::assertFalse($report['ok']);
        self::assertFalse($this->checkOf($report, 'loopback')['ok']);
    }

    public function testRunFailsOnTransportError(): void
    {
        $transport = new RecordingProbeTransport(error: new \RuntimeException('回环请求失败：连接超时'));

        $report = $this->probe($transport)->run();

        self::assertFalse($report['ok']);
        self::assertFalse($this->checkOf($report, 'loopback')['ok']);
        self::assertFileDoesNotExist($this->challengeFile());
    }

    public function testRunFailsWhenChallengeUnwritable(): void
    {
        $blocked = $this->dir . '/blocked';
        file_put_contents($blocked, 'x');
        $probe = new UpdateProbe(new UpdateChallenge($blocked . '/probe.json'), new RecordingProbeTransport(), 'http://127.0.0.1:8787');

        $report = $probe->run();

        self::assertFalse($report['ok']);
        self::assertFalse($this->checkOf($report, 'challenge')['ok']);
    }

    public function testRunRejectsNonLoopbackListen(): void
    {
        $transport = new RecordingProbeTransport();
        $probe = new UpdateProbe(new UpdateChallenge($this->challengeFile()), $transport, 'http://203.0.113.9:8787');

        $report = $probe->run();

        self::assertFalse($report['ok']);
        self::assertFalse($this->checkOf($report, 'target')['ok']);
        // 非本机目标绝不出网：传输层一次都不能被调
        self::assertSame(0, $transport->calls());
        self::assertFileDoesNotExist($this->challengeFile());
    }

    public function testLoopbackBaseMapping(): void
    {
        self::assertSame('http://127.0.0.1:8787', UpdateProbe::loopbackBase('http://0.0.0.0:8787'));
        self::assertSame('http://127.0.0.1:8787', UpdateProbe::loopbackBase('http://127.0.0.1:8787'));
        self::assertSame('http://127.0.0.1:8787', UpdateProbe::loopbackBase('http://localhost:8787'));
        self::assertSame('http://[::1]:8787', UpdateProbe::loopbackBase('http://[::]:8787'));
        self::assertSame('https://[::1]:8787', UpdateProbe::loopbackBase('https://[::1]:8787'));
        self::assertNull(UpdateProbe::loopbackBase('http://example.com:8787'));
        self::assertNull(UpdateProbe::loopbackBase('http://203.0.113.9:8787'));
        self::assertNull(UpdateProbe::loopbackBase('unix:///tmp/sock'));
        self::assertNull(UpdateProbe::loopbackBase('http://0.0.0.0:99999'));
        self::assertNull(UpdateProbe::loopbackBase(''));
    }

    public function testAnswerRejectsForeignIp(): void
    {
        $challenge = new UpdateChallenge($this->challengeFile());
        $token = $challenge->issue();

        self::assertNull($challenge->answer('203.0.113.9', $token));
        $challenge->clear();
    }

    public function testAnswerRejectsExpiredChallenge(): void
    {
        $file = $this->challengeFile();
        $challenge = new UpdateChallenge($file);
        $token = $challenge->issue();
        file_put_contents($file, (string) json_encode(['token' => $token, 'expires_at' => time() - 1]));

        self::assertNull($challenge->answer('127.0.0.1', $token));
        $challenge->clear();
    }

    public function testAnswerRejectsWrongToken(): void
    {
        $challenge = new UpdateChallenge($this->challengeFile());
        $challenge->issue();

        self::assertNull($challenge->answer('127.0.0.1', str_repeat('0', 64)));
        self::assertNull($challenge->answer('127.0.0.1', null));
        $challenge->clear();
    }

    public function testAnswerEchoesTokenAndBuild(): void
    {
        $challenge = new UpdateChallenge($this->challengeFile());
        $token = $challenge->issue();

        $payload = $challenge->answer('127.0.0.1', $token);

        self::assertNotNull($payload);
        self::assertSame($token, $payload['token']);
        self::assertSame(KernelApiVersion::CURRENT, $payload['kernel_api']);
        $challenge->clear();
    }

    public function testTransportRejectsNonLoopbackTargets(): void
    {
        $transport = new CurlProbeTransport();
        foreach ([
            'http://example.com:8787/x',
            'https://github.com/releases',
            'http://169.254.169.254/latest/meta-data',
            'http://127.0.0.1.evil.com:8787/x',
            'gopher://127.0.0.1:70/x',
            'ftp://127.0.0.1/x',
        ] as $url) {
            self::assertFalse(CurlProbeTransport::isLoopbackUrl($url), $url);
            try {
                $transport->get($url, []);
                self::fail('非回环目标被放行：' . $url);
            } catch (\InvalidArgumentException) {
                // 预期拒绝
            }
        }
        self::assertTrue(CurlProbeTransport::isLoopbackUrl('http://127.0.0.1:19100/api/admin/v1/system/update/probe'));
        self::assertTrue(CurlProbeTransport::isLoopbackUrl('http://[::1]:19100/x'));
        self::assertTrue(CurlProbeTransport::isLoopbackUrl('http://localhost:19100/x'));
    }

    private function challengeFile(): string
    {
        return $this->dir . '/runtime/system-update/health-probe.json';
    }

    private function probe(ProbeTransport $transport): UpdateProbe
    {
        return new UpdateProbe(new UpdateChallenge($this->challengeFile()), $transport, 'http://127.0.0.1:8787');
    }

    /** 替身回环服务：与真实应答端走同一个 UpdateChallenge::answer。 */
    private function loopbackServer(string $challengeFile): ProbeTransport
    {
        return new class ($challengeFile) implements ProbeTransport {
            public function __construct(private readonly string $file)
            {
            }

            public function get(string $url, array $headers): array
            {
                $payload = (new UpdateChallenge($this->file))->answer('127.0.0.1', $headers[UpdateProbe::TOKEN_HEADER] ?? null);
                if ($payload === null) {
                    return ['status' => 404, 'body' => 'Not Found'];
                }

                return ['status' => 200, 'body' => (string) json_encode($payload)];
            }
        };
    }

    /** @param array{checks: list<array{name: string, ok: bool, detail: string}>} $report */
    private function details(array $report): string
    {
        return implode("\n", array_map(
            static fn (array $check): string => $check['name'] . '=' . ($check['ok'] ? 'ok' : 'fail') . ' ' . $check['detail'],
            $report['checks'],
        ));
    }

    /**
     * @param array{checks: list<array{name: string, ok: bool, detail: string}>} $report
     *
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkOf(array $report, string $name): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check;
            }
        }
        self::fail('报告里没有 ' . $name . ' 检查项');
    }
}
