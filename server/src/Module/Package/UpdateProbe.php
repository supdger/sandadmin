<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Sandadmin\Contract\KernelApi\KernelApiVersion;

/**
 * 系统更新探测（离线回环挑战）：签发随机挑战 → 经自身 HTTP 服务的
 * 回环应答取回 → 逐字节比对。全程只打本机回环，不访问任何外部更新源；
 * 语义止于「实例能否经 HTTP 正确应答本构建」，不做更新执行。
 * 承接旧 sand-package SystemUpdateProbe（system-update-worker 的
 * health-probe 阶段）：挑战文件形状、应答端口径与超时均沿用旧实现。
 */
final class UpdateProbe
{
    public const PATH = '/api/admin/v1/system/update/probe';

    /** 挑战值请求头：与旧 sand-package 协议同名（x-sand-update-probe）。 */
    public const TOKEN_HEADER = 'x-sand-update-probe';

    public function __construct(
        private readonly UpdateChallenge $challenge,
        private readonly ProbeTransport $transport,
        private readonly string $listen,
    ) {
    }

    /** 生产入口：出站固定 cURL 回环实现，监听地址读框架 process 配置。 */
    public static function fromRuntime(?ProbeTransport $transport = null): self
    {
        $listen = function_exists('config') ? config('process.webman.listen') : null;

        return new self(
            UpdateChallenge::forRuntime(),
            $transport ?? new CurlProbeTransport(),
            is_string($listen) ? $listen : '',
        );
    }

    /**
     * 跑一次完整探测。checks 按 target → challenge → loopback → echo
     * 顺序记明细，前一关不过后面直接不跑；挑战文件用完即删。
     *
     * @return array{ok: bool, checked_time: string, checks: list<array{name: string, ok: bool, detail: string}>}
     */
    public function run(): array
    {
        $checks = [];
        $base = self::loopbackBase($this->listen);
        if ($base === null) {
            $checks[] = self::check('target', false, '监听地址不是本机回环（' . ($this->listen === '' ? '未配置' : $this->listen) . '），不发起请求');

            return self::report(false, $checks);
        }
        $checks[] = self::check('target', true, '探测目标 ' . $base . self::PATH);
        try {
            $token = $this->challenge->issue();
        } catch (\Throwable $error) {
            $checks[] = self::check('challenge', false, $error->getMessage());

            return self::report(false, $checks);
        }
        $checks[] = self::check('challenge', true, '随机挑战已写入运行目录');
        try {
            try {
                $reply = $this->transport->get($base . self::PATH, [self::TOKEN_HEADER => $token]);
            } catch (\Throwable $error) {
                $checks[] = self::check('loopback', false, $error->getMessage());

                return self::report(false, $checks);
            }
            if ($reply['status'] !== 200) {
                $checks[] = self::check('loopback', false, '回环应答 HTTP ' . $reply['status']);

                return self::report(false, $checks);
            }
            $data = json_decode($reply['body'], true);
            if (!is_array($data)) {
                $checks[] = self::check('loopback', false, '回环应答不是 JSON');

                return self::report(false, $checks);
            }
            $checks[] = self::check('loopback', true, '回环应答 HTTP 200');
            $echo = $data['token'] ?? null;
            if (!is_string($echo) || !hash_equals($token, $echo)) {
                $checks[] = self::check('echo', false, '应答挑战与本机签发不一致');

                return self::report(false, $checks);
            }
            $kernelApi = $data['kernel_api'] ?? null;
            if ($kernelApi !== KernelApiVersion::CURRENT) {
                $checks[] = self::check('echo', false, '应答构建与本构建不一致：' . (is_string($kernelApi) ? $kernelApi : '缺失'));

                return self::report(false, $checks);
            }
            $checks[] = self::check('echo', true, '应答挑战一致，运行构建 kernel_api ' . KernelApiVersion::CURRENT);

            return self::report(true, $checks);
        } finally {
            $this->challenge->clear();
        }
    }

    /**
     * 监听地址 → 回环基址：只认 http/https + 本机通配或回环地址，
     * 域名与外部地址一律返回 null，调用方不得再发起请求。
     */
    public static function loopbackBase(string $listen): ?string
    {
        $parts = parse_url($listen);
        if (!is_array($parts) || !is_string($parts['scheme'] ?? null) || !is_string($parts['host'] ?? null)) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        // parse_url 给出的端口只在 1–65535 内；缺省端口不可用，探测需要显式监听端口
        $port = $parts['port'] ?? null;
        if (!is_int($port)) {
            return null;
        }
        $host = strtolower($parts['host']);
        $target = null;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && (str_starts_with($host, '127.') || $host === '0.0.0.0')) {
            $target = '127.0.0.1';
        } elseif (in_array($host, ['localhost', '::', '[::]', '::1', '[::1]'], true)) {
            // IPv6 通配与 v6 回环统一走 [::1]；localhost 交给下面 v4 分支
            $target = $host === 'localhost' ? '127.0.0.1' : '[::1]';
        }
        if ($target === null) {
            return null;
        }

        return $scheme . '://' . $target . ':' . $port;
    }

    /** @return array{name: string, ok: bool, detail: string} */
    private static function check(string $name, bool $ok, string $detail): array
    {
        return ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    }

    /**
     * @param list<array{name: string, ok: bool, detail: string}> $checks
     *
     * @return array{ok: bool, checked_time: string, checks: list<array{name: string, ok: bool, detail: string}>}
     */
    private static function report(bool $ok, array $checks): array
    {
        return ['ok' => $ok, 'checked_time' => date('c'), 'checks' => $checks];
    }
}
