<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/security/scan-input-bans.php';

/** 输入输出三条禁令扫描器的正反例回归；PHP fixture 走临时目录，前端用例直接喂源码。 */
final class InputBanScanTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/input_bans_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /** 写 PHP fixture 并扫描，返回命中列表。
     *
     * @return list<array{rule:string,line:int,detail:string,file?:string}>
     */
    private function scanPhp(string $code, string $name = 'fixture.php'): array
    {
        $abs = $this->dir . '/' . $name;
        file_put_contents($abs, "<?php\n" . $code);
        return banScanPhpFile($abs, $name);
    }

    /**
     * @param list<array{rule:string,line:int,detail:string,file?:string}> $hits
     * @return list<string>
     */
    private function rules(array $hits): array
    {
        return array_map(static fn (array $hit): string => $hit['rule'], $hits);
    }

    public function testUnserializeCallFlagged(): void
    {
        $hits = $this->scanPhp('$x = unserialize($data); $y = UnSerialize ($blob);');
        self::assertSame(['反序列化调用', '反序列化调用'], $this->rules($hits));
    }

    public function testUnserializeMethodAndDunderIgnored(): void
    {
        $hits = $this->scanPhp('$obj->unserialize($x); Foo::unserialize($y); $unz = new Unserialize($a);');
        self::assertSame([], $this->rules($hits));
    }

    public function testUnserializeInCommentAndStringIgnored(): void
    {
        $hits = $this->scanPhp("// unserialize(\$x)\n\$str = 'unserialize(\$x)';");
        self::assertSame([], $this->rules($hits));
    }

    public function testRequestArrayDirectlyInPersistCallFlagged(): void
    {
        $hits = $this->scanPhp(
            '$model->save($request->post());'
            . 'Repo::create($request->all());'
            . '$repo->insert(["a" => 1], $request->get());'
            . '$this->request->update($request?->post());'
        );
        self::assertSame(
            ['请求数组直灌', '请求数组直灌', '请求数组直灌', '请求数组直灌'],
            $this->rules($hits),
        );
    }

    public function testRequestArrayViaValidatedFieldsAllowed(): void
    {
        $code = <<<'CODE'
            $body = $request->post();
            $fields = OrgInput::user($body, $actor);
            $repo->insert($fields);
            $model->fill($fields)->save();
            $service->update($id, $fields);
            CODE;
        self::assertSame([], $this->rules($this->scanPhp($code)));
    }

    public function testNonRequestReceiversAndNonPersistCallsAllowed(): void
    {
        $code = <<<'CODE'
            $catalog->all();
            $this->contexts->get($request);
            FileHttp::upload($runtime->uploads, $request->post('visibility'));
            $query = $request->get();
            $repo->insert($query);
            CODE;
        self::assertSame([], $this->rules($this->scanPhp($code)));
    }

    public function testHtmlAddedLineWithoutSanitizerFlagged(): void
    {
        $content = "<template>\n  <div v-html=\"raw\"></div>\n</template>\n";
        $hits = banScanHtmlFile('web/src/views/Demo.vue', $content, [2 => true]);
        self::assertCount(1, $hits);
        self::assertSame('未消毒HTML渲染', $hits[0]['rule']);
        self::assertSame(2, $hits[0]['line']);
    }

    public function testHtmlExistingLineNotFlagged(): void
    {
        $content = "<template>\n  <div v-html=\"raw\"></div>\n</template>\n";
        $hits = banScanHtmlFile('web/src/views/Demo.vue', $content, [1 => true]);
        self::assertSame([], $hits);
    }

    public function testHtmlSanitizedFileAllowed(): void
    {
        $content = "import DOMPurify from 'dompurify';\nconst html = DOMPurify.sanitize(raw);\n<div v-html=\"html\"></div>\n";
        $hits = banScanHtmlFile('sandadmin-artd/src/Demo.tsx', $content, [3 => true]);
        self::assertSame([], $hits);
    }

    public function testHtmlPatternsAndIndexHtml(): void
    {
        $content = "el.innerHTML = raw;\n";
        self::assertCount(1, banScanHtmlFile('web/index.html', $content, true));
        $content = "const h = escapeHtml(raw);\n";
        self::assertSame([], banScanHtmlFile('web/index.html', $content, true));
    }

    public function testFrontendPathScope(): void
    {
        self::assertTrue(banIsFrontendPath('web/src/a.vue'));
        self::assertTrue(banIsFrontendPath('sandadmin-artd/index.html'));
        self::assertFalse(banIsFrontendPath('web/package.json'));
        self::assertFalse(banIsFrontendPath('tools/x.ts'));
    }

    public function testParseDiffCollectsAddedLines(): void
    {
        $diff = <<<'DIFF'
            diff --git a/web/src/A.vue b/web/src/A.vue
            --- a/web/src/A.vue
            +++ b/web/src/A.vue
            @@ -10,0 +11,2 @@
            +<div v-html="x"></div>
            +ok
            DIFF;
        $added = banParseDiff($diff);
        self::assertTrue($added['web/src/A.vue'][11] ?? false);
        self::assertTrue($added['web/src/A.vue'][12] ?? false);
    }
}
