<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Crypto;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Crypto\CryptoException;
use Sandadmin\Infra\Crypto\EnvKeyProvider;
use Sandadmin\Infra\Crypto\GcmCipher;

final class EnvKeyProviderTest extends TestCase
{
    private static function keyEntry(string $ver): string
    {
        return $ver . '=' . base64_encode(random_bytes(GcmCipher::KEY_BYTES));
    }

    public function testFromSpecPicksCurrentVersionAndExposesAllKeys(): void
    {
        $provider = EnvKeyProvider::fromSpec(self::keyEntry('v2'), self::keyEntry('v1'));

        self::assertSame('v2', $provider->currentKeyVersion());
        self::assertSame(32, strlen($provider->masterKey('v1')));
        self::assertSame(32, strlen($provider->masterKey('v2')));
        self::assertSame($provider->masterKey('v1'), $provider->masterKey('v1'));
    }

    public function testFromSpecRejectsMissingCurrentKey(): void
    {
        $this->expectException(CryptoException::class);
        EnvKeyProvider::fromSpec('', self::keyEntry('v1'));
    }

    public function testFromSpecRejectsKeyWithWrongLength(): void
    {
        $this->expectException(CryptoException::class);
        EnvKeyProvider::fromSpec('v1=' . base64_encode(random_bytes(16)), '');
    }

    public function testFromSpecRejectsMalformedEntry(): void
    {
        $this->expectException(CryptoException::class);
        EnvKeyProvider::fromSpec('not-an-entry', '');
    }

    public function testFromSpecRejectsDuplicateKeyVersion(): void
    {
        $this->expectException(CryptoException::class);
        EnvKeyProvider::fromSpec(self::keyEntry('v1'), self::keyEntry('v1'));
    }

    public function testMasterKeyRejectsUnknownVersion(): void
    {
        $provider = EnvKeyProvider::fromSpec(self::keyEntry('v1'), '');
        $this->expectException(CryptoException::class);
        $provider->masterKey('v9');
    }

    public function testWrapUnwrapRoundTrip(): void
    {
        $provider = EnvKeyProvider::fromSpec(self::keyEntry('v1'), '');
        $secret = random_bytes(32);

        $wrapped = $provider->wrapKey($secret);
        self::assertStringStartsWith('v1:', $wrapped);
        self::assertSame($secret, $provider->unwrapKey($wrapped));
    }

    public function testUnwrapRejectsDifferentOrg(): void
    {
        $provider = EnvKeyProvider::fromSpec(self::keyEntry('v1'), '');
        $wrapped = $provider->wrapKey(random_bytes(32), 'org-a');

        $this->expectException(CryptoException::class);
        $provider->unwrapKey($wrapped, 'org-b');
    }

    public function testUnwrapRejectsTamperedWrappedKey(): void
    {
        $provider = EnvKeyProvider::fromSpec(self::keyEntry('v1'), '');
        $wrapped = $provider->wrapKey(random_bytes(32));
        $tampered = substr($wrapped, 0, -2) . 'AA';

        $this->expectException(CryptoException::class);
        $provider->unwrapKey($tampered);
    }

    public function testWrappedKeyStaysReadableAfterMasterRotation(): void
    {
        $old = EnvKeyProvider::fromSpec(self::keyEntry('v1'), '');
        $oldEntry = 'v1=' . base64_encode($old->masterKey('v1'));
        $wrapped = $old->wrapKey(random_bytes(32));

        $rotated = EnvKeyProvider::fromSpec(self::keyEntry('v2'), $oldEntry);
        self::assertSame(32, strlen($rotated->unwrapKey($wrapped)));
    }

    public function testWrapRejectsEmptySecret(): void
    {
        $provider = EnvKeyProvider::fromSpec(self::keyEntry('v1'), '');
        $this->expectException(CryptoException::class);
        $provider->wrapKey('');
    }
}
