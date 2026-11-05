<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Demo\DemoNote;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Db\Query\ListQuery;
use Sandadmin\Infra\Repository\Demo\DemoNoteRepository;

/**
 * 默认用 SQLite 内存库；设置 SAND_TEST_DB_URL（如 pdo-pgsql://u:p@127.0.0.1/sand_test）则跑真实库。
 */
final class DemoNoteRepositoryTest extends TestCase
{
    private Connection $conn;
    private DemoNoteRepository $repo;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        $this->conn = is_string($url) && $url !== ''
            ? DriverManager::getConnection((new \Doctrine\DBAL\Tools\DsnParser())->parse($url))
            : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new \Doctrine\DBAL\Schema\Schema();
        $t = $schema->createTable('sand_demo_note');
        $t->addColumn('id', 'integer', ['autoincrement' => true]);
        $t->addColumn('title', 'string', ['length' => 100]);
        $t->addColumn('status', 'smallint');
        $t->addColumn('created_at', 'datetime_immutable');
        $t->setPrimaryKey(['id']);
        $sm = $this->conn->createSchemaManager();
        if ($sm->tablesExist(['sand_demo_note'])) {
            $sm->dropTable('sand_demo_note');
        }
        foreach ($schema->toSql($this->conn->getDatabasePlatform()) as $sql) {
            $this->conn->executeStatement($sql);
        }
        $this->repo = new DemoNoteRepository($this->conn);
        $base = new \DateTimeImmutable('2026-01-01 00:00:00');
        foreach (['alpha', 'beta', 'gamma', '100%_off', 'delta'] as $i => $title) {
            $this->repo->create($title, $i % 2, $base->modify("+$i day"));
        }
    }

    protected function tearDown(): void
    {
        $this->conn->createSchemaManager()->dropTable('sand_demo_note');
        $this->conn->close();
    }

    public function testCrud(): void
    {
        $n = $this->repo->create('new', 1, new \DateTimeImmutable('2026-02-01 00:00:00'));
        self::assertInstanceOf(DemoNote::class, $this->repo->find($n->id));
        self::assertTrue($this->repo->rename($n->id, 'renamed'));
        self::assertSame('renamed', $this->repo->find($n->id)?->title);
        self::assertTrue($this->repo->delete($n->id));
        self::assertNull($this->repo->find($n->id));
        self::assertFalse($this->repo->delete($n->id));
    }

    public function testPaginateDefaultOrder(): void
    {
        $p = $this->repo->list(new ListQuery(2, 2));
        self::assertSame(5, $p->total);
        self::assertSame(3, $p->pageCount());
        self::assertSame(['gamma', 'beta'], array_map(static fn (DemoNote $n): string => $n->title, $p->items));
    }

    public function testFilterAndSort(): void
    {
        $p = $this->repo->list(ListQuery::fromArray(['sort' => 'title', 'filters' => ['status' => ['eq' => '0']]]));
        self::assertSame(['alpha', 'delta', 'gamma'], array_map(static fn (DemoNote $n): string => $n->title, $p->items));
        $p = $this->repo->list(ListQuery::fromArray(['filters' => ['id' => ['in' => '1,3', 'gt' => '1']]]));
        self::assertSame(['gamma'], array_map(static fn (DemoNote $n): string => $n->title, $p->items));
    }

    public function testLikeEscapesWildcards(): void
    {
        self::assertSame(1, $this->repo->list(ListQuery::fromArray(['filters' => ['title' => ['like' => '%_']]]))->total);
        self::assertSame(2, $this->repo->list(ListQuery::fromArray(['filters' => ['title' => ['like' => 'ta']]]))->total);
    }

    public function testRejectsUnknownSortField(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->repo->list(ListQuery::fromArray(['sort' => 'title;drop table x']));
    }

    public function testRejectsUnknownFilterField(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->repo->list(ListQuery::fromArray(['filters' => ['password' => ['eq' => 'x']]]));
    }
}