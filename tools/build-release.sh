#!/usr/bin/env bash
# 生产发布包构建：git archive 干净导出 HEAD → 按 tools/dev-only-paths.txt
# 物理剔除开发专用代码 → server 下 composer install --no-dev → 打 tar.gz → 自检。
#
#   tools/build-release.sh [版本号]
#
# 版本号默认取 git describe --tags --always（在标签上即纯版本号）。
# vendor 打进包里：发布形态是开箱即用的编译制品（skill：生产环境不安装、
# 不编译），--no-dev 保证 phpunit/phpstan 等开发依赖不进包。
# 可重复执行：每次重建 dist/build-* 临时目录并覆盖同名 tar 包。
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
VERSION="${1:-$(git -C "$ROOT" describe --tags --always 2>/dev/null || date +%Y%m%d)}"
PKG="sandadmin-$VERSION"
WORK="$DIST/build-$PKG"
OUT="$DIST/$PKG.tar.gz"

echo "== 1/6 导出 HEAD 到 $WORK"
rm -rf "$WORK"
mkdir -p "$WORK"
git -C "$ROOT" archive --format=tar "--prefix=$PKG/" HEAD | tar -x -C "$WORK"
SRC="$WORK/$PKG"

echo "== 2/6 按清单剔除开发专用代码"
php "$ROOT/tools/strip-dev.php" "$SRC"

echo "== 3/6 composer install --no-dev --optimize-autoloader"
( cd "$SRC/server" && composer install --no-dev --optimize-autoloader --no-interaction )

# 骨架发布脚本会回填 windows.php、插件前端测试文件等，需按清单宽容复剔
php "$ROOT/tools/strip-dev.php" "$SRC" --reapply

echo "== 4/6 自检：清单路径不存在、无开发构建标记、无 .env/密钥残留"
php "$ROOT/tools/strip-dev.php" "$SRC" --verify
if find "$SRC" -name '.sand-dev-build' | grep -q .; then
    echo "FAIL 产物含 .sand-dev-build 标记" >&2
    exit 1
fi
if find "$SRC" -name '.env' -o -name '*.key' -o -name '*.pem' | grep -q .; then
    echo "FAIL 产物含 .env/密钥类文件" >&2
    exit 1
fi

echo "== 5/6 自检：PHP 语法与 dev-only 引用扫描"
find "$SRC" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0 \
    | xargs -0 -n 50 php -l > /dev/null
php "$ROOT/tools/check-dev-refs.php" --dir "$SRC"

echo "== 6/6 自检：生产启动守卫路径（ProductionDebugGuard）"
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

rm -f "$OUT"
COPYFILE_DISABLE=1 tar -czf "$OUT" -C "$WORK" "$PKG"
rm -rf "$WORK"
echo "OK $OUT"
