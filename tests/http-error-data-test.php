<?php

declare(strict_types=1);

/**
 * v1 信封静态契约断言（开发用；不随包发布）。
 *
 *   php tests/http-error-data-test.php
 *
 * 原断言对象是已退役的 sandadmin-artd HTTP 层；v1 契约统一后改扫
 * web/ 前端与 server/ 后端的真实信封实现：
 *
 *   1. 后端成功信封单点（ApiOutcome::ok / JsonSuccessResponse）输出
 *      {code:"OK", message:"操作成功", data, request_id}；
 *   2. 后端模块侧不再散落手写成功信封（除两个单点外无 {data, request_id}
 *      成功体）；
 *   3. 前端 adminClient 成功判定只看 code === 'OK'，
 *      "存在 data 即成功"的旧宽松判定已移除；
 *   4. 前端业务错误仍把 code/message/fields/request_id 映射进
 *      AdminApiError（data 透传语义不破坏）；
 *   5. v1 mock okV1 与契约信封一致，测试与实现不脱节。
 */

namespace {
    $root = dirname(__DIR__);
    $files = [
        'adminClient' => $root . '/web/src/lib/adminClient.ts',
        'okV1' => $root . '/web/src/mocks/handlers/_v1.ts',
        'apiOutcome' => $root . '/server/src/Http/ApiOutcome.php',
        'jsonSuccess' => $root . '/server/src/Http/JsonSuccessResponse.php',
    ];
    foreach ($files as $name => $file) {
        if (!is_file($file)) {
            fwrite(STDERR, "[FAIL] 源文件不存在：{$file}\n");
            exit(1);
        }
    }
    $adminClient = (string) file_get_contents($files['adminClient']);
    $okV1 = (string) file_get_contents($files['okV1']);
    $apiOutcome = (string) file_get_contents($files['apiOutcome']);
    $jsonSuccess = (string) file_get_contents($files['jsonSuccess']);

    $assert = static function (bool $cond, string $message): void {
        if (!$cond) {
            fwrite(STDERR, "[FAIL] {$message}\n");
            exit(1);
        }
        echo "[PASS] {$message}\n";
    };
    $has = static fn (string $haystack, string $needle): bool => str_contains($haystack, $needle);

    // 1. 后端成功信封单点形状
    $assert($has($apiOutcome, "'code' => ErrorCodeRegistry::OK"),
        'ApiOutcome::ok 输出成功码 OK（来自注册表常量）');
    $assert($has($apiOutcome, "'message' => self::OK_MESSAGE")
        && $has($apiOutcome, "private const OK_MESSAGE = '操作成功'"),
        'ApiOutcome::ok 输出固定文案「操作成功」');
    $assert($has($apiOutcome, '\'data\' => $data') && $has($apiOutcome, '\'request_id\' => $requestId'),
        'ApiOutcome::ok 保留 data 与 request_id');
    $assert($has($apiOutcome, '\'code\' => $code') && $has($apiOutcome, '\'fields\' => $fields'),
        'ApiOutcome::fail 保留失败信封 {code, message, fields, request_id}');
    $assert($has($jsonSuccess, '\'code\' => ErrorCodeRegistry::OK')
        && $has($jsonSuccess, '\'data\' => $payload'),
        'JsonSuccessResponse 同步输出 OK 成功信封');

    // 2. 模块侧不再散落手写成功信封：除单点外 server/src、app 无 {data, request_id} 成功体
    $leaks = [];
    foreach (['server/src', 'server/app'] as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!($file instanceof SplFileInfo) || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if (str_ends_with($path, 'ApiOutcome.php') || str_ends_with($path, 'JsonSuccessResponse.php')) {
                continue;
            }
            $content = (string) file_get_contents($path);
            if (preg_match("/'data'\\s*=>[^\\n]+'request_id'\\s*=>/", $content)) {
                $leaks[] = substr($path, strlen($root) + 1);
            }
        }
    }
    $assert($leaks === [], 'src/app 无散落 {data, request_id} 手写成功信封'
        . ($leaks === [] ? '' : '：' . implode('、', $leaks)));

    // 3. 前端成功判定只看 code === 'OK'
    $assert(substr_count($adminClient, ".code !== 'OK'") >= 2,
        'adminClient 请求与上传路径都按 code===OK 判成功');
    $assert(!$has($adminClient, "'data' in payload"),
        'adminClient 已移除「存在 data 即成功」的旧宽松判定');

    // 4. 失败信封映射不破坏（code/message/fields/request_id 进 AdminApiError）
    $assert($has($adminClient, 'code: errPayload.code')
        && $has($adminClient, 'fields: errPayload.fields')
        && $has($adminClient, 'requestId,'),
        'adminClient 失败信封仍把 code/message/fields/request_id 透传进 AdminApiError');
    $assert($has($adminClient, "errPayload.message || i18n.t('errors.system.message')"),
        'adminClient 失败文案兜底逻辑保持');

    // 5. v1 mock 与契约一致
    $assert($has($okV1, "code: 'OK'") && $has($okV1, "'操作成功'"),
        'okV1 mock 输出 {code:OK, message:操作成功, data, request_id}');

    echo "ALL HTTP-ERROR-DATA ASSERTIONS PASSED\n";
}
