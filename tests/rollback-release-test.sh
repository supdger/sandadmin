#!/usr/bin/env bash
# tools/rollback-release.sh 的自测：在临时目录造部署根与制品库 fixtures，
# 不碰仓库真实 dist/、不需要数据库。
# 运行：bash tests/rollback-release-test.sh
# 退出码：0 全部用例通过；1 有用例失败（输出各用例差异）。
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT="$REPO/tools/rollback-release.sh"
WORK="$(mktemp -d -t sandadmin-rollback-test)"
trap 'rm -rf "$WORK"' EXIT

PASSED=0
FAILED=0

# 造部署根：releases/<版本>/ + current 符号链接
make_root() {
    local root="$1" current="$2"
    shift 2
    mkdir -p "$root/releases"
    for version in "$@"; do
        mkdir -p "$root/releases/$version"
    done
    ln -sfn "releases/$current" "$root/current"
}

# 造制品库：releases.txt + previous + tar 包；sha256 参数可故意写错
make_dist() {
    local dist="$1" version="$2" recorded="$3"
    mkdir -p "$dist"
    echo "pkg-$version" > "$dist/sandadmin-$version.tar.gz"
    printf '%s\n' "$version" > "$dist/previous"
    printf '%s\t%s\t%s\t%s\n' "$version" "sandadmin-$version.tar.gz" "$recorded" '2026-11-20T00:00:00Z' \
        >> "$dist/releases.txt"
}

expect() {
    local name="$1" want="$2" got="$3"
    if [ "$want" = "$got" ]; then
        PASSED=$((PASSED + 1))
        echo "PASS $name"
    else
        FAILED=$((FAILED + 1))
        # 变量名后紧跟全角标点要加 {}，否则多字节首字节被并进变量名
        echo "FAIL ${name}：期望 ${want}，实际 ${got}"
    fi
}

run() {
    bash "$SCRIPT" "$@" >/dev/null 2>&1
    echo $?
}

# 1. --root --dry-run 不改指针
ROOT_A="$WORK/root-dry"
make_root "$ROOT_A" v2 v1 v2
expect "root dry-run 退出码" 0 "$(run --root "$ROOT_A" v1 --dry-run)"
expect "root dry-run 未改指针" "releases/v2" "$(readlink "$ROOT_A/current")"

# 2. --root 正常切换
ROOT_B="$WORK/root-switch"
make_root "$ROOT_B" v2 v1 v2
expect "root 切换退出码" 0 "$(run --root "$ROOT_B" v1)"
expect "root 切换后指针" "releases/v1" "$(readlink "$ROOT_B/current")"

# 3. --root 未给版本时取除当前外最新一版
ROOT_C="$WORK/root-auto"
make_root "$ROOT_C" v2 v1 v2
expect "root 自动取上一版退出码" 0 "$(run --root "$ROOT_C")"
expect "root 自动取上一版指针" "releases/v1" "$(readlink "$ROOT_C/current")"

# 4. 制品库无 previous 拒绝
DIST_A="$WORK/dist-noprev"
mkdir -p "$DIST_A"
expect "dist 无 previous 拒绝" 1 "$(run --dist "$DIST_A")"

# 5. --root 目标目录缺失拒绝
ROOT_D="$WORK/root-missing"
make_root "$ROOT_D" v2 v1 v2
expect "root 目标缺失拒绝" 1 "$(run --root "$ROOT_D" v9)"

# 6. dist 模式 sha256 不符拒绝
DIST_B="$WORK/dist-badsha"
make_dist "$DIST_B" v1 "0000000000000000000000000000000000000000000000000000000000000000"
expect "dist sha256 不符拒绝" 1 "$(run --dist "$DIST_B")"

# 7. current 已指向目标版本（重复回退）拒绝
ROOT_E="$WORK/root-repeat"
make_root "$ROOT_E" v1 v1 v2
expect "重复回退拒绝" 1 "$(run --root "$ROOT_E" v1)"

# 8. dist 模式校验通过并给出目标
DIST_C="$WORK/dist-ok"
mkdir -p "$DIST_C"
echo "pkg-v1" > "$DIST_C/sandadmin-v1.tar.gz"
printf 'v1\n' > "$DIST_C/previous"
SHA="$(shasum -a 256 "$DIST_C/sandadmin-v1.tar.gz" | awk '{print $1}')"
printf 'v1\tsandadmin-v1.tar.gz\t%s\t2026-11-20T00:00:00Z\n' "$SHA" > "$DIST_C/releases.txt"
expect "dist 校验通过退出码" 0 "$(run --dist "$DIST_C")"

# 8b. 同版重建追加两条记录时按最后一条校验
DIST_D="$WORK/dist-rebuilt"
mkdir -p "$DIST_D"
echo "pkg-v1-rebuilt" > "$DIST_D/sandadmin-v1.tar.gz"
printf 'v1\n' > "$DIST_D/previous"
SHA_LAST="$(shasum -a 256 "$DIST_D/sandadmin-v1.tar.gz" | awk '{print $1}')"
printf 'v1\tsandadmin-v1.tar.gz\t%s\t2026-11-19T00:00:00Z\n' \
    "0000000000000000000000000000000000000000000000000000000000000000" > "$DIST_D/releases.txt"
printf 'v1\tsandadmin-v1.tar.gz\t%s\t2026-11-19T01:00:00Z\n' "$SHA_LAST" >> "$DIST_D/releases.txt"
expect "同版两条记录取最后一条" 0 "$(run --dist "$DIST_D")"

# 9. --root 的 current 不是符号链接拒绝
ROOT_F="$WORK/root-notlink"
mkdir -p "$ROOT_F/releases/v1"
expect "current 非符号链接拒绝" 1 "$(run --root "$ROOT_F" v1)"

# 10. 参数错误退出码 2
expect "参数错误退出码" 2 "$(run --root)"

echo "----"
echo "通过 $PASSED 项，失败 $FAILED 项"
[ "$FAILED" = 0 ]
