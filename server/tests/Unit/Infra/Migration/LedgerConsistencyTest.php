<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Migration;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Migration\LedgerConsistency;
use Sandadmin\Infra\Migration\LedgerRow;
use Sandadmin\Infra\Migration\MigrationCatalog;
use Sandadmin\Infra\Migration\MigrationFile;

/**
 * 半成品库检测：failed 记录、checksum 不符、账本/文件断档、非法 status
 * 都必须报违例；干净账本与「应用到前缀 + 尾部 rolled_back」必须放行。
 */
final class LedgerConsistencyTest extends TestCase
{
    /** @var list<MigrationFile> */
    private array $files;

    protected function setUp(): void
    {
        $this->files = MigrationCatalog::load(['host' => __DIR__ . '/fixtures/migrations'])->all();
    }

    public function testEmptyLedgerIsConsistent(): void
    {
        self::assertSame([], LedgerConsistency::problems($this->files, []));
    }

    public function testAppliedPrefixIsConsistent(): void
    {
        $rows = [
            $this->row('0001', 'applied', $this->files[0]->checksum),
            $this->row('0002', 'applied', $this->files[1]->checksum),
        ];
        self::assertSame([], LedgerConsistency::problems($this->files, $rows));
    }

    public function testAppliedPrefixWithRolledBackTailIsConsistent(): void
    {
        $rows = [
            $this->row('0001', 'applied', $this->files[0]->checksum),
            $this->row('0002', 'applied', $this->files[1]->checksum),
            $this->row('0003', 'rolled_back', 'x', 'down'),
        ];
        // 0003 无回退文件：checksum 无法核对，不报错
        self::assertSame([], LedgerConsistency::problems($this->files, $rows));
    }

    public function testFailedRowIsReported(): void
    {
        $rows = [
            $this->row('0001', 'applied', $this->files[0]->checksum),
            $this->row('0002', 'failed', $this->files[1]->checksum),
        ];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertNotEmpty($problems);
        self::assertStringContainsString('failed', implode("\n", $problems));
    }

    public function testChecksumMismatchOnAppliedFileIsReported(): void
    {
        $rows = [
            $this->row('0001', 'applied', 'deadbeef'),
        ];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertStringContainsString('被改动', implode("\n", $problems));
    }

    public function testChecksumMismatchOnDownFileIsReported(): void
    {
        $rows = [
            $this->row('0001', 'applied', $this->files[0]->checksum),
            $this->row('0002', 'rolled_back', 'deadbeef', 'down'),
        ];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertStringContainsString('回退文件被改动', implode("\n", $problems));
    }

    public function testMissingFileIsReported(): void
    {
        $rows = [$this->row('0099', 'applied', 'x')];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertStringContainsString('找不到对应文件', implode("\n", $problems));
    }

    public function testGapInAppliedSequenceIsReported(): void
    {
        $rows = [
            $this->row('0001', 'applied', $this->files[0]->checksum),
            $this->row('0003', 'applied', 'x'),
        ];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertStringContainsString('对不上', implode("\n", $problems));
    }

    public function testUnrecordedVersionBelowRecordedIsReported(): void
    {
        // 0003 有账本行（rolled_back）但 0002 没有任何行：账本丢行
        $rows = [
            $this->row('0001', 'applied', $this->files[0]->checksum),
            $this->row('0003', 'rolled_back', 'x', 'down'),
        ];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertStringContainsString('没有账本行', implode("\n", $problems));
    }

    public function testInvalidStatusIsReported(): void
    {
        $rows = [$this->row('0001', 'weird', 'x')];
        $problems = LedgerConsistency::problems($this->files, $rows);
        self::assertStringContainsString('非法', implode("\n", $problems));
    }

    public function testGuidanceContainsRepairHints(): void
    {
        $text = LedgerConsistency::guidance(['样例违例']);
        self::assertStringContainsString('修复指引', $text);
        self::assertStringContainsString('样例违例', $text);
    }

    /**
     * @param 'up'|'down' $direction
     */
    private function row(
        string $version,
        string $status,
        string $checksum,
        string $direction = 'up',
    ): LedgerRow {
        return new LedgerRow(
            'host',
            $version,
            $version . '_x.sql',
            $checksum,
            $direction,
            $status,
            '1.0.0',
            'tester',
            '2026-11-19 00:00:00+00',
            1,
        );
    }
}
