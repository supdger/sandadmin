<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\DisableCheckItem;
use Sandadmin\Domain\Plugin\ExtensionCatalog;
use Sandadmin\Domain\Plugin\ExtensionDraft;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionManifest;
use Sandadmin\Domain\Plugin\ExtensionOverride;
use Sandadmin\Domain\Plugin\FileUsageAuthorizer;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\PluginExtensions;
use Sandadmin\Domain\Plugin\ScheduleFreq;
use Sandadmin\Domain\Plugin\SettingsCategory;
use Sandadmin\Domain\Plugin\UserDisableCheck;
use Sandadmin\Domain\Plugin\UserTransfer;
use Sandadmin\Infra\Plugin\ExtensionRegistry;
use Sandadmin\Infra\Plugin\PluginExtensionRepository;

/**
 * 默认 SQLite 内存库（表结构按 sand_system_plugin_extension 基线列手写）。
 * 设置 SAND_TEST_DB_URL（如 pgsql://u:p@127.0.0.1:25476/sand_test）则跑真实库，
 * 要求库内已应用 0001 基线迁移。
 */
final class ExtensionRegistryTest extends TestCase
{
    private const TABLE = 'sand_system_plugin_extension';
    private const PLUGIN_TABLE = 'sand_system_plugin';

    private Connection $conn;
    private PluginExtensionRepository $repository;
    private ExtensionRegistry $registry;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        $this->conn = is_string($url) && $url !== ''
            ? DriverManager::getConnection((new DsnParser())->parse($url))
            : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        if (($this->conn->getParams()['driver'] ?? null) === 'pdo_sqlite') {
            $this->createSqliteSchema();
        } else {
            foreach (['acme', 'aaa', 'bbb', 'ghost'] as $code) {
                $this->addPluginRow($code);
            }
            // 真实库在用例间共享，覆盖行需要手动清空
            $this->conn->executeStatement('DELETE FROM ' . self::TABLE);
        }
        $this->repository = new PluginExtensionRepository($this->conn);
        $this->registry = new ExtensionRegistry(
            $this->repository,
            ExtensionCatalog::fromManifests(self::manifests()),
        );
    }

    protected function tearDown(): void
    {
        if (!isset($this->conn)) {
            return;
        }
        $this->conn->close();
    }

    /** @return array<string, array<string, mixed>> */
    private static function manifests(): array
    {
        return [
            'acme' => [
                'settings_categories' => [
                    ['key' => 'general', 'title' => "O'Brien \"通用\"", 'description' => '站点', 'icon' => 'setting', 'sort' => 2],
                    ['key' => 'hidden', 'title' => '隐藏', 'enabled' => false],
                ],
                'personal_settings_categories' => [
                    ['key' => 'profile', 'title' => '资料'],
                ],
                'cron_tasks' => [
                    [
                        'key' => 'nightly',
                        'title' => '夜间',
                        'description' => '清理',
                        'handler' => 'Acme\\Tasks\\Nightly',
                        'schedule' => ['freq' => 'daily', 'time' => '03:00'],
                        'schedule_locked' => true,
                        'is_critical' => true,
                    ],
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
                'menus' => [['key' => 'ignored', 'title' => '不属于扩展位']],
            ],
            'bbb' => [
                'settings_categories' => [
                    ['key' => 'a', 'title' => 'B', 'sort' => 0],
                ],
            ],
            'aaa' => [
                'settings_categories' => [
                    ['key' => 'a', 'title' => 'A', 'sort' => 0],
                    ['key' => 'b', 'title' => 'B2', 'sort' => 0],
                    ['key' => 'z', 'title' => 'Z', 'sort' => 5],
                ],
            ],
        ];
    }

    private function createSqliteSchema(): void
    {
        $this->conn->executeStatement(
            'CREATE TABLE ' . self::PLUGIN_TABLE . ' (code varchar(64) PRIMARY KEY)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE ' . self::TABLE . ' (
                plugin_code varchar(64) NOT NULL,
                kind varchar(32) NOT NULL,
                extension_key varchar(128) NOT NULL,
                enabled boolean NOT NULL DEFAULT true,
                "sort" smallint NOT NULL DEFAULT 0,
                create_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                update_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                suspend_time datetime NULL,
                PRIMARY KEY (plugin_code, kind, extension_key),
                CONSTRAINT ck_kind CHECK (kind IN (
                    \'settings_category\', \'personal_settings_category\', \'search_source\',
                    \'notification_source\', \'cron_task\', \'file_usage\', \'workbench_card\', \'option_group\')),
                CONSTRAINT fk_plugin FOREIGN KEY (plugin_code) REFERENCES ' . self::PLUGIN_TABLE . ' (code) ON DELETE CASCADE
            )',
        );
        foreach (['acme', 'aaa', 'bbb', 'ghost'] as $code) {
            $this->conn->executeStatement(
                'INSERT INTO ' . self::PLUGIN_TABLE . ' (code) VALUES (?)',
                [$code],
            );
        }
    }

    private function addPluginRow(string $code): void
    {
        $this->conn->executeStatement(
            'INSERT INTO sand_system_plugin (code, name, version, host_constraint, manifest_sha256, state)
             VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (code) DO NOTHING',
            [$code, $code, '1.0.0', '^1.0', str_repeat('a', 64), 'installed'],
        );
    }

    public function testManifestDefaultsProduceEffectiveExtensionsWithoutDbRows(): void
    {
        $categories = $this->registry->settingsCategories();
        self::assertSame(
            ['aaa:a', 'aaa:b', 'bbb:a', 'acme:general', 'aaa:z'],
            array_map(
                static fn (SettingsCategory $category): string => $category->pluginCode . ':' . $category->key,
                $categories,
            ),
        );
        $general = null;
        foreach ($categories as $category) {
            if ($category->pluginCode === 'acme' && $category->key === 'general') {
                $general = $category;
            }
        }
        if (!$general instanceof SettingsCategory) {
            self::fail('缺少 acme:general');
        }
        self::assertSame("O'Brien \"通用\"", $general->title);
        self::assertSame('站点', $general->description);
        self::assertSame('setting', $general->icon);
        self::assertSame(2, $general->sort);

        self::assertSame('资料', $this->registry->personalSettingsCategories()[0]->title);
        $cron = $this->registry->cronTasks()[0];
        self::assertSame('nightly', $cron->key);
        self::assertSame('Acme\\Tasks\\Nightly', $cron->handler);
        self::assertSame(ScheduleFreq::Daily, $cron->schedule->freq);
        self::assertSame('03:00', $cron->schedule->time);
        self::assertTrue($cron->scheduleLocked);
        self::assertTrue($cron->isCritical);
        self::assertSame('清理', $cron->description);
        self::assertTrue($this->registry->fileUsageRegistrations()[0]->multiple);
        self::assertSame(2, $this->registry->workbenchCards()[0]->width);
        self::assertSame('on', $this->registry->optionGroups()[0]->options[0]->value);
        self::assertSame(0, $this->repository->countForPlugin('acme'));
    }

    public function testOverrideRowsChangeEnabledAndSortOnly(): void
    {
        $this->registry->saveOverride('acme', ExtensionKind::SettingsCategory, 'general', false, 9);
        $this->registry->saveOverride('aaa', ExtensionKind::SettingsCategory, 'z', true, -1);
        $this->registry->saveOverride('bbb', ExtensionKind::SettingsCategory, 'a', true, 100);

        $keys = array_map(
            static fn (SettingsCategory $category): string => $category->pluginCode . ':' . $category->key,
            $this->registry->settingsCategories(),
        );
        self::assertSame(['aaa:z', 'aaa:a', 'aaa:b', 'bbb:a'], $keys);
        self::assertNotContains('acme:general', $keys);
        self::assertNotContains('acme:hidden', $keys);

        $fresh = new ExtensionRegistry($this->repository, ExtensionCatalog::fromManifests(self::manifests()));
        self::assertCount(4, $fresh->settingsCategories());
        self::assertSame(
            3,
            $this->repository->countForPlugin('acme')
                + $this->repository->countForPlugin('aaa')
                + $this->repository->countForPlugin('bbb'),
        );

        $this->registry->resetOverride('acme', ExtensionKind::SettingsCategory, 'general');
        self::assertContains('acme:general', array_map(
            static fn (SettingsCategory $category): string => $category->pluginCode . ':' . $category->key,
            $this->registry->settingsCategories(),
        ));
        $this->registry->resetOverride('acme', ExtensionKind::SettingsCategory, 'general');
    }

    public function testManifestDisabledCanBeEnabledByOverride(): void
    {
        $this->registry->saveOverride('acme', ExtensionKind::SettingsCategory, 'hidden', true, 0);
        $keys = array_map(
            static fn (SettingsCategory $category): string => $category->pluginCode . ':' . $category->key,
            $this->registry->settingsCategories(),
        );
        self::assertContains('acme:hidden', $keys);
    }

    public function testStaleOverrideWithoutDeclarationIsIgnored(): void
    {
        $this->repository->save(
            new ExtensionOverride('ghost', ExtensionKind::SettingsCategory, 'gone', true, 0),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $keys = array_map(
            static fn (SettingsCategory $category): string => $category->pluginCode . ':' . $category->key,
            $this->registry->settingsCategories(),
        );
        self::assertNotContains('ghost:gone', $keys);
    }

    public function testSaveOverrideRejectsUndeclaredExtension(): void
    {
        try {
            $this->registry->saveOverride('acme', ExtensionKind::SettingsCategory, 'missing', true, 0);
            self::fail('未声明的扩展位必须拒绝');
        } catch (InvalidExtensionException) {
        }
        try {
            $this->registry->saveOverride('nobody', ExtensionKind::SettingsCategory, 'general', true, 0);
            self::fail('未声明的插件必须拒绝');
        } catch (InvalidExtensionException) {
        }
        try {
            $this->registry->saveOverride('../acme', ExtensionKind::SettingsCategory, 'general', true, 0);
            self::fail('非法 plugin_code 必须拒绝');
        } catch (InvalidExtensionException) {
        }
        self::assertSame(0, $this->repository->countForPlugin('acme'));
    }

    public function testSaveIsUpsertAndCompositeKeyIsEnforced(): void
    {
        $at = new \DateTimeImmutable('2026-11-10 00:00:00', new \DateTimeZone('UTC'));
        $this->repository->save(new ExtensionOverride('acme', ExtensionKind::FileUsage, 'cover', true, 3), $at);
        // 同一复合键再次保存是更新而不是插入第二行
        $this->repository->save(
            new ExtensionOverride('acme', ExtensionKind::FileUsage, 'cover', false, 9),
            $at->modify('+1 hour'),
        );
        self::assertSame(1, $this->repository->countForPlugin('acme'));
        self::assertSame([], $this->registry->fileUsageRegistrations());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->conn->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (plugin_code, kind, extension_key, enabled, "sort")
             VALUES (?, ?, ?, ?, ?)',
            ['acme', 'file_usage', 'cover', true, 1],
        );
    }

    public function testSameKeyMayExistOnDifferentKinds(): void
    {
        $this->registry->saveOverride('acme', ExtensionKind::OptionGroup, 'status', true, 1);
        self::assertSame('状态', $this->registry->optionGroups()[0]->title);
        self::assertSame(1, $this->repository->countForPlugin('acme'));
    }

    public function testDeleteForPluginRemovesAllRows(): void
    {
        $this->registry->saveOverride('acme', ExtensionKind::SettingsCategory, 'general', false, 0);
        $this->registry->saveOverride('acme', ExtensionKind::FileUsage, 'cover', false, 0);
        self::assertSame(2, $this->repository->countForPlugin('acme'));
        $this->repository->deleteForPlugin('acme');
        self::assertSame(0, $this->repository->countForPlugin('acme'));
        $this->repository->deleteForPlugin('missing-plugin');
    }

    public function testDisableCheckItemsAggregatesRegisteredCallbacks(): void
    {
        $this->registry->registerDisableCheck('zzz', new class implements UserDisableCheck {
            /** @return list<DisableCheckItem> */
            public function pendingItems(int $userId): array
            {
                return [new DisableCheckItem('zzz', 'open_ticket', 2, '有未处理工单')];
            }
        });
        $this->registry->registerDisableCheck('aaa', new class implements UserDisableCheck {
            /** @return list<DisableCheckItem> */
            public function pendingItems(int $userId): array
            {
                return [new DisableCheckItem('aaa', 'pending_order', 1, '有待发货订单')];
            }
        });

        $items = $this->registry->disableCheckItems(42);
        self::assertCount(2, $items);
        self::assertSame(['aaa', 'zzz'], array_map(static fn (DisableCheckItem $item): string => $item->source, $items));
        self::assertSame(
            ['source' => 'aaa', 'kind' => 'pending_order', 'count' => 1, 'message' => '有待发货订单'],
            $items[0]->jsonSerialize(),
        );
    }

    public function testDisableCheckEmptyWhenNoCallbacks(): void
    {
        self::assertSame([], $this->registry->disableCheckItems(1));
    }

    public function testTransferUserItemsInvokesCallbacksInPluginOrder(): void
    {
        /** @var \ArrayObject<int, string> $log */
        $log = new \ArrayObject();
        $make = static function (string $tag) use ($log): UserTransfer {
            return new class($tag, $log) implements UserTransfer {
                /** @param \ArrayObject<int, string> $log */
                public function __construct(private string $tag, private \ArrayObject $log)
                {
                }

                public function transfer(int $userId, int $toUserId): void
                {
                    $this->log->append($this->tag . ':' . $userId . '>' . $toUserId);
                }
            };
        };
        $this->registry->registerUserTransfer('zzz', $make('zzz'));
        $this->registry->registerUserTransfer('aaa', $make('aaa'));

        $this->registry->transferUserItems(10, 20);
        self::assertSame(['aaa:10>20', 'zzz:10>20'], $log->getArrayCopy());
    }

    public function testDuplicateCallbackRegistrationIsRejected(): void
    {
        $check = new class implements UserDisableCheck {
            /** @return list<DisableCheckItem> */
            public function pendingItems(int $userId): array
            {
                return [];
            }
        };
        $this->registry->registerDisableCheck('acme', $check);
        $this->expectException(InvalidExtensionException::class);
        $this->registry->registerDisableCheck('acme', $check);
    }

    public function testFileUsageAuthorizationAggregatesBySubjectType(): void
    {
        $this->registry->registerFileUsageAuthorizer('article.cover', new class implements FileUsageAuthorizer {
            public function authorize(int $userId, int $fileId, string $subjectType, string $subjectId): bool
            {
                return $subjectId === '42';
            }
        });
        $this->registry->registerFileUsageAuthorizer('user_avatar', new class implements FileUsageAuthorizer {
            public function authorize(int $userId, int $fileId, string $subjectType, string $subjectId): bool
            {
                return true;
            }
        });

        self::assertTrue($this->registry->authorizeFileUsage(7, 100, []));
        self::assertTrue($this->registry->authorizeFileUsage(7, 100, [
            ['subject_type' => 'article.cover', 'subject_id' => '42'],
            ['subject_type' => 'user_avatar', 'subject_id' => '7'],
        ]));
        self::assertFalse($this->registry->authorizeFileUsage(7, 100, [
            ['subject_type' => 'article.cover', 'subject_id' => '41'],
        ]));
        self::assertFalse($this->registry->authorizeFileUsage(7, 100, [
            ['subject_type' => 'unknown.thing', 'subject_id' => '1'],
        ]));
    }

    public function testDuplicateFileUsageAuthorizerIsRejected(): void
    {
        $authorizer = new class implements FileUsageAuthorizer {
            public function authorize(int $userId, int $fileId, string $subjectType, string $subjectId): bool
            {
                return true;
            }
        };
        $this->registry->registerFileUsageAuthorizer('article.cover', $authorizer);
        $this->expectException(InvalidExtensionException::class);
        $this->registry->registerFileUsageAuthorizer('article.cover', $authorizer);
    }

    public function testScheduleDefinitionRoundTripsThroughManifest(): void
    {
        $drafts = ExtensionManifest::drafts([
            'cron_tasks' => [
                ['key' => 'tick', 'title' => '心跳', 'handler' => 'Acme\\Tick', 'schedule' => ['freq' => 'interval', 'interval' => 5]],
                ['key' => 'week', 'title' => '周报', 'handler' => 'Acme\\Week', 'schedule' => ['freq' => 'weekly', 'time' => '09:30', 'weekday' => 1]],
            ],
        ]);
        self::assertCount(2, $drafts);
        /** @var list<ExtensionDraft> $drafts */
        $catalog = new ExtensionCatalog([new PluginExtensions('solo', $drafts)]);
        $tasks = (new ExtensionRegistry($this->repository, $catalog))->cronTasks();
        self::assertCount(2, $tasks);
        self::assertSame(5, $tasks[0]->schedule->interval);
        self::assertSame(1, $tasks[1]->schedule->weekday);
    }
}
