<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Crypto;

/**
 * 密钥材料或密文错误。属内部故障，不映射契约错误码；
 * 文案不带密钥、密文内容，凭证类失败不向外暴露细节。
 */
final class CryptoException extends \RuntimeException
{
    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** env 缺 SAND_CRYPTO_KEY 或条目为空。 */
    public static function missingMasterKey(): self
    {
        return new self('未配置加密主密钥');
    }

    /** 条目格式错、base64 不合法、解码后不是 32 字节或 keyver 重复。 */
    public static function invalidMasterKey(string $reason): self
    {
        return new self('加密主密钥配置无效：' . $reason);
    }

    /** wrapKey 收到空数据密钥。 */
    public static function emptySecret(): self
    {
        return new self('数据密钥不能为空');
    }

    /** 密文首段的 keyver 在当前与旧密钥列表里都没有。 */
    public static function unknownKeyVersion(): self
    {
        return new self('密文密钥版本无法识别');
    }

    public static function encryptFailed(?\Throwable $previous = null): self
    {
        return new self('加密失败', $previous);
    }

    /** 密文格式错或 GCM 校验失败（被篡改、密钥不对、AAD 不符），不细分原因。 */
    public static function decryptFailed(): self
    {
        return new self('密文无法解密');
    }
}
