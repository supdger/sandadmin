<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Crypto;

use Sandadmin\Domain\Crypto\KeyProviderInterface;

/**
 * 字段级加密服务（N5）：AES-256-GCM，密文 `v<keyver>:<nonce>:<ct>:<tag>`。
 * 新写入用当前 keyver；解密按密文首段 keyver 取主密钥，
 * 旧版本密文靠 SAND_CRYPTO_PREVIOUS_KEYS 轮换解密。
 */
final class Crypto
{
    public function __construct(private readonly KeyProviderInterface $provider)
    {
    }

    public function encrypt(string $plaintext): string
    {
        $version = $this->provider->currentKeyVersion();
        return GcmCipher::seal($this->provider->masterKey($version), $version, $plaintext, '');
    }

    /** @throws \Sandadmin\Domain\Crypto\CryptoException 格式错、keyver 未知或校验失败 */
    public function decrypt(string $ciphertext): string
    {
        [$ver, $nonce, $ct, $tag] = GcmCipher::parse($ciphertext);
        return GcmCipher::open($this->provider->masterKey($ver), $nonce, $ct, $tag, '');
    }
}
