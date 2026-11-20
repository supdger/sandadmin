<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

/**
 * 离线包 Ed25519 分离签名验证。签名格式与信任根细则放行稿待定，
 * 当前为「分离签名 + 调用方显式提供公钥」的过渡口径：公钥不内嵌内核，
 * 由 CLI 参数传入；验签对 zip 字节整体做且先于解包，验不过一律拒绝。
 * 坏输入返回 false 或问题清单，不向外抛异常。
 */
final class PackageSignature
{
    /**
     * 装/检包共用的验签步：读 zip、签名文件与公钥文件，返回结论与问题清单。
     * verified 只在真实验签通过时为 true；allowUnsigned 只豁免「未提供签名」，
     * 提供了签名但验不过照样进问题清单。
     *
     * @return array{verified: bool, problems: list<string>}
     */
    public static function checkPackage(
        string $zipFile,
        ?string $signatureFile,
        ?string $pubkeyFile,
        bool $allowUnsigned,
    ): array {
        if ($signatureFile === null) {
            if ($allowUnsigned) {
                return ['verified' => false, 'problems' => []];
            }

            return ['verified' => false, 'problems' => ['安装包未提供签名，按验签失败拒绝']];
        }
        $signature = self::readFile($signatureFile);
        if ($signature === null) {
            return ['verified' => false, 'problems' => ['签名文件不存在或不可读：' . $signatureFile]];
        }
        if ($pubkeyFile === null) {
            return ['verified' => false, 'problems' => ['提供了签名但未提供验签公钥，无法验证，按验签失败拒绝']];
        }
        $pubkey = self::readFile($pubkeyFile);
        if ($pubkey === null) {
            return ['verified' => false, 'problems' => ['公钥文件不存在或不可读：' . $pubkeyFile]];
        }
        if (!self::verifyFile($zipFile, $signature, $pubkey)) {
            return ['verified' => false, 'problems' => ['安装包 Ed25519 验签未通过']];
        }

        return ['verified' => true, 'problems' => []];
    }

    /**
     * 对文件字节做 Ed25519 分离签名验证。签名与公钥接受原样字节或 base64
     * 文本，长度、编码、密钥任一不符都返回 false。
     */
    public static function verifyFile(string $file, string $signature, string $pubkey): bool
    {
        $content = is_file($file) ? file_get_contents($file) : false;
        if (!is_string($content)) {
            return false;
        }
        $rawSignature = self::decode($signature, SODIUM_CRYPTO_SIGN_BYTES);
        $rawKey = self::decode($pubkey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        if ($rawSignature === null || $rawSignature === '' || $rawKey === null || $rawKey === '') {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($rawSignature, $content, $rawKey);
        } catch (\SodiumException) {
            return false;
        }
    }

    /** 原样字节长度不符就按 base64 解一次；两种都对不上返回 null。 */
    private static function decode(string $value, int $length): ?string
    {
        if (strlen($value) === $length) {
            return $value;
        }
        $decoded = base64_decode(trim($value), true);

        return is_string($decoded) && strlen($decoded) === $length ? $decoded : null;
    }

    private static function readFile(string $file): ?string
    {
        $content = is_file($file) ? file_get_contents($file) : false;

        return is_string($content) ? $content : null;
    }
}
