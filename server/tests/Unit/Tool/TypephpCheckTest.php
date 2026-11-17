<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Tool;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/tools/typephp_check.php';

/** TypePHP 禁用写法检查脚本的正反例回归；fixture 全走临时文件，测试完清理。 */
final class TypephpCheckTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/typephp_check_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * 写 fixture 并扫描，返回命中列表。
     *
     * @param array{files?:array<string,true>,lines?:array<string,array<int,true>>} $allow
     * @return list<array{level:string,rule:string,line:int,lines:list<int>,name:string,why:string,exempt:bool}>
     */
    private function scan(string $code, array $allow = [], string $name = 'fixture.php'): array
    {
        $abs = $this->dir . '/' . $name;
        file_put_contents($abs, "<?php\n" . $code);
        return typephpScanFile($abs, 'server/src/' . $name, ['files' => $allow['files'] ?? [], 'lines' => $allow['lines'] ?? []]);
    }

    /** @param list<array{level:string,rule:string,exempt:bool}> $hits */
    private function countHits(array $hits, string $level, ?string $rule = null): int
    {
        return count(array_filter($hits, static fn (array $h): bool => !$h['exempt']
            && $h['level'] === $level && ($rule === null || $h['rule'] === $rule)));
    }

    public function testMagicMethodsFlagged(): void
    {
        $hits = $this->scan('class A { public function __call($method, $args) {} private function __get($name) {} protected function __serialize(): array { return []; } }');
        self::assertSame(3, $this->countHits($hits, 'FAIL', '魔术方法'));
    }

    public function testAllowedDunderMethodsIgnored(): void
    {
        $hits = $this->scan('class A { public function __construct() {} public function __invoke() {} public function __toString(): string { return "a"; } }');
        self::assertSame(0, $this->countHits($hits, 'FAIL'));
    }

    public function testVariableVariableFlagged(): void
    {
        $hits = $this->scan('$$name = 1;');
        self::assertSame(1, $this->countHits($hits, 'FAIL', '可变变量'));
    }

    public function testVariableCallsFlagged(): void
    {
        $hits = $this->scan('$fn(); $o->$method(); $o?->$action(); Foo::$service(); $o->{$e}(); new $className();');
        self::assertSame(6, $this->countHits($hits, 'FAIL', '可变调用'));
    }

    public function testStaticPropertyAccessAllowed(): void
    {
        $hits = $this->scan('class A { public static $x; } $y = A::$x; self::$x = 1;');
        self::assertSame(0, $this->countHits($hits, 'FAIL'));
    }

    public function testDynamicFunctionsFlagged(): void
    {
        $hits = $this->scan('eval($code); extract($vars); $fn = create_function("$a", "return $a;");');
        self::assertSame(3, $this->countHits($hits, 'FAIL'));
        self::assertSame(1, $this->countHits($hits, 'FAIL', '动态执行'));
        self::assertSame(2, $this->countHits($hits, 'FAIL', '危险函数'));
    }

    public function testDynamicIncludeFlagged(): void
    {
        $hits = $this->scan('require $path; include_once $file; require $$x;');
        self::assertSame(3, $this->countHits($hits, 'FAIL', '动态加载'));
    }

    public function testStaticIncludeAllowed(): void
    {
        $hits = $this->scan('require __DIR__ . "/a.php"; include_once "b.php"; require_once dirname(__DIR__) . "/c.php";');
        self::assertSame(0, $this->countHits($hits, 'FAIL', '动态加载'));
    }

    public function testCommentsAndStringsIgnored(): void
    {
        $code = <<<'CODE'
            // $fn(); eval($x); function __call() {}
            /* extract($a); $$var = 1; */
            $left = 'eval($x)';
            $right = "use $$x and $fn() here";
            /** @see __call 注释字样不算 */
            $doc = 'self::$method()';
            CODE;
        $hits = $this->scan($code);
        self::assertSame(0, $this->countHits($hits, 'FAIL'));
    }

    public function testWarnRulesNotFail(): void
    {
        $hits = $this->scan('compact("a"); $closure->bindTo($target); Closure::bind($fn, $target);');
        self::assertSame(0, $this->countHits($hits, 'FAIL'));
        self::assertSame(1, $this->countHits($hits, 'WARN', '反向造变量'));
        self::assertSame(2, $this->countHits($hits, 'WARN', '闭包重绑定'));
    }

    public function testAllowListExemptsHits(): void
    {
        $allow = ['files' => ['server/src/exempt.php' => true]];
        $hits = $this->scan('$fn(); $$var = 1;', $allow, 'exempt.php');
        self::assertSame(0, $this->countHits($hits, 'FAIL'));
        self::assertGreaterThan(0, count($hits));
    }

    public function testAllowListLineScoped(): void
    {
        $allow = ['lines' => ['server/src/scoped.php' => [2 => true]]];
        $hits = $this->scan("extract(\$a);\ncompact('x');", $allow, 'scoped.php');
        self::assertSame(0, $this->countHits($hits, 'FAIL'));
        self::assertSame(1, $this->countHits($hits, 'WARN')); // compact 在第 3 行，不在豁免行
    }

    public function testParseDiffCollectsAddedLines(): void
    {
        $diff = <<<'DIFF'
            diff --git a/server/src/A.php b/server/src/A.php
            index 111..222 100644
            --- a/server/src/A.php
            +++ b/server/src/A.php
            @@ -10,0 +11,2 @@
            +$fn();
            +extract($a);
            @@ -30,2 +40,1 @@
             kept
            -gone
            +added
            DIFF;
        $added = typephpParseDiff($diff);
        $lines = $added['server/src/A.php'] ?? [];
        self::assertTrue($lines[11] ?? false);
        self::assertTrue($lines[12] ?? false);
        self::assertTrue($lines[40] ?? false);
        self::assertArrayNotHasKey(13, $lines);
    }
}
