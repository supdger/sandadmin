<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionManifest;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\OptionItem;
use Sandadmin\Domain\Plugin\SettingsCategory;
use Sandadmin\Infra\Plugin\ExtensionRegistry;
use Sandadmin\Infra\Plugin\PluginExtensionRepository;
use Sandadmin\Infra\Plugin\Schema\PluginExtensionSchema;
use Sandadmin\Migrations\Version20261105120000;

/**
 * 默认 SQLite 内存库。设置 SAND_TEST_DB_URL（如 pdo-pgsql://u:p@127.0.0.1/sand_test）则跑真实库。
 */
final class ExtensionRegistryTest extends TestCase
{
    private Connection $conn;
    private PluginExtensionRepository $repository;
    private ExtensionRegistry $registry;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        $this->conn = is_string($url) && $url !== ''
            ? DriverManager::getConnection((new DsnParser())->parse($url))
            : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = $this->conn->createSchemaManager();
        if ($schema->tablesExist([PluginExtensionSchema::TABLE])) {
            $schema->dropTable(PluginExtensionSchema::TABLE);
        }
        PluginExtensionSchema::apply($this->conn);
        $this->repository = new PluginExtensionRepository($this->conn);
        $this->registry = new ExtensionRegistry($this->repository);
    }

    protected function tearDown(): void
    {
        $schema = $this->conn->createSchemaManager();
        if ($schema->tablesExist([PluginExtensionSchema::TABLE])) {
            $schema->dropTable(PluginExtensionSchema::TABLE);
        }
        $this->conn->close();
    }

    public function testMigrationDeclaresTheSameTable(): void
    {
        $migration = (new \ReflectionClass(Version20261105120000::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(Version20261105120000::class, $migration);
        $schema = new Schema();
        $migration->up($schema);
        self::assertTrue($schema->hasTable(PluginExtensionSchema::TABLE));
        $table = $schema->getTable(PluginExtensionSchema::TABLE);
        self::assertSame(
            ['id', 'plugin_id', 'kind', 'extension_key', 'title', 'payload', 'enabled', 'sort_order', 'created_at', 'updated_at'],
            array_map(static fn (\Doctrine\DBAL\Schema\Column $column): string => $column->getName(), $table->getColumns()),
        );
        $migration->down($schema);
        self::assertFalse($schema->hasTable(PluginExtensionSchema::TABLE));

        $columns = $this->conn->createSchemaManager()->listTableColumns(PluginExtensionSchema::TABLE);
        self::assertSame(
            ['id', 'plugin_id', 'kind', 'extension_key', 'title', 'payload', 'enabled', 'sort_order', 'created_at', 'updated_at'],
            array_keys($columns),
        );
        $unique = false;
        foreach ($this->conn->createSchemaManager()->listTableIndexes(PluginExtensionSchema::TABLE) as $index) {
            if ($index->isUnique() && !$index->isPrimary() && $index->getColumns() === ['plugin_id', 'kind', 'extension_key']) {
                $unique = true;
            }
        }
        self::assertTrue($unique);
    }

    public function testUniqueIdentityIsEnforcedByTheTable(): void
    {
        $this->insertRaw('acme', ExtensionKind::SettingsCategory->value, 'general');
        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertRaw('acme', ExtensionKind::SettingsCategory->value, 'general');
    }

    public function testInstallWritesEveryKindAndRuntimeReadsOnlyTheDatabase(): void
    {
        $missing = 'Sandadmin\\Tests\\Missing\\NotARealHandler';
        self::assertFalse(class_exists($missing, false));
        $drafts = ExtensionManifest::drafts([
            'settings_categories' => [
                ['key' => 'general', 'title' => "O'Brien \"通用\"", 'description' => '站点', 'icon' => 'setting', 'sort_order' => 2],
            ],
            'personal_settings_categories' => [
                ['key' => 'profile', 'title' => '资料', 'description' => ''],
            ],
            'search_sources' => [
                ['key' => 'docs', 'title' => '文档', 'handler' => $missing, 'placeholder' => '搜索'],
            ],
            'notification_sources' => [
                ['key' => 'inbox', 'title' => '收件箱', 'channel' => 'inbox'],
            ],
            'cron_jobs' => [
                ['key' => 'nightly', 'title' => '夜间', 'expression' => '0 0 3 * * *', 'handler' => $missing, 'timezone' => 'UTC'],
            ],
            'file_usages' => [
                ['key' => 'cover', 'title' => '封面', 'owner_type' => 'article.cover', 'multiple' => true],
            ],
            'workbench_cards' => [
                ['key' => 'welcome', 'title' => '欢迎', 'component' => 'WelcomeCard', 'width' => 2, 'height' => 1],
            ],
            'option_groups' => [
                ['key' => 'status', 'title' => '状态', 'options' => [['value' => 'on', 'label' => '开']]],
            ],
        ]);
        $this->registry->installPlugin('sand-iam', ...$drafts);

        self::assertSame("O'Brien \"通用\"", $this->registry->settingsCategories()[0]->title);
        self::assertSame('站点', $this->registry->settingsCategories()[0]->description);
        self::assertSame('资料', $this->registry->personalSettingsCategories()[0]->title);
        self::assertSame($missing, $this->registry->searchSources()[0]->handler);
        self::assertSame('inbox', $this->registry->notificationSources()[0]->channel);
        self::assertSame('0 0 3 * * *', $this->registry->cronJobs()[0]->expression);
        self::assertTrue($this->registry->fileUsageRegistrations()[0]->multiple);
        self::assertSame(2, $this->registry->workbenchCards()[0]->width);
        self::assertSame('on', $this->registry->optionGroups()[0]->options[0]->value);
        self::assertFalse(class_exists($missing, false));
        self::assertSame(8, $this->repository->countForPlugin('sand-iam'));
    }

    public function testRuntimeIgnoresFilesOnDisk(): void
    {
        $dir = sys_get_temp_dir() . '/sandadmin-plugin-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $manifest = $dir . '/plugin.manifest.php';
        try {
            file_put_contents($manifest, "<?php return ['settings_categories' => [['key' => 'from-disk', 'title' => '磁盘']]];\n");
            self::assertSame([], $this->registry->settingsCategories());
            $this->registry->installPlugin('acme', ...ExtensionManifest::drafts([
                'settings_categories' => [
                    ['key' => 'from-memory', 'title' => '内存'],
                ],
            ]));
            self::assertSame(['from-memory'], array_map(
                static fn (SettingsCategory $category): string => $category->key,
                $this->registry->settingsCategories(),
            ));
        } finally {
            if (is_file($manifest)) {
                unlink($manifest);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testOrderingDisabledReplacementAndUninstall(): void
    {
        $this->registry->installPlugin(
            'bbb',
            ExtensionDraft::settingsCategory('a', 'B', sortOrder: 0),
        );
        $this->registry->installPlugin(
            'aaa',
            ExtensionDraft::settingsCategory('b', 'B', sortOrder: 0),
            ExtensionDraft::settingsCategory('a', 'A', sortOrder: 0),
            ExtensionDraft::settingsCategory('z', 'Z', sortOrder: 2),
            ExtensionDraft::settingsCategory('hidden', '隐藏', enabled: false),
            ExtensionDraft::cronJob('nightly', '夜间', '0 3 * * *', 'Acme\\Job', enabled: false),
        );

        $categories = $this->registry->settingsCategories();
        self::assertSame(
            ['aaa:a', 'aaa:b', 'bbb:a', 'aaa:z'],
            array_map(
                static fn (SettingsCategory $category): string => $category->pluginId . ':' . $category->key,
                $categories,
            ),
        );
        self::assertNull($categories[0]->icon);
        self::assertSame([], $this->registry->cronJobs());
        self::assertSame(5, $this->repository->countForPlugin('aaa'));

        $fresh = new ExtensionRegistry(new PluginExtensionRepository($this->conn));
        self::assertCount(4, $fresh->settingsCategories());

        $this->registry->installPlugin('aaa', ExtensionDraft::personalSettingsCategory('profile', '资料'));
        self::assertSame([], array_values(array_filter(
            $this->registry->settingsCategories(),
            static fn (SettingsCategory $category): bool => $category->pluginId === 'aaa',
        )));
        self::assertSame('资料', $this->registry->personalSettingsCategories()[0]->title);
        self::assertSame('bbb', $this->registry->settingsCategories()[0]->pluginId);

        $this->registry->installPlugin('aaa');
        self::assertSame([], $this->registry->personalSettingsCategories());
        self::assertSame(0, $this->repository->countForPlugin('aaa'));

        $this->registry->uninstallPlugin('bbb');
        self::assertSame([], $this->registry->settingsCategories());
        $this->registry->uninstallPlugin('missing-plugin');
    }

    public function testDuplicateInstallDoesNotErasePreviousRows(): void
    {
        $this->registry->installPlugin('acme', ExtensionDraft::settingsCategory('general', '通用'));
        try {
            $this->registry->installPlugin(
                'acme',
                ExtensionDraft::settingsCategory('general', '通用'),
                ExtensionDraft::settingsCategory('general', '重复'),
            );
            self::fail('重复键必须拒绝');
        } catch (InvalidExtensionException) {
        }
        self::assertSame('通用', $this->registry->settingsCategories()[0]->title);
        self::assertSame(1, $this->repository->countForPlugin('acme'));
    }

    public function testInvalidDraftDoesNotErasePreviousRows(): void
    {
        $this->registry->installPlugin('acme', ExtensionDraft::settingsCategory('general', '通用'));
        try {
            $this->registry->installPlugin('acme', ExtensionDraft::cronJob('bad', '坏', 'not cron', 'Acme\\Job'));
            self::fail('非法 cron 必须拒绝');
        } catch (InvalidExtensionException) {
        }
        self::assertSame('通用', $this->registry->settingsCategories()[0]->title);
    }

    public function testSameKeyMayExistOnDifferentKinds(): void
    {
        $this->registry->installPlugin(
            'acme',
            ExtensionDraft::settingsCategory('general', '设置'),
            ExtensionDraft::optionGroup('general', '选项', [new OptionItem('a', '甲')]),
        );
        self::assertSame('设置', $this->registry->settingsCategories()[0]->title);
        self::assertSame('选项', $this->registry->optionGroups()[0]->title);
    }

    public function testCorruptPayloadIsRejected(): void
    {
        $this->registry->installPlugin('acme', ExtensionDraft::settingsCategory('general', '通用'));
        $this->conn->createQueryBuilder()
            ->update(PluginExtensionSchema::TABLE)
            ->set('payload', ':payload')
            ->where('extension_key = :extension_key')
            ->setParameter('payload', '{"nope":true}')
            ->setParameter('extension_key', 'general')
            ->executeStatement();

        $this->expectException(InvalidExtensionException::class);
        $this->registry->settingsCategories();
    }

    public function testRejectsPluginIdBeforeWrite(): void
    {
        try {
            $this->registry->installPlugin('../acme', ExtensionDraft::settingsCategory('general', '通用'));
            self::fail('非法 plugin_id 必须拒绝');
        } catch (InvalidExtensionException) {
        }
        self::assertTrue($this->conn->createSchemaManager()->tablesExist([PluginExtensionSchema::TABLE]));
        $count = $this->conn->fetchOne('SELECT COUNT(*) FROM plugin_extension');
        self::assertTrue($count === 0 || $count === '0');
    }

    private function insertRaw(string $pluginId, string $kind, string $key): void
    {
        $this->conn->createQueryBuilder()
            ->insert(PluginExtensionSchema::TABLE)
            ->values([
                'plugin_id' => ':plugin_id',
                'kind' => ':kind',
                'extension_key' => ':extension_key',
                'title' => ':title',
                'payload' => ':payload',
                'enabled' => ':enabled',
                'sort_order' => ':sort_order',
                'created_at' => ':created_at',
                'updated_at' => ':updated_at',
            ])
            ->setParameter('plugin_id', $pluginId)
            ->setParameter('kind', $kind)
            ->setParameter('extension_key', $key)
            ->setParameter('title', 't')
            ->setParameter('payload', '{}')
            ->setParameter('enabled', 1)
            ->setParameter('sort_order', 0)
            ->setParameter('created_at', '2026-11-05 00:00:00')
            ->setParameter('updated_at', '2026-11-05 00:00:00')
            ->executeStatement();
    }
}
