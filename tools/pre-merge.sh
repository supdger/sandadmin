#!/usr/bin/env bash
# 提交前本地快速门禁。用法：tools/pre-merge.sh [--fast|--full]
# --fast（默认）：PHP 语法检查 + 危险函数扫描 + PHPUnit Unit 套件 + PHPStan
# --full：在 fast 基础上追加 Integration 套件（需要 SAND_TEST_DB_URL）
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="${1:---fast}"

if [ "$MODE" != "--fast" ] && [ "$MODE" != "--full" ]; then
    echo "用法：$0 [--fast|--full]" >&2
    exit 2
fi

cd "$ROOT/server"

echo "== 1/4 PHP 语法检查（src/ app/ config/）"
find src app config -name '*.php' -print0 | xargs -0 -n 50 php -l > /dev/null

echo "== 2/4 危险函数白名单扫描"
php tests/security/scan-dangerous-functions.php

echo "== 3/4 PHPUnit Unit 套件"
vendor/bin/phpunit --testsuite unit

echo "== 4/4 PHPStan"
vendor/bin/phpstan analyse --no-progress

if [ "$MODE" = "--full" ]; then
    echo "== 附加：PHPUnit Integration 套件"
    vendor/bin/phpunit --testsuite integration
fi

echo "pre-merge ${MODE} 全部通过"
