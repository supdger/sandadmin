<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Sandadmin\Contract\KernelApi\KernelApiVersion;
use Sandadmin\Infra\Mcp\McpGate;

/**
 * 回环挑战的签发与应答。挑战值是 32 字节随机数，写进运行目录下的
 * health-probe.json，只有实例自己的 HTTP 服务能读到它；应答端只认
 * 回环来源 + 未过期 + token 逐字节一致，其余一律不给内容。
 * 语义承接旧 sand-package SystemUpdateProbeController。
 */
final class UpdateChallenge
{
    public const FILE = 'health-probe.json';

    /** 挑战有效期秒数：沿用旧实现 150 秒，覆盖探测往返与进程切换间隙。 */
    public const TTL = 150;

    public function __construct(private readonly string $file)
    {
    }

    /** 生产入口：挑战文件固定放 server/runtime/system-update/ 下。 */
    public static function forRuntime(): self
    {
        $runtime = function_exists('runtime_path')
            ? runtime_path()
            : dirname(__DIR__, 3) . '/runtime';

        return new self($runtime . '/system-update/' . self::FILE);
    }

    /** @return string 写成的挑战值；目录不可写时抛异常，由调用方记明细 */
    public function issue(): string
    {
        $token = bin2hex(random_bytes(32));
        $dir = dirname($this->file);
        // 同名文件占位时先拒掉，免得 mkdir 白打一条警告
        if (!is_dir($dir) && (file_exists($dir) || (!mkdir($dir, 0755, true) && !is_dir($dir)))) {
            throw new \RuntimeException('无法创建探测目录 ' . $dir);
        }
        $body = json_encode(['token' => $token, 'expires_at' => time() + self::TTL], JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || file_put_contents($this->file, $body, LOCK_EX) === false) {
            throw new \RuntimeException('无法写入探测挑战文件 ' . $this->file);
        }

        return $token;
    }

    /**
     * 应答端校验：回环来源 + 挑战未过期 + token 一致才回显。
     *
     * @return array{token: string, kernel_api: string}|null
     */
    public function answer(string $remoteIp, ?string $token): ?array
    {
        if (!McpGate::isLoopback($remoteIp) || $token === null || $token === '' || !is_file($this->file)) {
            return null;
        }
        $raw = file_get_contents($this->file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !is_int($data['expires_at'] ?? null) || $data['expires_at'] < time()) {
            return null;
        }
        $expected = $data['token'] ?? null;
        if (!is_string($expected) || !hash_equals($expected, $token)) {
            return null;
        }

        return ['token' => $expected, 'kernel_api' => KernelApiVersion::CURRENT];
    }

    public function clear(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }
}
