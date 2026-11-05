<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\File;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\FileUsage;
use Sandadmin\Application\File\UploadRequest;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Http\File\FileStackFactory;
use Sandadmin\Http\File\FileUsageHttp;
use Sandadmin\Http\File\HttpErrorMapper;
use Sandadmin\Http\File\RequestId;
use Sandadmin\Http\File\UploadHttp;
use Sandadmin\Infra\Storage\DbalFileUsageRegistry;
use Sandadmin\Infra\Storage\FileUsageSchema;
use Sandadmin\Infra\Storage\LocalFlysystemStore;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\FileStore;
use Sandadmin\Storage\MimeSniffer;
use Sandadmin\Storage\StorageConfig;

final class FileEndpointTest extends TestCase
{
    private string $root;

    private FileStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sandadmin-b6-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->store = LocalFlysystemStore::open($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testUploadAndUsageResponsesUseTheErrorEnvelope(): void
    {
        $config = new StorageConfig($this->root, 32, ['text/plain']);
        $uploads = new UploadHttp(new UploadService(
            $this->store,
            $config,
            new \Sandadmin\Infra\Storage\FinfoMimeSniffer(),
            new BoundedCopier(),
        ), new HttpErrorMapper());
        $created = $uploads->upload(new UploadRequest($this->memory('hello'), null, 'note.txt'), 'req-fixed1');
        self::assertSame(201, $created->status);
        $body = json_decode($created->json(), true);
        self::assertIsArray($body);
        self::assertSame('text/plain', $body['mime']);
        self::assertSame(5, $body['size']);
        self::assertIsString($body['id']);

        $tooBig = $uploads->upload(new UploadRequest($this->memory('hello'), 33, 'note.txt'), 'req-fixed1');
        self::assertSame(413, $tooBig->status);
        self::assertStringContainsString('"code":"upload_too_large"', $tooBig->json());
        self::assertStringContainsString('"request_id":"req-fixed1"', $tooBig->json());
        self::assertStringContainsString('"fields":{', $tooBig->json());

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        FileUsageSchema::apply($connection);
        $usageHttp = new FileUsageHttp(new FileUsage($this->store, new DbalFileUsageRegistry($connection)), new HttpErrorMapper());
        $fileId = $body['id'];
        $registered = $usageHttp->register($fileId, ['subject_type' => 'user', 'subject_id' => '9'], 'req-fixed1');
        self::assertSame(201, $registered->status);
        $again = $usageHttp->register($fileId, ['subject_type' => 'user', 'subject_id' => '9'], 'req-fixed1');
        self::assertSame(201, $again->status);
        $blocked = $usageHttp->delete($fileId, 'req-fixed1');
        self::assertSame(409, $blocked->status);
        self::assertStringContainsString('"code":"file_in_use"', $blocked->json());
        $removed = $usageHttp->unregister($fileId, ['subject_type' => 'user', 'subject_id' => '9'], 'req-fixed1');
        self::assertSame(200, $removed->status);
        $deleted = $usageHttp->delete($fileId, 'req-fixed1');
        self::assertSame(200, $deleted->status);
        $missing = $usageHttp->delete($fileId, 'req-fixed1');
        self::assertSame(404, $missing->status);
        self::assertStringContainsString('"fields":{', $missing->json());
        $badSubject = $usageHttp->register($fileId, ['subject_type' => 1, 'subject_id' => 'a'], 'req-fixed1');
        self::assertSame(422, $badSubject->status);
        self::assertStringContainsString('"code":"invalid_usage"', $badSubject->json());
        $connection->close();
    }

    public function testUnexpectedFailuresDoNotLeakInternals(): void
    {
        $uploads = new UploadHttp(new UploadService(
            $this->store,
            new StorageConfig($this->root, 32, ['text/plain']),
            new SecretSniffer(),
            new BoundedCopier(),
        ), new HttpErrorMapper());
        $result = $uploads->upload(new UploadRequest($this->memory('hello'), null, 'note.txt'), 'req-fixed1');
        self::assertSame(500, $result->status);
        self::assertStringContainsString('"code":"internal_error"', $result->json());
        self::assertStringContainsString('"fields":{}', $result->json());
        self::assertStringNotContainsString('SECRET', $result->json());
        self::assertStringNotContainsString('/etc/passwd', $result->json());
    }

    public function testRequestIdAndRoutesAndDatabaseGuard(): void
    {
        self::assertSame('abcd_1234', RequestId::fromHeader('abcd_1234'));
        $generated = RequestId::fromHeader("bad\nid");
        self::assertSame(1, preg_match('/\A[a-f0-9]{16}\z/', $generated));

        $routes = file_get_contents(dirname(__DIR__, 4) . '/config/route.php');
        self::assertIsString($routes);
        self::assertStringContainsString("'/api/files'", $routes);
        self::assertStringContainsString("'/api/files/{id}/usages'", $routes);
        self::assertStringContainsString("'/api/files/{id}'", $routes);

        try {
            FileStackFactory::connectionFromDatabaseConfig(['database' => '', 'driver' => 'pgsql']);
            self::fail('expected an unconfigured database to be rejected');
        } catch (StorageException $error) {
            self::assertSame('storage_unavailable', $error->errorCode);
            self::assertSame('数据库尚未配置', $error->getMessage());
        }
        try {
            FileStackFactory::connectionFromDatabaseConfig([
                'database' => 'sandadmin',
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'username' => 'sand',
                'password' => 'secret',
            ]);
            self::fail('expected a non-postgresql driver to be rejected');
        } catch (StorageException $error) {
            self::assertSame('文件登记只支持 PostgreSQL', $error->getMessage());
            self::assertStringNotContainsString('secret', $error->getMessage());
        }
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

final class SecretSniffer implements MimeSniffer
{
    public function sniff(mixed $stream): string
    {
        throw new \RuntimeException('SECRET:/etc/passwd');
    }
}
