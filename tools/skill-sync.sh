#!/usr/bin/env bash
# 校验仓库内 .agents/skills/ 副本与上游 ~/.shared-skills/ 同名 skill 是否一致。
#
# 上游是本机共享 skill 目录（各工具共用的权威副本），仓库副本用于随仓分发。
# 默认只做比对：全部一致 exit 0，任一 skill 不一致或缺失打印差异并 exit 1。
# --pull 用上游覆盖仓库副本，覆盖后重新校验，一致才 exit 0。

set -euo pipefail

UPSTREAM_DIR="${SKILL_SYNC_UPSTREAM:-$HOME/.shared-skills}"
SKILLS=(sandadmin1x-backend sandadmin1x-frontend sandadmin1x-plugin sandadmin1x-ai-loop)

# 仓库副本固定在 <仓库根>/.agents/skills/，与脚本位置无关。
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_SKILLS_DIR="$REPO_ROOT/.agents/skills"

# 生成目录清单：相对路径 + 逐文件 sha256，排序后可直接 diff。
# 排除 .DS_Store 和隐藏元数据，避免 macOS 噪声文件造成假不一致。
manifest() {
    local dir="$1"
    (cd "${dir}" && find . -type f ! -name '.DS_Store' ! -name '*.pyc' \
        | LC_ALL=C sort | xargs shasum -a 256)
}

pull() {
    local ok=1
    for skill in "${SKILLS[@]}"; do
        local src="$UPSTREAM_DIR/${skill}"
        local dst="$REPO_SKILLS_DIR/${skill}"
        if [[ ! -d "${src}" ]]; then
            echo "SKIP  ${skill}  上游不存在：${src}"
            ok=0
            continue
        fi
        mkdir -p "${dst}"
        rsync -a --delete --exclude '.DS_Store' --exclude '*.pyc' "${src}/" "${dst}/"
        echo "PULL  ${skill}  ${src} -> ${dst}"
    done
    [[ ${ok} -eq 1 ]]
}

check() {
    local ok=1
    for skill in "${SKILLS[@]}"; do
        local src="$UPSTREAM_DIR/${skill}"
        local dst="$REPO_SKILLS_DIR/${skill}"
        if [[ ! -d "${src}" ]]; then
            echo "FAIL  ${skill}  上游不存在：${src}"
            ok=0
            continue
        fi
        if [[ ! -d "${dst}" ]]; then
            echo "FAIL  ${skill}  仓库副本缺失：${dst}（用 --pull 拉取）"
            ok=0
            continue
        fi
        local diff_out
        diff_out="$(diff <(manifest "${src}") <(manifest "${dst}") || true)"
        if [[ -z "${diff_out}" ]]; then
            echo "OK    ${skill}  与上游一致"
        else
            echo "FAIL  ${skill}  与上游不一致（< 上游 / > 仓库副本）："
            echo "${diff_out}" | sed 's/^/      /'
            ok=0
        fi
    done
    [[ ${ok} -eq 1 ]]
}

case "${1:-}" in
    "")
        check
        ;;
    --pull)
        pull
        echo "---- 拉取后复核 ----"
        check
        ;;
    -h|--help)
        sed -n '2,7p' "${BASH_SOURCE[0]}"
        ;;
    *)
        echo "未知参数：$1（只支持 --pull）" >&2
        exit 2
        ;;
esac
