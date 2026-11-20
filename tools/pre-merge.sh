#!/usr/bin/env bash
# 提交前本地快速门禁。用法：tools/pre-merge.sh [--fast|--full] [--typephp]
# --fast（默认）：模块测试目录检查 + PHP 语法检查 + 契约一致性 + 危险函数扫描 + 输入输出禁令扫描 + dev-only 引用门禁 + TypePHP 禁用写法扫描 + PHPUnit Unit 套件 + PHPStan
# --full：在 fast 基础上追加 Integration 套件（需要 SAND_TEST_DB_URL）+ 改动行覆盖率门禁（需要 pcov 或 xdebug）
# --typephp：追加 TypePHP 可选编译验证；找不到编译器或项目文件时跳过不阻塞
#   （ADR-23：编译门禁在工具链稳定前是可选的，不过不合并的硬门禁是其余几项）
# 菜单页 Playwright 冒烟不进本脚本，单列命令：cd web && pnpm test:smoke
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="--fast"
TYPEPHP=0
for arg in "$@"; do
    case "$arg" in
        --fast|--full) MODE="$arg" ;;
        --typephp) TYPEPHP=1 ;;
        *)
            echo "用法：$0 [--fast|--full] [--typephp]" >&2
            exit 2
            ;;
    esac
done

cd "$ROOT/server"

echo "== 1/9 模块测试目录检查（src/Module ↔ tests/{Unit,Integration}/Module）"
php ../tools/check-module-tests.php

echo "== 2/9 PHP 语法检查（src/ app/ config/）"
find src app config -name '*.php' -print0 | xargs -0 -n 50 php -l > /dev/null

echo "== 3/9 契约一致性（产物与 contract/openapi.yaml 逐字节比对）"
php ../tools/contract-build --check

echo "== 4/9 危险函数白名单扫描"
php tests/security/scan-dangerous-functions.php

echo "== 5/9 输入输出三条禁令扫描"
php tests/security/scan-input-bans.php

echo "== 6/9 dev-only 引用门禁（tools/dev-only-paths.txt）"
php ../tools/check-dev-refs.php

# 存量 --all 全量会报既有可变调用/动态 include（整改是另一项工作），
# 门禁先卡增量：只查未提交改动与相对 main 差异里新增的行（typephp.md「改到哪合规到哪」）。
echo "== 7/9 TypePHP 禁用写法扫描（diff 模式）"
php "$ROOT/tools/typephp_check.php"

echo "== 8/9 PHPUnit Unit 套件"
vendor/bin/phpunit --testsuite unit

echo "== 9/9 PHPStan"
vendor/bin/phpstan analyse --no-progress

if [ "$MODE" = "--full" ]; then
    echo "== 附加：PHPUnit Integration 套件"
    vendor/bin/phpunit --testsuite integration
    echo "== 附加：改动行覆盖率门禁（tools/test-gate.json）"
    php ../tools/check-diff-coverage.php
fi

if [ "$TYPEPHP" = "1" ]; then
    echo "== 附加：TypePHP 可选编译验证"
    # 查找顺序：TYPEPHP_BIN 环境变量 → PATH 里的 typephp → ~/agent-work/grokbot/webman-typephp 下可执行文件
    TYPEPHP_BIN_FOUND="${TYPEPHP_BIN:-}"
    if [ -z "$TYPEPHP_BIN_FOUND" ] || [ ! -x "$TYPEPHP_BIN_FOUND" ]; then
        TYPEPHP_BIN_FOUND="$(command -v typephp 2>/dev/null || true)"
    fi
    if [ -z "$TYPEPHP_BIN_FOUND" ] && [ -d "$HOME/agent-work/grokbot/webman-typephp" ]; then
        TYPEPHP_BIN_FOUND="$(find "$HOME/agent-work/grokbot/webman-typephp" -maxdepth 4 -type f \
            \( -name 'typephp' -o -name 'tpc' -o -name 'tpc.php' \) -perm +111 2>/dev/null | head -1 || true)"
    fi
    if [ -z "$TYPEPHP_BIN_FOUND" ]; then
        echo "未找到 TypePHP 编译器，跳过可选编译"
    else
        # tpc 需要一个 TypePHP 项目文件（webman-typephp 插件的 typephp:compile 生成，
        # 或手工提供并用 TYPEPHP_PROJECT 指定）；编译产物只进 .typephp/build/，不改任何源文件。
        TYPEPHP_PROJECT="${TYPEPHP_PROJECT:-$ROOT/server/.typephp/project.php}"
        if [ ! -f "$TYPEPHP_PROJECT" ]; then
            echo "已找到 TypePHP 编译器（${TYPEPHP_BIN_FOUND}），但没有项目文件（${TYPEPHP_PROJECT}），跳过可选编译"
        elif [[ "$TYPEPHP_BIN_FOUND" == *.php ]]; then
            php "$TYPEPHP_BIN_FOUND" "$TYPEPHP_PROJECT" --no-progress \
                || echo "WARN TypePHP 可选编译未通过（工具链技术预览期不阻塞门禁）"
        else
            "$TYPEPHP_BIN_FOUND" "$TYPEPHP_PROJECT" --no-progress \
                || echo "WARN TypePHP 可选编译未通过（工具链技术预览期不阻塞门禁）"
        fi
    fi
fi

# 命名规范自查：只查相对 origin/main 合并基线的新增行；
# 找不到基线（本地未取 origin/main）时跳过不误报。terms 需要仓内词表。
# 放在末尾是为避开未合并 PR 对前面步骤编号的改动区间；fail 即退出，
# 打不到「全部通过」，门禁效力不变。
echo "== 命名自查（diff 模式）"
NAMING_BASE="$(git -C "$ROOT" merge-base origin/main HEAD 2>/dev/null || true)"
if [ -z "$NAMING_BASE" ]; then
    echo "未找到 origin/main 合并基线，跳过命名自查"
else
    NAMING_DIFF="$(mktemp -t sandadmin-naming)"
    git -C "$ROOT" diff -U0 "$NAMING_BASE"...HEAD > "$NAMING_DIFF"
    python3 "$ROOT/tools/naming_check.py" code --allow "$ROOT/naming-allow.txt" --diff "$NAMING_DIFF"
    python3 "$ROOT/tools/naming_check.py" notes --diff "$NAMING_DIFF"
    python3 "$ROOT/tools/naming_check.py" terms "$ROOT/tools/naming-terms.tsv" --allow "$ROOT/naming-allow.txt" --diff "$NAMING_DIFF"
    rm -f "$NAMING_DIFF"
fi

echo "pre-merge ${MODE} 全部通过"
