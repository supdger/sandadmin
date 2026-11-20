<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Crypto;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Crypto\CryptoException;
use Sandadmin\Infra\Crypto\Crypto;
use Sandadmin\Infra\Crypto\EnvKeyProvider;
use Sandadmin\Infra\Crypto\GcmCipher;

final class CryptoTest extends TestCase
{
    private static function keyEntry(string $ver): string
    {
        return $ver . '=' . base64_encode(random_bytes(GcmCipher::KEY_BYTES));
    }

    private static function crypto(string $current = 'v1', string $previous = ''): Crypto
    {
        return new Crypto(EnvKeyProvider::fromSpec(self::keyEntry($current), $previous));
    }

    public function testEncryptProducesVersionedEnvelopeFormat(): void
    {
        $crypto = self::crypto();
        $parts = explode(':', $crypto->encrypt('smtp-secret'));

        self::assertCount(4, $parts);
        self::assertSame('v1', $parts[0]);
        $nonce = base64_decode($parts[1], true);
        $ct = base64_decode($parts[2], true);
        $tag = base64_decode($parts[3], true);
        self::assertNotFalse($nonce);
        self::assertNotFalse($ct);
        self::assertNotFalse($tag);
        self::assertSame(GcmCipher::NONCE_BYTES, strlen($nonce));
        self::assertSame(GcmCipher::TAG_BYTES, strlen($tag));
        self::assertSame('smtp-secret', $crypto->decrypt(implode(':', $parts)));
    }

    public function testDecryptRoundTripsPlaintext(): void
    {
        $crypto = self::crypto();
        foreach (['', 'a', '中文密码 ✓', random_bytes(64), str_repeat('x', 4096)] as $plain) {
            self::assertSame($plain, $crypto->decrypt($crypto->encrypt($plain)));
        }
    }

    public function testDecryptRejectsTamperedTag(): void
    {
        $crypto = self::crypto();
        $parts = explode(':', $crypto->encrypt('secret'));
        $tag = base64_decode($parts[3], true);
        self::assertNotFalse($tag);
        $tag[0] = $tag[0] === 'A' ? 'B' : 'A';
        $parts[3] = base64_encode($tag);

        $this->expectException(CryptoException::class);
        $crypto->decrypt(implode(':', $parts));
    }

    public function testDecryptRejectsTamperedCiphertext(): void
    {
        $crypto = self::crypto();
        $parts = explode(':', $crypto->encrypt('secret'));
        $ct = base64_decode($parts[2], true);
        self::assertNotFalse($ct);
        $ct[0] = $ct[0] === "\x00" ? "\x01" : "\x00";
        $parts[2] = base64_encode($ct);

        $this->expectException(CryptoException::class);
        $crypto->decrypt(implode(':', $parts));
    }

    public function testDecryptRejectsTamperedNonce(): void
    {
        $crypto = self::crypto();
        $parts = explode(':', $crypto->encrypt('secret'));
        $parts[1] = base64_encode(random_bytes(GcmCipher::NONCE_BYTES));

        $this->expectException(CryptoException::class);
        $crypto->decrypt(implode(':', $parts));
    }

    public function testDecryptRejectsMalformedInput(): void
    {
        $crypto = self::crypto();
        $malformedInputs = [
            '',
            'v1',
            'v1:xx:yy',
            'v1:!!:!!:!!',
            'x1:' . base64_encode('a') . ':' . base64_encode('b') . ':' . base64_encode('c'),
            'v1:' . base64_encode(random_bytes(8)) . ':' . base64_encode('b') . ':' . base64_encode(random_bytes(16)),
        ];
        foreach ($malformedInputs as $bad) {
            try {
                $crypto->decrypt($bad);
                self::fail('未拒绝非法密文：' . $bad);
            } catch (CryptoException) {
            }
        }
        $this->addToAssertionCount(1);
    }

    public function testDecryptRejectsUnknownKeyVersion(): void
    {
        $crypto = self::crypto('v1');
        $other = self::crypto('v7');

        $this->expectException(CryptoException::class);
        $crypto->decrypt($other->encrypt('secret'));
    }

    public function testOldKeyVersionStillDecryptsAfterRotation(): void
    {
        $oldEntry = self::keyEntry('v1');
        $before = new Crypto(EnvKeyProvider::fromSpec($oldEntry, ''));
        $ciphertext = $before->encrypt('legacy-secret');

        $after = new Crypto(EnvKeyProvider::fromSpec(self::keyEntry('v2'), $oldEntry));
        self::assertSame('legacy-secret', $after->decrypt($ciphertext));
    }

    public function testNewWritesUseCurrentKeyVersion(): void
    {
        $crypto = self::crypto('v3');
        self::assertStringStartsWith('v3:', $crypto->encrypt('secret'));
    }

    public function testEncryptUsesRandomNonce(): void
    {
        $crypto = self::crypto();
        self::assertNotSame($crypto->encrypt('same'), $crypto->encrypt('same'));
    }
}
