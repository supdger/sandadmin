<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\PackageSignature;
use Sandadmin\Tests\Support\FileKit;

/**
 * 验签器单测：密钥运行时生成、只落测试临时目录。
 * 覆盖原样字节与 base64 两种输入、坏签名/坏公钥、缺签名与缺公钥分支。
 */
final class PackageSignatureTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = FileKit::scratchRoot();
        if (!mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            self::fail('无法创建测试临时目录');
        }
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            FileKit::removeTree($this->dir);
            $this->dir = '';
        }
    }

    public function testVerifyAcceptsRawAndBase64Inputs(): void
    {
        [$file, $signature, $pubkey] = $this->signedContent();

        self::assertTrue(PackageSignature::verifyFile($file, $signature, $pubkey));
        self::assertTrue(PackageSignature::verifyFile($file, base64_encode($signature), base64_encode($pubkey)));
        self::assertTrue(PackageSignature::verifyFile($file, base64_encode($signature) . "\n", base64_encode($pubkey) . "\n"));
    }

    public function testVerifyRejectsTamperedContent(): void
    {
        [$file, $signature, $pubkey] = $this->signedContent();
        file_put_contents($file, 'tampered-bytes');

        self::assertFalse(PackageSignature::verifyFile($file, $signature, $pubkey));
    }

    public function testVerifyRejectsBadInputs(): void
    {
        [$file, $signature, $pubkey] = $this->signedContent();
        $other = sodium_crypto_sign_keypair();

        self::assertFalse(PackageSignature::verifyFile($file, 'not-a-signature', $pubkey));
        self::assertFalse(PackageSignature::verifyFile($file, $signature, '!!bad-key!!'));
        self::assertFalse(PackageSignature::verifyFile($file, $signature, base64_encode(str_repeat('k', 16))));
        self::assertFalse(PackageSignature::verifyFile($file, $signature, sodium_crypto_sign_publickey($other)));
        self::assertFalse(PackageSignature::verifyFile($this->dir . '/missing.zip', $signature, $pubkey));
    }

    public function testCheckPackageVerifiesSignedPackage(): void
    {
        [$file, $signature, $pubkey] = $this->signedContent();
        $signatureFile = $this->dir . '/pkg.sig';
        $pubkeyFile = $this->dir . '/pkg.pub';
        file_put_contents($signatureFile, base64_encode($signature) . "\n");
        file_put_contents($pubkeyFile, base64_encode($pubkey) . "\n");

        $result = PackageSignature::checkPackage($file, $signatureFile, $pubkeyFile, false);

        self::assertTrue($result['verified']);
        self::assertSame([], $result['problems']);
    }

    public function testCheckPackageRejectsMissingSignatureUnlessAllowed(): void
    {
        [$file] = $this->signedContent();

        $denied = PackageSignature::checkPackage($file, null, null, false);
        self::assertFalse($denied['verified']);
        self::assertNotEmpty($denied['problems']);

        $allowed = PackageSignature::checkPackage($file, null, null, true);
        self::assertFalse($allowed['verified']);
        self::assertSame([], $allowed['problems']);
    }

    public function testCheckPackageRejectsSignatureWithoutPubkey(): void
    {
        [$file, $signature] = $this->signedContent();
        $signatureFile = $this->dir . '/pkg.sig';
        file_put_contents($signatureFile, base64_encode($signature));

        $result = PackageSignature::checkPackage($file, $signatureFile, null, false);

        self::assertFalse($result['verified']);
        self::assertNotEmpty($result['problems']);
    }

    public function testCheckPackageRejectsUnreadableFiles(): void
    {
        [$file, $signature, $pubkey] = $this->signedContent();
        $signatureFile = $this->dir . '/pkg.sig';
        $pubkeyFile = $this->dir . '/pkg.pub';
        file_put_contents($signatureFile, base64_encode($signature));
        file_put_contents($pubkeyFile, base64_encode($pubkey));

        $missingSig = PackageSignature::checkPackage($file, $this->dir . '/missing.sig', $pubkeyFile, false);
        self::assertFalse($missingSig['verified']);
        self::assertNotEmpty($missingSig['problems']);

        $missingKey = PackageSignature::checkPackage($file, $signatureFile, $this->dir . '/missing.pub', false);
        self::assertFalse($missingKey['verified']);
        self::assertNotEmpty($missingKey['problems']);
    }

    public function testAllowUnsignedDoesNotRescueBadSignature(): void
    {
        [$file, $signature, $pubkey] = $this->signedContent();
        $signatureFile = $this->dir . '/pkg.sig';
        $pubkeyFile = $this->dir . '/pkg.pub';
        file_put_contents($signatureFile, base64_encode($signature));
        file_put_contents($pubkeyFile, base64_encode($pubkey));
        file_put_contents($file, 'tampered-bytes');

        $result = PackageSignature::checkPackage($file, $signatureFile, $pubkeyFile, true);

        self::assertFalse($result['verified']);
        self::assertNotEmpty($result['problems']);
    }

    /**
     * 造一份内容并用一次性 Ed25519 密钥签名，返回文件路径、原样签名、原样公钥。
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function signedContent(): array
    {
        $file = $this->dir . '/payload-' . bin2hex(random_bytes(4)) . '.zip';
        file_put_contents($file, 'zip-bytes-' . bin2hex(random_bytes(8)));
        $bytes = file_get_contents($file);
        self::assertIsString($bytes);
        $pair = sodium_crypto_sign_keypair();

        return [
            $file,
            sodium_crypto_sign_detached($bytes, sodium_crypto_sign_secretkey($pair)),
            sodium_crypto_sign_publickey($pair),
        ];
    }
}
