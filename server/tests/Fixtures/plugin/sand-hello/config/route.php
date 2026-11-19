<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源: server/plugin/sand-hello/contract/openapi.yaml
// 源哈希: sha256:c9bcacd3e31e2b0fba8e8fdeda7f46fd8d97cc7a872ff47704df0012f331c57c
// 生成器: contract-build v1

use Webman\Route;

require __DIR__ . '/route.generated.php';

Route::disableDefaultRoute('sandhello');
