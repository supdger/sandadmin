<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Infra\Plugin\PluginExtensionRepository;
use Sandadmin\Infra\Plugin\PluginResidue;
use Sandadmin\Infra\Plugin\ScheduleClaim;

/**
 * 卸载不删数据时，定时任务不得再被认领，扩展覆盖先停用、重装后恢复。
 * 需要 SAND_TEST_DB_URL 指向已应用 0001 与 0006 的 PostgreSQL。
 */
final class PluginResidueTest extends TestCase
{
    private const PLUGIN = 'residue';

    private const OTHER = 'residuegone';

    private Connection $conn;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        $this->conn = DriverManager::getConnection((new DsnParser())->parse($url));
        try {
            $this->conn->executeQuery('SELECT suspend_time FROM sand_system_schedule LIMIT 0');
        } catch (\Throwable) {
            $this->conn->close();
            unset($this->conn);
            self::markTestSkipped('需要已应用 0006 的 PostgreSQL');
        }
        $this->wipe();
    }

    protected function tearDown(): void
    {
        if (!isset($this->conn)) {
            return;
        }
        $this->wipe();
        $this->conn->close();
    }

    public function testSuspendStopsDueTaskAndKeepsRunHistory(): void
    {
        $past = new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC'));
        $this->insertPlugin(self::PLUGIN, 'installed');
        $this->insertSchedule(self::PLUGIN . '.tick', self::PLUGIN, $past);
        $this->conn->executeStatement(
            'INSERT INTO sand_system_schedule_run (schedule_code, trigger, start_time) VALUES (?, ?, ?)',
            [self::PLUGIN . '.tick', 'schedule', $past],
            [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );
        $this->insertExtension(self::PLUGIN);

        $claim = new ScheduleClaim($this->conn);
        $now = $past->modify('+1 hour');
        self::assertSame([self::PLUGIN . '.tick'], $this->ownedDue($claim, $now));

        (new PluginResidue($this->conn))->suspend(self::PLUGIN, $now);

        self::assertSame([], $this->ownedDue($claim, $now));
        self::assertSame(1, $this->countRuns(self::PLUGIN . '.tick'));
        self::assertFalse($this->isExtensionVisible(self::PLUGIN));
        $enabled = $this->conn->fetchOne(
            'SELECT enabled FROM sand_system_schedule WHERE code = ?',
            [self::PLUGIN . '.tick'],
        );
        self::assertTrue($enabled === true || $enabled === 't' || $enabled === '1' || $enabled === 1);
    }

    public function testResumeRestoresDueTaskAndExtension(): void
    {
        $past = new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC'));
        $now = $past->modify('+1 hour');
        $this->insertPlugin(self::PLUGIN, 'installed');
        $this->insertSchedule(self::PLUGIN . '.tick', self::PLUGIN, $past);
        $this->insertExtension(self::PLUGIN);
        $residue = new PluginResidue($this->conn);
        $residue->suspend(self::PLUGIN, $now);
        $residue->resume(self::PLUGIN, $now->modify('+1 minute'));

        $claim = new ScheduleClaim($this->conn);
        self::assertSame([self::PLUGIN . '.tick'], $this->ownedDue($claim, $now->modify('+2 minutes')));
        $found = 0;
        foreach ((new PluginExtensionRepository($this->conn))->listByKind(ExtensionKind::SettingsCategory) as $override) {
            if ($override->pluginCode !== self::PLUGIN) {
                continue;
            }
            $found++;
            self::assertTrue($override->enabled);
        }
        self::assertSame(1, $found);
    }

    public function testUninstalledPluginIsNotDueWithoutSuspend(): void
    {
        $past = new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC'));
        $now = $past->modify('+1 hour');
        $this->insertPlugin(self::OTHER, 'uninstalled');
        $this->insertSchedule(self::OTHER . '.tick', self::OTHER, $past);
        $this->insertSchedule('host.residue', null, $past);

        $codes = $this->ownedDue(new ScheduleClaim($this->conn), $now);

        self::assertSame(['host.residue'], $codes);
    }

    private function insertPlugin(string $code, string $state): void
    {
        $this->conn->executeStatement(
            'INSERT INTO sand_system_plugin (code, name, version, host_constraint, manifest_sha256, state)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [$code, $code, '1.0.0', '^1.0', str_repeat('a', 64), $state],
        );
    }

    private function insertSchedule(string $code, ?string $pluginCode, \DateTimeImmutable $nextRun): void
    {
        if ($pluginCode === null) {
            $this->conn->executeStatement(
                'INSERT INTO sand_system_schedule (code, plugin_code, enabled, next_run_time) VALUES (?, NULL, TRUE, ?)',
                [$code, $nextRun],
                [ParameterType::STRING, Types::DATETIME_IMMUTABLE],
            );

            return;
        }
        $this->conn->executeStatement(
            'INSERT INTO sand_system_schedule (code, plugin_code, enabled, next_run_time) VALUES (?, ?, TRUE, ?)',
            [$code, $pluginCode, $nextRun],
            [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );
    }

    private function insertExtension(string $pluginCode): void
    {
        $this->conn->executeStatement(
            'INSERT INTO sand_system_plugin_extension'
            . ' (plugin_code, kind, extension_key, enabled, sort) VALUES (?, ?, ?, TRUE, 0)',
            [$pluginCode, ExtensionKind::SettingsCategory->value, 'general'],
        );
    }

    /** @return list<string> */
    private function ownedDue(ScheduleClaim $claim, \DateTimeImmutable $now): array
    {
        $owned = [];
        foreach ($claim->dueCodes($now) as $code) {
            if ($code === self::PLUGIN . '.tick' || $code === self::OTHER . '.tick' || $code === 'host.residue') {
                $owned[] = $code;
            }
        }

        return $owned;
    }

    private function isExtensionVisible(string $pluginCode): bool
    {
        foreach ((new PluginExtensionRepository($this->conn))->listByKind(ExtensionKind::SettingsCategory) as $override) {
            if ($override->pluginCode === $pluginCode) {
                return true;
            }
        }

        return false;
    }

    private function countRuns(string $scheduleCode): int
    {
        $value = $this->conn->fetchOne(
            'SELECT COUNT(*) FROM sand_system_schedule_run WHERE schedule_code = ?',
            [$scheduleCode],
        );
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        self::fail('执行记录计数不是整数');
    }

    private function wipe(): void
    {
        $this->conn->executeStatement(
            'DELETE FROM sand_system_schedule WHERE code IN (?, ?, ?)',
            [self::PLUGIN . '.tick', self::OTHER . '.tick', 'host.residue'],
        );
        $this->conn->executeStatement(
            'DELETE FROM sand_system_plugin WHERE code IN (?, ?)',
            [self::PLUGIN, self::OTHER],
        );
    }
}
