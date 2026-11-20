#!/usr/bin/env bash
# 基线只减不增闸：受保护文件相对 BASE 的行数不得增加。
#   tools/check-baseline-shrink.sh <BASE> [文件...]
# 缺省文件：server/phpstan-baseline.neon tools/strict-types-baseline.txt
#           naming-allow.txt
#
# 口径：
#   - BASE 侧 tools/pre-merge.sh blob 缺失 → 判失败（fail-closed：
#     证明不了闸已在 BASE 存在时不得放行，避免静默 bootstrap）。
#   - BASE 脚本存在但不含 strict-types-baseline 字样 → 本闸首次登记，
#     行数增长只 WARN 不拦；合并入 main 后 BASE 脚本带该字样，
#     之后一律硬 fail。
#   - 否则任一文件 AFTER 行数 > BEFORE 即失败。
set -uo pipefail

BASE="${1:-}"
if [ -z "$BASE" ]; then
    echo "用法：$0 <BASE> [文件...]" >&2
    exit 2
fi
shift
FILES=("$@")
if [ "${#FILES[@]}" -eq 0 ]; then
    FILES=(server/phpstan-baseline.neon tools/strict-types-baseline.txt naming-allow.txt)
fi

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if ! git rev-parse --verify "$BASE" >/dev/null 2>&1; then
    echo "FAIL 找不到对照 ref：${BASE}" >&2
    exit 1
fi
# fail-closed：BASE 存在但取不到 pre-merge.sh 本体时不能按「闸未登记」放行
BASE_SCRIPT="$(git show "$BASE:tools/pre-merge.sh" 2>/dev/null)" || {
    echo "FAIL BASE ${BASE} 侧缺少 tools/pre-merge.sh，无法判定基线闸口径" >&2
    exit 1
}
[ -n "$BASE_SCRIPT" ] || {
    echo "FAIL BASE ${BASE} 侧 tools/pre-merge.sh 为空，无法判定基线闸口径" >&2
    exit 1
}

BOOTSTRAP=0
if ! printf '%s' "$BASE_SCRIPT" | grep -q 'strict-types-baseline'; then
    BOOTSTRAP=1
    echo "基线闸首次落地，增长部分随本 PR 人审；合并入 main 后本检查生效"
fi

FAILED=0
for f in "${FILES[@]}"; do
    BEFORE=0
    if git cat-file -e "$BASE:$f" 2>/dev/null; then
        BEFORE=$(git show "$BASE:$f" | wc -l | tr -d ' ')
    fi
    AFTER=0
    if [ -f "$f" ]; then
        AFTER=$(wc -l < "$f" | tr -d ' ')
    fi
    if [ "$AFTER" -gt "$BEFORE" ]; then
        if [ "$BOOTSTRAP" -eq 1 ]; then
            echo "WARN: $f 行数 ${BEFORE} -> ${AFTER}（+$((AFTER - BEFORE))，首次登记不拦）"
        else
            echo "$f 行数 ${BEFORE} -> ${AFTER}（+$((AFTER - BEFORE))）"
            FAILED=1
        fi
    else
        echo "OK: $f 行数 ${BEFORE} -> ${AFTER}"
    fi
done

exit "$FAILED"
