<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\FileUsage;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Infra\Storage\DbalFileUsageRegistry;
use Sandadmin\Infra\Storage\FileUsageSchema;
use Sandadmin\Infra\Storage\FinfoMimeSniffer;
use Sandadmin\Infra\Storage\LocalFlysystemStore;
use Sandadmin\Storage\Exception\FileInUseException;
use Sandadmin\Storage\Exception\FileNotFoundException;
use Sandadmin\Storage\Exception\InvalidUsageException;
use Sandadmin\Storage\FileStore;
use Sandadmin\Storage\FileUsageRef;
use Sandadmin\Storage\StorageConfig;
use Sandadmin\Storage\StoredFile;

final class FileUsageBookkeepingTest extends TestCase
{
    private string $root;

    private Connection $connection;

    private FileStore $store;

    private DbalFileUsageRegistry $registry;

    private FileUsage $usage;

    private UploadService $uploads;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sandadmin-b6-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        FileUsageSchema::apply($this->connection);
        $config = new StorageConfig($this->root, 1024, ['text/plain']);
        $this->store = LocalFlysystemStore::open($this->root);
        $this->registry = new DbalFileUsageRegistry($this->connection);
        $this->usage = new FileUsage($this->store, $this->registry);
        $this->uploads = new UploadService($this->store, $config, new FinfoMimeSniffer(), new BoundedCopier());
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->removeTree($this->root);
    }

    public function testRegisterAndUnregisterAreIdempotent(): void
    {
        $file = $this->storeText();
        $ref = new FileUsageRef($file->id, 'user', '42');
        $this->registry->register($ref);
        $first = $this->registry->listFor($file->id);
        usleep(2000);
        $this->registry->register($ref);
        $second = $this->registry->listFor($file->id);

        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertSame(1, $this->registry->countFor($file->id));
        self::assertSame(
            $first->items[0]->createdAt->format('Y-m-d\TH:i:s.uP'),
            $second->items[0]->createdAt->format('Y-m-d\TH:i:s.uP'),
        );
        self::assertSame('user', $second->items[0]->ref->subjectType);
        self::assertSame('42', $second->items[0]->ref->subjectId);

        $this->registry->unregister($ref);
        $this->registry->unregister($ref);
        self::assertSame(0, $this->registry->countFor($file->id));
        self::assertCount(0, $this->usage->list($file->id));
    }

    public function testDeleteIsBlockedUntilTheLastUsageIsRemoved(): void
    {
        $file = $this->storeText();
        $user = new FileUsageRef($file->id, 'user', '1');
        $page = new FileUsageRef($file->id, 'page', '9');
        $this->usage->register($user);
        $this->usage->register($page);

        try {
            $this->usage->deleteFile($file->id);
            self::fail('expected the file to stay while it is referenced');
        } catch (FileInUseException $error) {
            self::assertSame('file_in_use', $error->errorCode);
        }
        self::assertTrue($this->store->exists($file->id));

        $this->usage->unregister($user);
        try {
            $this->usage->deleteFile($file->id);
            self::fail('expected the remaining reference to keep the file');
        } catch (FileInUseException) {
            self::assertSame(1, $this->registry->countFor($file->id));
        }

        $this->usage->unregister($page);
        $this->usage->deleteFile($file->id);
        self::assertFalse($this->store->exists($file->id));
        self::assertFileDoesNotExist($this->root . '/' . $file->id);
        self::assertFileDoesNotExist($this->root . '/' . $file->id . '.meta.json');
        self::assertSame(0, $this->registry->countFor($file->id));
    }

    public function testRegisterRejectsAMissingFileWithoutWritingUsage(): void
    {
        $id = bin2hex(random_bytes(16));
        try {
            $this->usage->register(new FileUsageRef($id, 'user', '1'));
            self::fail('expected a missing file to be rejected');
        } catch (FileNotFoundException $error) {
            self::assertSame('file_not_found', $error->errorCode);
        }
        self::assertSame(0, $this->registry->countFor($id));
        self::assertCount(0, $this->registry->listFor($id));
    }

    public function testInvalidSubjectIsRejected(): void
    {
        $file = $this->storeText();
        try {
            new FileUsageRef($file->id, '../user', '1');
            self::fail('expected a path-like subject to be rejected');
        } catch (InvalidUsageException $error) {
            self::assertSame('invalid_usage', $error->errorCode);
            self::assertArrayHasKey('subject_type', $error->fields);
        }
        self::assertSame(0, $this->registry->countFor($file->id));
    }

    public function testDeleteRejectsIdentifiersThatLeaveTheStorageRoot(): void
    {
        $secret = dirname($this->root) . '/sandadmin-b6-secret-' . bin2hex(random_bytes(4));
        file_put_contents($secret, 'keep');
        try {
            $this->usage->deleteFile('../' . basename($secret));
            self::fail('expected a non file id to be rejected');
        } catch (InvalidUsageException) {
            self::assertFileExists($secret);
            self::assertSame('keep', file_get_contents($secret));
        } finally {
            unlink($secret);
        }
    }

    public function testSchemaApplyIsIdempotent(): void
    {
        FileUsageSchema::apply($this->connection);
        $file = $this->storeText();
        $this->usage->register(new FileUsageRef($file->id, 'user', '7'));
        self::assertSame(1, $this->registry->countFor($file->id));

        $sql = file_get_contents(dirname(__DIR__, 4) . '/database/migrations/20261105120000_create_file_usage.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS file_usage', $sql);
        self::assertStringContainsString('PRIMARY KEY (file_id, subject_type, subject_id)', $sql);
    }

    private function storeText(): StoredFile
    {
        return $this->uploads->upload($this->memory('hello'), null, 'note.txt');
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
        fwrite($handle, $contents);
        rewind($handle);

        return $handle;
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
