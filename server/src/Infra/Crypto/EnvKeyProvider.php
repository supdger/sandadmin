<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Crypto;

use Sandadmin\Domain\Crypto\CryptoException;
use Sandadmin\Domain\Crypto\KeyProviderInterface;

/**
 * env 本地主密钥实现（N5/M11 默认）。`SAND_CRYPTO_KEY` 存当前主密钥、
 * `SAND_CRYPTO_PREVIOUS_KEYS` 存逗号分隔的旧条目；条目格式 `v<序号>=<base64 的 32 字节>`。
 * 密钥只从环境变量读，不落库、不进日志、不进仓库；
 * 缺失或长度不对在装配时即拒绝，替换 KMS/Vault 时换实现不动接口。
 */
final class EnvKeyProvider implements KeyProviderInterface
{
    public const CURRENT_ENV = 'SAND_CRYPTO_KEY';
    public const PREVIOUS_ENV = 'SAND_CRYPTO_PREVIOUS_KEYS';

    private const ENTRY_PATTERN = '/\A(v[0-9A-Za-z._-]{1,15})=([A-Za-z0-9+\/]+=*)\z/';

    /** @param array<string, string> $keys keyver => 原始主密钥 */
    private function __construct(
        private readonly string $version,
        private readonly array $keys,
    ) {
    }

    /** 从环境变量装配；缺 SAND_CRYPTO_KEY 或密钥材料不合法时拒绝。 */
    public static function fromEnv(): self
    {
        $current = env(self::CURRENT_ENV, '');
        $previous = env(self::PREVIOUS_ENV, '');
        return self::fromSpec(
            is_string($current) ? trim($current) : '',
            is_string($previous) ? trim($previous) : '',
        );
    }

    /** 直接给条目文本装配，测试与装配层用；不读 env。 */
    public static function fromSpec(string $current, string $previous): self
    {
        $keys = [];
        foreach (self::entries($previous) as $entry) {
            [$ver, $key] = self::parseEntry($entry);
            if (isset($keys[$ver])) {
                throw CryptoException::invalidMasterKey('keyver 重复：' . $ver);
            }
            $keys[$ver] = $key;
        }
        if ($current === '') {
            throw CryptoException::missingMasterKey();
        }
        [$ver, $key] = self::parseEntry($current);
        if (isset($keys[$ver])) {
            throw CryptoException::invalidMasterKey('keyver 重复：' . $ver);
        }
        $keys[$ver] = $key;
        return new self($ver, $keys);
    }

    public function currentKeyVersion(): string
    {
        return $this->version;
    }

    public function masterKey(string $keyVersion): string
    {
        $key = $this->keys[$keyVersion] ?? null;
        if ($key === null) {
            throw CryptoException::unknownKeyVersion();
        }
        return $key;
    }

    public function wrapKey(string $secret, string $orgId = self::DEFAULT_ORG): string
    {
        if ($secret === '') {
            throw CryptoException::emptySecret();
        }
        return GcmCipher::seal($this->keys[$this->version], $this->version, $secret, $orgId);
    }

    public function unwrapKey(string $wrapped, string $orgId = self::DEFAULT_ORG): string
    {
        [$ver, $nonce, $ct, $tag] = GcmCipher::parse($wrapped);
        return GcmCipher::open($this->masterKey($ver), $nonce, $ct, $tag, $orgId);
    }

    /** @return list<string> 非空条目列表 */
    private static function entries(string $spec): array
    {
        if ($spec === '') {
            return [];
        }
        $entries = [];
        foreach (explode(',', $spec) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /** @return array{0: string, 1: string} [keyver, 原始主密钥] */
    private static function parseEntry(string $entry): array
    {
        if (preg_match(self::ENTRY_PATTERN, $entry, $match) !== 1) {
            throw CryptoException::invalidMasterKey('条目须形如 v1=<base64>');
        }
        $key = base64_decode($match[2], true);
        if ($key === false || strlen($key) !== GcmCipher::KEY_BYTES) {
            throw CryptoException::invalidMasterKey('主密钥必须是 base64 的 32 字节');
        }
        return [$match[1], $key];
    }
}
