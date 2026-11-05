<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Infra\Storage\FinfoMimeSniffer;
use Sandadmin\Infra\Storage\LocalFlysystemStore;
use Sandadmin\Storage\Exception\InvalidUploadException;
use Sandadmin\Storage\Exception\MimeNotAllowedException;
use Sandadmin\Storage\Exception\UploadTooLargeException;
use Sandadmin\Storage\FileStore;
use Sandadmin\Storage\OriginalName;
use Sandadmin\Storage\StorageConfig;
use Sandadmin\Storage\StoredFile;

final class UploadBoundsTest extends TestCase
{
    private string $root;

    private FileStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sandadmin-b6-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->store = LocalFlysystemStore::open($this->root);
        CapProbeStream::$reads = 0;
        ReadForbiddenStream::$reads = 0;
        if (!in_array('capprobe', stream_get_wrappers(), true)) {
            stream_wrapper_register('capprobe', CapProbeStream::class);
        }
        if (!in_array('readforbidden', stream_get_wrappers(), true)) {
            stream_wrapper_register('readforbidden', ReadForbiddenStream::class);
        }
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testDefaultCapIs100Megabytes(): void
    {
        self::assertSame(100 * 1024 * 1024, StorageConfig::DEFAULT_MAX_BYTES);
        $server = file_get_contents(dirname(__DIR__, 4) . '/config/server.php');
        self::assertIsString($server);
        self::assertStringContainsString('StorageConfig::DEFAULT_MAX_BYTES', $server);
        $defaults = StorageConfig::defaultAllowedMimeTypes();
        self::assertNotContains('application/octet-stream', $defaults);
        self::assertNotContains('image/svg+xml', $defaults);
    }

    public function testExactCapIsAcceptedAndOneExtraByteIsRejected(): void
    {
        $service = $this->service(8);
        $stored = $service->upload($this->memory(str_repeat('a', 8)), null, 'note.txt');
        self::assertSame(8, $stored->size);
        self::assertSame('text/plain', $stored->mime);
        self::assertSame([], $this->outsideRoot($stored));

        try {
            $service->upload($this->memory(str_repeat('a', 9)), null, 'note.txt');
            self::fail('expected the cap to reject 9 bytes');
        } catch (UploadTooLargeException $error) {
            self::assertSame('upload_too_large', $error->errorCode);
            self::assertSame(8, $error->maxBytes);
        }
        self::assertSame([$stored->id, $stored->id . '.meta.json'], $this->entries());
    }

    public function testDeclaredLengthAboveTheCapDoesNotReadTheStream(): void
    {
        $stream = fopen('readforbidden://upload', 'rb');
        self::assertIsResource($stream);
        try {
            $this->service(16)->upload($stream, 17, 'big.bin');
            self::fail('expected an early size rejection');
        } catch (UploadTooLargeException) {
            self::assertSame(0, ReadForbiddenStream::$reads);
            self::assertSame([], $this->entries());
        } finally {
            fclose($stream);
        }
    }

    public function testEmptyUploadIsRejectedWithoutAStoredObject(): void
    {
        $stream = fopen('readforbidden://upload', 'rb');
        self::assertIsResource($stream);
        try {
            $this->service(16)->upload($stream, 0, 'empty.txt');
            self::fail('expected an empty upload to be rejected');
        } catch (InvalidUploadException $error) {
            self::assertSame(0, ReadForbiddenStream::$reads);
            self::assertSame('invalid_upload', $error->errorCode);
            self::assertSame([], $this->entries());
        } finally {
            fclose($stream);
        }

        try {
            $this->service(16)->upload($this->memory(''), null, 'empty.txt');
            self::fail('expected an empty stream to be rejected');
        } catch (InvalidUploadException $error) {
            self::assertSame('invalid_upload', $error->errorCode);
        }
        self::assertSame([], $this->entries());
    }

    public function testShortDeclaredLengthDoesNotBypassTheCap(): void
    {
        try {
            $this->service(8)->upload($this->memory(str_repeat('b', 20)), 4, 'note.txt');
            self::fail('expected the reader to stop at the cap');
        } catch (UploadTooLargeException) {
            self::assertSame([], $this->entries());
        }
    }

    public function testStreamingReaderStopsAtTheCap(): void
    {
        $stream = fopen('capprobe://upload', 'rb');
        self::assertIsResource($stream);
        try {
            $this->service(100)->upload($stream, null, 'stream.txt');
            self::fail('expected the streaming reader to stop');
        } catch (UploadTooLargeException) {
            self::assertLessThanOrEqual(2, CapProbeStream::$reads);
            self::assertSame([], $this->entries());
        } finally {
            fclose($stream);
        }
    }

    public function testMimeComesFromContentAndDisallowedContentIsNotStored(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $stored = $this->service(1024, ['text/plain', 'image/png'])->upload($this->memory('hello'), null, 'report.exe');
        self::assertSame('text/plain', $stored->mime);
        self::assertSame('report.exe', $stored->originalName);

        try {
            $this->service(1024, ['text/plain'])->upload($this->memory($png), null, 'pixel.png');
            self::fail('expected the png to be rejected');
        } catch (MimeNotAllowedException $error) {
            self::assertSame('mime_not_allowed', $error->errorCode);
            self::assertSame('image/png', $error->mime);
        }
        self::assertSame([$stored->id, $stored->id . '.meta.json'], $this->entries());
        self::assertSame('hello', file_get_contents($this->root . '/' . $stored->id));
    }

    public function testOriginalNameCannotEscapeTheStorageRoot(): void
    {
        $stored = $this->service(32)->upload($this->memory('hello'), null, '../../etc/passwd');
        self::assertSame('passwd', $stored->originalName);
        $loaded = $this->store->get($stored->id);
        self::assertSame('passwd', $loaded->originalName);
        self::assertSame([$stored->id, $stored->id . '.meta.json'], $this->entries());
        $source = file_get_contents(dirname(__DIR__, 4) . '/src/Infra/Storage/LocalFlysystemStore.php');
        self::assertIsString($source);
        self::assertStringContainsString('LocalFilesystemAdapter', $source);
        self::assertStringNotContainsString('Aws\\S3', $source);
        self::assertStringNotContainsString('Aliyun', $source);
    }

    public function testSanitizerDropsTraversalAndControlCharacters(): void
    {
        self::assertSame('passwd', OriginalName::sanitize("../../etc/passwd"));
        self::assertSame('file', OriginalName::sanitize('..'));
        self::assertSame('ab', OriginalName::sanitize("a\0b"));
        self::assertSame(200, mb_strlen(OriginalName::sanitize(str_repeat('n', 240))));
    }

    /**
     * @param list<string>|null $mimes
     */
    private function service(int $maxBytes, ?array $mimes = null): UploadService
    {
        return new UploadService(
            $this->store,
            new StorageConfig($this->root, $maxBytes, $mimes ?? ['text/plain', 'image/png']),
            new FinfoMimeSniffer(),
            new BoundedCopier(),
        );
    }

    /**
     * @return resource
     */
    private function memory(string $contents): mixed
    {
        $handle = fopen('php://memory', 'rb+');
        if (!is_resource($handle)) {
            throw new \RuntimeException('memory stream');
        }
        if ($contents !== '') {
            fwrite($handle, $contents);
        }
        rewind($handle);

        return $handle;
    }

    /**
     * @return list<string>
     */
    private function entries(): array
    {
        $scanned = scandir($this->root);
        if ($scanned === false) {
            return [];
        }
        $entries = [];
        foreach ($scanned as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }
        sort($entries);

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function outsideRoot(StoredFile $stored): array
    {
        $parent = dirname($this->root);
        $found = [];
        foreach ([$stored->id, $stored->originalName, 'passwd'] as $name) {
            if (is_file($parent . '/' . $name)) {
                $found[] = $name;
            }
        }

        return $found;
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $entries = scandir($root);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $root . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
                continue;
            }
            unlink($path);
        }
        rmdir($root);
    }
}

final class CapProbeStream
{
    public static int $reads = 0;

    /** @var resource|null */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        self::$reads++;

        return str_repeat('a', $count);
    }

    public function stream_eof(): bool
    {
        return self::$reads > 20;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => 0, 'mode' => 33060, 'mtime' => 0];
    }
}

final class ReadForbiddenStream
{
    public static int $reads = 0;

    /** @var resource|null */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        self::$reads++;
        throw new \RuntimeException('stream was read');
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => 0, 'mode' => 33060, 'mtime' => 0];
    }
}
