<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Crypto;

/**
 * 信封加密的密钥端口（J-10/M11）：数据密钥加密数据、主密钥只包数据密钥。
 * 默认实现是 env 本地主密钥（Infra/Crypto/EnvKeyProvider），
 * 可替换为云 KMS 或 Vault Transit；替换方只须兑现本接口。
 * orgId 是「按组织的数据密钥」维度；框架单租户下恒为 DEFAULT_ORG
 * （总表第 11 条、R-7），实现把它绑定进包裹密文，跨组织不可解。
 */
interface KeyProviderInterface
{
    public const DEFAULT_ORG = 'default';

    /** 当前主密钥版本号，即新密文首段取值（形如 v1）。 */
    public function currentKeyVersion(): string;

    /**
     * 按版本号取主密钥（原始 32 字节），只供内核 Crypto 与包数据密钥用。
     *
     * @throws CryptoException 版本不存在（unknownKeyVersion）
     */
    public function masterKey(string $keyVersion): string;

    /**
     * 用当前主密钥把数据密钥包成可存储的密文（自带 keyver，orgId 作绑定）。
     *
     * @throws CryptoException 数据密钥为空（emptySecret）
     */
    public function wrapKey(string $secret, string $orgId = self::DEFAULT_ORG): string;

    /**
     * 还原 wrapKey 的输出。
     *
     * @throws CryptoException keyver 未知、被篡改或 orgId 不符
     */
    public function unwrapKey(string $wrapped, string $orgId = self::DEFAULT_ORG): string;
}
