<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Contract;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Build\ContractDoc;
use Sandadmin\Contract\Build\PhpEmit;

/**
 * 插件命名连接四规则（x-connections）的解析校验：合规声明通过，
 * 账本不随连接、outbox 不落业务库、审计不异步落内核、连接预算缺输入
 * 都按问题清单打回。夹具走临时片段文件，不碰真实插件。
 *
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class NamedConnectionTest extends TestCase
{
    private string $caseDir = '';

    protected function tearDown(): void
    {
        if ($this->caseDir !== '' && is_dir($this->caseDir)) {
            foreach (glob($this->caseDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->caseDir);
            $this->caseDir = '';
        }
    }

    public function testCompliantConnectionPasses(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: business_database
                outbox_relay: per_connection
                audit_sink: kernel_async
                audit_records: local
            YAML));

        self::assertSame([], $doc['problems'], implode("\n", $doc['problems']));
        self::assertCount(1, $doc['connections']);
        self::assertSame(2, $doc['connections'][0]['connections_per_process']);
    }

    public function testNoneDeclarationPasses(): void
    {
        // 连接不用 outbox/审计时显式声明 none，与「未声明」可区分
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertSame([], $doc['problems'], implode("\n", $doc['problems']));
    }

    public function testUndeclaredConnectionsPass(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(''));

        self::assertSame([], $doc['problems'], implode("\n", $doc['problems']));
        self::assertSame([], $doc['connections']);
    }

    public function testHostConnectionRejected(): void
    {
        $doc = $this->parseFragment('host', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('宿主契约不允许出现', $doc['problems']);
    }

    public function testPrivateLedgerFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: private
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('ledger 必须声明为 per_connection', $doc['problems']);
    }

    public function testKernelOutboxFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: kernel_database
                audit_sink: none
            YAML));

        self::assertHasProblem('outbox 必须声明为 business_database 或 none', $doc['problems']);
    }

    public function testOutboxMissingRelayFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: business_database
                audit_sink: none
            YAML));

        self::assertHasProblem('outbox_relay 必须声明为 per_connection', $doc['problems']);
    }

    public function testNoneOutboxWithRelayFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                outbox_relay: per_connection
                audit_sink: none
            YAML));

        self::assertHasProblem('outbox 为 none 时不许声明 outbox_relay', $doc['problems']);
    }

    public function testSyncAuditFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: sync_table
            YAML));

        self::assertHasProblem('audit_sink 必须声明为 kernel_async 或 none', $doc['problems']);
    }

    public function testKernelAuditMissingRecordsFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: kernel_async
            YAML));

        self::assertHasProblem('audit_records 必须声明为 local', $doc['problems']);
    }

    public function testNoneAuditWithRecordsFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
                audit_records: local
            YAML));

        self::assertHasProblem('audit_sink 为 none 时不许声明 audit_records', $doc['problems']);
    }

    public function testMissingPerProcessFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('connections_per_process 必须是 ≥1 的整数', $doc['problems']);
    }

    public function testZeroPerProcessFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 0
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('connections_per_process 必须是 ≥1 的整数', $doc['problems']);
    }

    public function testHostNameCollisionFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: pgsql
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('占用宿主连接名', $doc['problems']);
    }

    public function testDuplicateNameFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
              - name: sandai
                connections_per_process: 1
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('重复声明', $doc['problems']);
    }

    public function testBadNameShapeFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: Sand-AI
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
            YAML));

        self::assertHasProblem('不是小写蛇形', $doc['problems']);
    }

    public function testUnknownKeyFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: per_connection
                outbox: none
                audit_sink: none
                private_ledger: true
            YAML));

        self::assertHasProblem('未识别键', $doc['problems']);
    }

    public function testNonListConnectionsFails(): void
    {
        $doc = $this->parseFragment('sand-bad', self::yamlWith(<<<'YAML'
            x-connections:
              name: sandai
            YAML));

        self::assertHasProblem('x-connections 必须是 list', $doc['problems']);
    }

    public function testBudgetArtifactSumsPerProcess(): void
    {
        $content = PhpEmit::emitConnectionBudget([
            ['plugin' => 'sand-ai', 'name' => 'sandai', 'connections_per_process' => 2],
            ['plugin' => 'sand-x', 'name' => 'xdb', 'connections_per_process' => 1],
        ], ['contract/openapi.yaml'], 'ab12');

        $budget = json_decode($content, true);
        self::assertIsArray($budget);
        self::assertSame(4, $budget['host_per_process']);
        self::assertSame(3, $budget['plugin_per_process']);
        self::assertSame(7, $budget['total_per_process']);
        self::assertIsArray($budget['connections']);
        self::assertCount(2, $budget['connections']);
    }

    /** @return ParsedDoc */
    private function parseFragment(string $source, string $yaml): array
    {
        $dir = sys_get_temp_dir() . '/contract-conn-' . getmypid() . '-' . bin2hex(random_bytes(3));
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            self::fail('无法创建临时目录');
        }
        $this->caseDir = $dir;
        $file = $dir . '/openapi.yaml';
        file_put_contents($file, $yaml);

        return ContractDoc::parse($file, $source);
    }

    /** 拼一份最小合法片段（info + 空 paths），$extra 追加顶层段 */
    private static function yamlWith(string $extra): string
    {
        return "openapi: 3.1.0\ninfo: {title: t, version: \"0.1\"}\n" . $extra . "\npaths: {}\n";
    }

    /**
     * @param list<string> $problems
     */
    private static function assertHasProblem(string $needle, array $problems): void
    {
        self::assertNotEmpty($problems, '期望校验打回但 problems 为空');
        self::assertStringContainsString($needle, implode("\n", $problems));
    }
}
