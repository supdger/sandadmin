#!/usr/bin/env bash
# tools/check-baseline-shrink.sh 的自测：在临时 git 仓库里造 BASE 侧
# pre-merge.sh 有无/有无闸标记的 fixtures，不碰仓库真实历史。
# 运行：bash tests/pre-merge-baseline-test.sh
# 退出码：0 全部用例通过；1 有用例失败（输出各用例差异）。
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT_SRC="$REPO/tools/check-baseline-shrink.sh"
WORK="$(mktemp -d -t sandadmin-baseline-test)"
trap 'rm -rf "$WORK"' EXIT

PASSED=0
FAILED=0

expect() {
    local name="$1" want="$2" got="$3"
    if [ "$want" = "$got" ]; then
        PASSED=$((PASSED + 1))
        echo "PASS $name"
    else
        FAILED=$((FAILED + 1))
        echo "FAIL ${name}：期望 ${want}，实际 ${got}"
    fi
}

# 在指定仓库目录里跑脚本，返回退出码
run_in() {
    local dir="$1"
    shift
    bash "$dir/tools/check-baseline-shrink.sh" "$@" >/dev/null 2>&1
    echo $?
}

# 造仓库：$1=目录，拷入被测脚本并 git init
make_repo() {
    local dir="$1"
    mkdir -p "$dir/tools"
    cp "$SCRIPT_SRC" "$dir/tools/check-baseline-shrink.sh"
    git -C "$dir" init -q
    git -C "$dir" config user.email test@example.com
    git -C "$dir" config user.name test
}

commit_all() {
    local dir="$1" msg="$2"
    git -C "$dir" add -A
    git -C "$dir" commit -qm "$msg"
    git -C "$dir" rev-parse HEAD
}

# ── 仓库 A：BASE 完全没有 tools/pre-merge.sh（fail-closed 用例）──
A="$WORK/repo-a"
make_repo "$A"
printf 'line1\n' > "$A/tools/strict-types-baseline.txt"
BASE_NO_SCRIPT=$(commit_all "$A" "base without pre-merge.sh")

# 工作区把基线文件加一行（未提交，AFTER 读工作区）
printf 'line1\nline2\n' > "$A/tools/strict-types-baseline.txt"
expect "BASE 缺 pre-merge.sh 判失败（fail-closed）" 1 "$(run_in "$A" "$BASE_NO_SCRIPT" tools/strict-types-baseline.txt)"
expect "BASE ref 不存在判失败" 1 "$(run_in "$A" deadbeefdeadbeef tools/strict-types-baseline.txt)"

# ── 仓库 B：BASE 有 pre-merge.sh 但无闸标记（bootstrap 用例）──
B="$WORK/repo-b"
make_repo "$B"
printf '#!/usr/bin/env bash\necho old\n' > "$B/tools/pre-merge.sh"
printf 'line1\n' > "$B/tools/strict-types-baseline.txt"
BASE_BOOTSTRAP=$(commit_all "$B" "bootstrap base")
printf 'line1\nline2\n' > "$B/tools/strict-types-baseline.txt"
expect "bootstrap：基线增长只 WARN 不拦" 0 "$(run_in "$B" "$BASE_BOOTSTRAP" tools/strict-types-baseline.txt)"
printf 'line1\n' > "$B/tools/strict-types-baseline.txt"
expect "bootstrap：行数不增放行" 0 "$(run_in "$B" "$BASE_BOOTSTRAP" tools/strict-types-baseline.txt)"

# ── 仓库 C：BASE 的 pre-merge.sh 含闸标记（硬 fail 用例）──
printf '#!/usr/bin/env bash\n# tools/strict-types-baseline.txt 只减不增\n' > "$B/tools/pre-merge.sh"
BASE_GATE=$(commit_all "$B" "gate base")
printf 'line1\nline2\n' > "$B/tools/strict-types-baseline.txt"
expect "闸已生效：基线增长判失败" 1 "$(run_in "$B" "$BASE_GATE" tools/strict-types-baseline.txt)"
printf 'line1\n' > "$B/tools/strict-types-baseline.txt"
expect "闸已生效：行数不增放行" 0 "$(run_in "$B" "$BASE_GATE" tools/strict-types-baseline.txt)"

echo
echo "通过 ${PASSED}，失败 ${FAILED}"
[ "$FAILED" -eq 0 ]
