<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\SpaFallback;
use support\exception\PageNotFoundException;
use support\Request;
use support\Response;
use Webman\Context;

/** SPA 回退：非后端接口前缀（/api、/core）、非真实文件的 GET 返回 index.html；其余一律 404。 */
final class SpaFallbackTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/spafallback-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testGetPagePathServesIndex(): void
    {
        $response = $this->respond('GET', '/login');
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRootPathServesIndex(): void
    {
        $response = $this->respond('GET', '/');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testApiPathStillNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->respond('GET', '/api/admin/v1/nope');
    }

    public function testBareApiPathStillNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->respond('GET', '/api');
    }

    public function testCorePathStillNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->respond('GET', '/core/nope/available');
    }

    public function testBareCorePathStillNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->respond('GET', '/core');
    }

    public function testNonGetMethodsStillNotFound(): void
    {
        $caught = 0;
        foreach (['POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'] as $method) {
            try {
                $this->respond($method, '/login');
            } catch (PageNotFoundException) {
                $caught++;
            }
        }
        self::assertSame(5, $caught);
    }

    public function testMissingIndexStillNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $request = $this->request('GET', '/login');
        Context::reset(new \ArrayObject([\Webman\Http\Request::class => $request]));
        SpaFallback::serveIndex($request, $this->dir . '/index.html');
    }

    public function testIsSpaPathClassification(): void
    {
        self::assertTrue(SpaFallback::isSpaPath('GET', '/'));
        self::assertTrue(SpaFallback::isSpaPath('GET', '/login'));
        self::assertTrue(SpaFallback::isSpaPath('GET', '/system/users'));
        self::assertFalse(SpaFallback::isSpaPath('GET', '/api'));
        self::assertFalse(SpaFallback::isSpaPath('GET', '/api/admin/v1/users'));
        self::assertFalse(SpaFallback::isSpaPath('GET', '/core'));
        self::assertFalse(SpaFallback::isSpaPath('GET', '/core/captcha'));
        self::assertTrue(SpaFallback::isSpaPath('GET', '/corex'));
        self::assertFalse(SpaFallback::isSpaPath('HEAD', '/login'));
        self::assertFalse(SpaFallback::isSpaPath('OPTIONS', '/login'));
    }

    /** 真实 index.html 存在时按 method/path 走完整 respond；上下文供文件 304 判定读取。 */
    private function respond(string $method, string $path): Response
    {
        $index = $this->dir . '/index.html';
        if (!is_file($index)) {
            file_put_contents($index, '<html>spa</html>');
        }
        $request = $this->request($method, $path);
        Context::reset(new \ArrayObject([\Webman\Http\Request::class => $request]));

        return SpaFallback::serveIndex($request, $index);
    }

    private function request(string $method, string $path): Request
    {
        return new Request($method . ' ' . $path . " HTTP/1.1\r\nHost: example.test\r\n\r\n");
    }
}
