#!/usr/bin/env bash
# 回退到上一版制品：只动「制品指针/制品校验」，不碰数据库、不执行
# 迁移回退脚本、不启停服务（v4 U-6 / M-07：生产回退 = 切旧制品 +
# 按需恢复备份）。
#
#   tools/rollback-release.sh --root <部署目录> [版本号] [--dry-run]
#   tools/rollback-release.sh --dist <制品目录> [--dry-run]
#
# --root：部署目录内含 releases/<版本>/ 与 current 符号链接；把
#   current 原子地重指到目标版本。版本号可省略——取 releases/ 里除
#   当前版外修改时间最新的一版。结束只打印提示，重启/重载按运维流程。
# --dist：制品库目录（默认仓库 dist/），读 previous 得上一版版本号，
#   校验 sandadmin-<版本>.tar.gz 存在且 sha256 与 releases.txt 登记
#   一致，打印回退目标；此模式不切换任何文件。
# --dry-run：只打印将执行的动作，不落任何改动。
#
# 退出码：0 成功；1 无可回退版本/制品缺失/sha256 不符/目录形状不对；
# 2 参数错误。
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROOT_DIR=""
DIST_DIR=""
TARGET=""
DRY_RUN=0

usage() {
    sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'
}

fail() {
    echo "FAIL $*" >&2
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        --root)
            [ $# -ge 2 ] || { echo "--root 缺目录" >&2; exit 2; }
            ROOT_DIR="$2"; shift 2 ;;
        --dist)
            [ $# -ge 2 ] || { echo "--dist 缺目录" >&2; exit 2; }
            DIST_DIR="$2"; shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        --help|-h) usage; exit 0 ;;
        --*) echo "未知参数：$1" >&2; usage >&2; exit 2 ;;
        *)
            if [ -n "$TARGET" ]; then
                echo "版本号只能给一个" >&2
                exit 2
            fi
            TARGET="$1"; shift ;;
    esac
done

if [ -n "$ROOT_DIR" ] && [ -n "$DIST_DIR" ]; then
    echo "--root 与 --dist 只能选一个" >&2
    exit 2
fi
if [ -z "$ROOT_DIR" ] && [ -n "$TARGET" ]; then
    echo "版本号只在 --root 模式下有效" >&2
    exit 2
fi
if [ -z "$ROOT_DIR" ] && [ -z "$DIST_DIR" ]; then
    DIST_DIR="$REPO/dist"
fi

if [ -n "$DIST_DIR" ]; then
    # 制品库模式：只定位并校验回退目标，不切换任何文件
    [ -d "$DIST_DIR" ] || fail "制品目录不存在：$DIST_DIR"
    PREV_FILE="$DIST_DIR/previous"
    [ -f "$PREV_FILE" ] || fail "没有可回退的上一版记录：$PREV_FILE"
    VERSION="$(head -n 1 "$PREV_FILE" | tr -d '[:space:]')"
    [ -n "$VERSION" ] || fail "上一版记录为空：$PREV_FILE"
    PKG="$DIST_DIR/sandadmin-$VERSION.tar.gz"
    [ -f "$PKG" ] || fail "上一版制品不存在：$PKG"
    # 同版重建会追加新行，盘上 tar 对应最后一次登记，取最后一条
    RECORDED="$(awk -v v="$VERSION" '$1 == v {rec = $3} END {print rec}' "$DIST_DIR/releases.txt" 2>/dev/null || true)"
    [ -n "$RECORDED" ] || fail "releases.txt 未登记版本 $VERSION"
    ACTUAL="$(shasum -a 256 "$PKG" | awk '{print $1}')"
    [ "$ACTUAL" = "$RECORDED" ] || fail "sha256 不符：releases.txt 登记 ${RECORDED}，实际 $ACTUAL"
    [ "$DRY_RUN" = 1 ] && echo "[dry-run] 不切换文件，仅校验并打印回退目标"
    echo "回退目标制品：$PKG"
    echo "版本：$VERSION  sha256：$ACTUAL"
    echo "PASS 制品校验通过；部署侧请按运维流程切换并重启/重载服务"
    exit 0
fi

# 部署根模式：原子地把 current 重指到上一版
[ -d "$ROOT_DIR/releases" ] || fail "部署目录缺 releases/：$ROOT_DIR"
[ -L "$ROOT_DIR/current" ] || fail "current 不是符号链接：$ROOT_DIR/current"
POINTED="$(readlink "$ROOT_DIR/current")"
case "$POINTED" in
    releases/*|"$ROOT_DIR"/releases/*) ;;
    *) fail "current 未指向 releases/ 内版本：$POINTED" ;;
esac
CURRENT_VERSION="$(basename "$POINTED")"

if [ -z "$TARGET" ]; then
    # 未指定版本：取除当前版外修改时间最新的一版
    TARGET=""
    while IFS= read -r dir; do
        name="$(basename "$dir")"
        if [ "$name" != "$CURRENT_VERSION" ]; then
            TARGET="$name"
            break
        fi
    done < <(ls -dt "$ROOT_DIR"/releases/*/ 2>/dev/null || true)
fi
[ -n "$TARGET" ] || fail "releases/ 里没有可回退的其他版本"
[ "$TARGET" != "$CURRENT_VERSION" ] || fail "current 已指向 $TARGET，无可回退"
[ -d "$ROOT_DIR/releases/$TARGET" ] || fail "回退目标目录不存在：$ROOT_DIR/releases/$TARGET"

echo "当前版本：$CURRENT_VERSION"
echo "回退目标：$TARGET"
if [ "$DRY_RUN" = 1 ]; then
    echo "[dry-run] 将把 current 重指到 releases/$TARGET"
    exit 0
fi

# 先建临时链接再原子改名：BSD mv 会跟随 current 指向的目录把
# 链接移进去，rename(2) 才是替换符号链接本身，经 php 调（本仓必装）
TMP_LINK="$ROOT_DIR/.current.new.$$"
ln -sfn "releases/$TARGET" "$TMP_LINK"
if ! php -r 'rename($argv[1], $argv[2]) || exit(1);' "$TMP_LINK" "$ROOT_DIR/current"; then
    rm -f "$TMP_LINK"
    fail "current 重指到 releases/$TARGET 失败"
fi
echo "OK current → releases/$TARGET"
echo "请按运维流程重启/重载服务；本脚本不碰数据库、不执行迁移回退"
