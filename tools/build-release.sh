#!/usr/bin/env bash
# 生产发布包构建：git archive 干净导出 HEAD → 按 tools/dev-only-paths.txt
# 物理剔除开发专用代码 → server 下 composer install --no-dev → vendor
# sand-core 与 plugin/sandadmin 载荷剔除 dev-login 链、第三方测试文件 →
# vendor 按 tools/strip-vendor-extra.php 名单剔除非运行文件（tests/docs/
# 示例/CI 元信息，autoload 覆盖路径豁免）→ web/ 前端构建并把 dist 并入
# server/public → 自检 → 打 tar.gz。
#
#   tools/build-release.sh [版本号] [--self-check]
#
# --self-check 跑完整剔除链与全部自检断言但不打 tar.gz、不产出制品，
# 供 CI 快档复用本脚本做发布门禁。
# 版本号默认取 git describe --tags --always（在标签上即纯版本号）。
# vendor 打进包里：发布形态是开箱即用的编译制品（skill：生产环境不安装、
# 不编译），--no-dev 保证 phpunit/phpstan 等开发依赖不进包。
# 可重复执行：每次重建 dist/build-* 临时目录并覆盖同名 tar 包。
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
SELF_CHECK=0
VERSION=""
for arg in "$@"; do
    case "$arg" in
        --self-check) SELF_CHECK=1 ;;
        *) VERSION="$arg" ;;
    esac
done
VERSION="${VERSION:-$(git -C "$ROOT" describe --tags --always 2>/dev/null || date +%Y%m%d)}"
PKG="sandadmin-$VERSION"
WORK="$DIST/build-$PKG"
OUT="$DIST/$PKG.tar.gz"

echo "== 1/8 导出 HEAD 到 $WORK"
rm -rf "$WORK"
mkdir -p "$WORK"
git -C "$ROOT" archive --format=tar "--prefix=$PKG/" HEAD | tar -x -C "$WORK"
SRC="$WORK/$PKG"

echo "== 2/8 按清单剔除开发专用代码"
php "$ROOT/tools/strip-dev.php" "$SRC"

echo "== 3/8 composer install --no-dev --optimize-autoloader"
( cd "$SRC/server" && composer install --no-dev --optimize-autoloader --no-interaction )

# 骨架发布脚本会回填 windows.php、插件前端测试文件等，需按清单宽容复剔
php "$ROOT/tools/strip-dev.php" "$SRC" --reapply

echo "== 4/8 vendor sand-core 与 plugin/sandadmin 载荷剔除 dev-login 链、第三方测试文件"
php "$ROOT/tools/strip-sand-core.php" "$SRC"
# 第三方包自带的测试文件（*.test.*、*.spec.*、__tests__、前端载荷内 tests/）
php "$ROOT/tools/strip-vendor-tests.php" "$SRC"
# vendor 非运行文件：tests/docs/示例/CI 元信息等按名单剔除，autoload 覆盖路径豁免
php "$ROOT/tools/strip-vendor-extra.php" "$SRC"
# 剔除后重建类映射，清掉指向已删文件的 optimized classmap 项
( cd "$SRC/server" && composer dump-autoload --no-dev --optimize --no-interaction )

echo "== 5/8 web/ 前端构建（剔除后源码 → dist → 并入 server/public）"
# 发布包必须带构建产物，Node/pnpm 不满足直接失败
node -e 'const [m,n]=process.versions.node.split(".").map(Number);process.exit(m>20||(m===20&&n>=19)?0:1)' \
    || { echo "FAIL 需要 Node ≥20.19（当前 $(node -v 2>/dev/null || echo 未安装)）" >&2; exit 1; }
command -v pnpm >/dev/null 2>&1 \
    || { echo "FAIL 需要 pnpm（web/package.json 固定 pnpm@12.16.1）" >&2; exit 1; }
( cd "$SRC/web" && pnpm install --frozen-lockfile )
# 哨兵剔除后再过一遍类型检查，挡住剔除留下的悬空引用
( cd "$SRC/web" && pnpm typecheck )
( cd "$SRC/web" && pnpm build )
# 产物是单行压缩文件，mcp/dev 相邻复合才算 dev 符号（.* 跨度误命中真业务词）
if grep -rilE 'dev[-_]?login|devEnter|devSurface|SAND-DEV|dev[-_]?mcp|mcp[-_]?dev|SAND_MCP' "$SRC/web/dist"; then
    echo "FAIL 前端构建产物含 dev 符号" >&2
    exit 1
fi
# webman 以 server/public 为静态根：index.html 落在根上，/ 即 SPA 登录入口
cp -R "$SRC/web/dist/." "$SRC/server/public/"
# msw postinstall 会往 public/ 重新铺 mockServiceWorker.js，一并清掉
rm -rf "$SRC/web/node_modules" "$SRC/web/dist" "$SRC/web/public/mockServiceWorker.js"

echo "== 6/8 自检：清单路径不存在、无开发构建标记、无 .env/密钥残留"
php "$ROOT/tools/strip-dev.php" "$SRC" --verify
if find "$SRC" -name '.sand-dev-build' | grep -q .; then
    echo "FAIL 产物含 .sand-dev-build 标记" >&2
    exit 1
fi
# sand-core vendor 树与载荷副本：dev-login 符号只许留在 tools/ 级文件
# （上游门禁脚本、清单自身）与载荷登记清单里
if find "$SRC/server/plugin/sandadmin" "$SRC/server/vendor/supdger/sand-core" \
        -type d -name tools -prune -o -type f \
        ! -name '.sand-core-payload-manifest.json' -print \
    | xargs grep -ilE 'dev[-_]?login' ; then
    echo "FAIL sand-core/载荷仍有 dev-login 符号残留" >&2
    exit 1
fi
# sandadmin-artd/.env 与 .env.development 由 vendor FrontendPublisher
# 发布前端源码时生成，只含构建期消费的 VITE_* 公开变量；发布语义是
# 包内不带任何 .env，自检前物理删除后按零残留断言
rm -f "$SRC/sandadmin-artd/.env" "$SRC/sandadmin-artd/.env.development"
if find "$SRC" -name '.env' -o -name '*.key' -o -name '*.pem' | grep -q .; then
    echo "FAIL 产物含 .env/密钥类文件" >&2
    exit 1
fi
# 第三方测试文件不得进包（剔除漏网的兜底断言）
left=$(find "$SRC" \( -name '*.test.*' -o -name '*.spec.*' -o -name '__tests__' \) -print)
if [ -n "$left" ]; then
    echo "FAIL 产物含第三方测试文件：" >&2
    echo "$left" >&2
    exit 1
fi
# vendor 非运行文件剔除的兜底：无名单内残留，且重建后的类映射
# 不指向任何已删文件（autoload 豁免口径误判的最后防线）
php "$ROOT/tools/strip-vendor-extra.php" "$SRC" --verify
php "$ROOT/tools/strip-vendor-extra.php" "$SRC" --check-classmap

echo "== 7/8 自检：PHP 语法与 dev-only 引用扫描"
find "$SRC" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0 \
    | xargs -0 -n 50 php -l > /dev/null
php "$ROOT/tools/check-dev-refs.php" --dir "$SRC"

echo "== 8/8 自检：生产启动守卫路径（ProductionDebugGuard）"
php -r '
$root = $argv[1];
require $root . "/server/vendor/autoload.php";
require $root . "/server/app/bootstrap/ProductionDebugGuard.php";
$guard = "app\\bootstrap\\ProductionDebugGuard";
$guard::assertSafe("production", false);
// 与守卫启动路径同口径：标记类在制品中应不存在
$dev = class_exists("Sandadmin\\Infra\\DevBuild") && Sandadmin\Infra\DevBuild::enabled();
if ($dev) {
    fwrite(STDERR, "FAIL 制品仍可解析开发构建标记\n");
    exit(1);
}
$guard::assertDevChainOff("production", $dev, getenv("SAND_DEV_LOGIN"), getenv("SAND_MCP_DEV_TOKEN"));
echo "PASS 生产启动守卫自检通过\n";
' "$SRC"

if [ "$SELF_CHECK" = "1" ]; then
    rm -rf "$WORK"
    # $ 变量名后紧跟全角字符时必须加大括号，否则 bash 把多字节首字节并进变量名
    echo "OK 自检通过（${PKG}，未产出制品）"
    exit 0
fi

rm -f "$OUT"
COPYFILE_DISABLE=1 tar -czf "$OUT" -C "$WORK" "$PKG"
rm -rf "$WORK"
echo "OK $OUT"
