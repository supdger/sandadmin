<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Crypto;

use Sandadmin\Domain\Crypto\CryptoException;

/**
 * AES-256-GCM 信封编解码（N5）。密文形如 `v1:<nonce>:<ct>:<tag>`，
 * 首段是密钥版本号，后三段二进制各按 base64 编码；aad 参与校验不进密文。
 *
 * @internal 只供 Crypto 与 EnvKeyProvider 用，业务侧不要直接调。
 */
final class GcmCipher
{
    public const KEY_BYTES = 32;
    public const NONCE_BYTES = 12;
    public const TAG_BYTES = 16;

    private const KEYVER_PATTERN = '/\Av[0-9A-Za-z._-]{1,15}\z/';
    private const CIPHER = 'aes-256-gcm';

    private function __construct()
    {
    }

    /**
     * 拆出 [keyver, nonce, ct, tag]，后三段已解码成二进制。
     * 段数不对、keyver 或 base64 不合法、nonce/tag 长度不符一律 decryptFailed。
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    public static function parse(string $ciphertext): array
    {
        $parts = explode(':', $ciphertext);
        if (count($parts) !== 4 || preg_match(self::KEYVER_PATTERN, $parts[0]) !== 1) {
            throw CryptoException::decryptFailed();
        }
        $nonce = base64_decode($parts[1], true);
        $ct = base64_decode($parts[2], true);
        $tag = base64_decode($parts[3], true);
        if ($nonce === false || $ct === false || $tag === false
            || strlen($nonce) !== self::NONCE_BYTES
            || strlen($tag) !== self::TAG_BYTES
        ) {
            throw CryptoException::decryptFailed();
        }
        return [$parts[0], $nonce, $ct, $tag];
    }

    /** 生成随机 nonce 加密；keyver 只写进密文首段、不参与派生。 */
    public static function seal(string $key, string $keyVersion, string $plaintext, string $aad): string
    {
        if (strlen($key) !== self::KEY_BYTES) {
            throw CryptoException::invalidMasterKey('主密钥必须是 32 字节');
        }
        if (preg_match(self::KEYVER_PATTERN, $keyVersion) !== 1) {
            throw CryptoException::invalidMasterKey('keyver 须形如 v1');
        }
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ct = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, self::TAG_BYTES);
        if ($ct === false || strlen($tag) !== self::TAG_BYTES) {
            throw CryptoException::encryptFailed();
        }
        return $keyVersion . ':' . base64_encode($nonce) . ':' . base64_encode($ct) . ':' . base64_encode($tag);
    }

    /** GCM 校验失败（篡改、密钥或 aad 不符）统一抛 decryptFailed，不细分原因。 */
    public static function open(string $key, string $nonce, string $ct, string $tag, string $aad): string
    {
        $plain = openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plain === false) {
            throw CryptoException::decryptFailed();
        }
        return $plain;
    }
}
