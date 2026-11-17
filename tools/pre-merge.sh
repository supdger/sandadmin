#!/usr/bin/env bash
# 合并前本地门禁：与 .github/workflows/merge-gates*.yml 同一套命令。
# 任何一项不过即非零退出；全部通过才允许合并。
#
# 用法：
#   tools/pre-merge.sh          # 同 --fast（与 sand-core 一致）
#   tools/pre-merge.sh --fast   # 快档：PHPStan + strict types + deptrac + 命名
#                               #       + 基线只减不增 + 前端三道 + PHPUnit unit
#                               #       套件（不需要数据库）
#   tools/pre-merge.sh --full   # 慢档口径：--fast 全部内容 + PHPUnit 全套
#                               #       （需先配库，见下）
#   PRE_MERGE_DB=pgsql tools/pre-merge.sh --full
#
# 数据库由环境变量配置（本脚本不启动容器、不建库）：
#   PRE_MERGE_DB            --full 必填：pgsql | mysql80 | mysql84
#   PRE_MERGE_DB_HOST       默认 127.0.0.1
#   PRE_MERGE_DB_PORT       默认按库种：pgsql=5432，mysql80=3306，mysql84=3306
#   PRE_MERGE_DB_NAME       默认 sand_test
#   PRE_MERGE_DB_USER       默认 postgres（pgsql）/ root（mysql*）
#   PRE_MERGE_DB_PASSWORD   默认空
#   PRE_MERGE_SCHEMA_DIR    可选：指向一目录 .sql，按文件名序应用到目标库
#                           （pgsql 走 psql，mysql* 走 mysql 客户端），用于
#                           在专用测试库上铺基线；应用失败即判失败。
#   PRE_MERGE_BASE          命名 diff 检查的对照 ref，默认 origin/main。
#
# 现状提醒：集成测试的 SAND_TEST_DB_URL 只认 pgsql://，mysql* 档位下
# 数据库用例会如实报错（方言层未落地，CI 同档位标记为已知失败）；
# pgsql 指向空库同样报错而非跳过——先用 PRE_MERGE_SCHEMA_DIR 铺基线
# （0001–0003 基线迁移尚未随仓发布，需从基线产物目录取）。
#
# 依赖：php + composer（server/vendor 已装）、python3、pnpm（web/ 存在时）、
#       psql 或 mysql 客户端（仅在配了 PRE_MERGE_SCHEMA_DIR 时需要）。

set -uo pipefail

MODE=fast
case "${1:-}" in
  --full)    MODE=full ;;
  ""|--fast) MODE=fast ;;
  *) echo "未知参数：${1}（只认 --fast / --full）" >&2; exit 2 ;;
esac

cd "$(dirname "$0")/.."
ROOT=$(pwd)
FAILED=0

step() {
  printf '\n===== %s =====\n' "$1"
}

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  FAILED=1
}

# ---------- 1. PHPStan level max ----------
step "PHPStan level max"
if [ ! -x server/vendor/bin/phpstan ]; then
  fail "server/vendor 未安装，先跑 cd server && composer install"
else
  (cd server && vendor/bin/phpstan analyse --no-progress) || fail "phpstan"
fi

# ---------- 2. deptrac 依赖方向 ----------
step "deptrac 依赖方向（内核不依赖插件层）"
if [ ! -x server/vendor/bin/deptrac ]; then
  fail "deptrac 未安装，先跑 cd server && composer install"
elif [ ! -d server/plugin ]; then
  fail "server/plugin 未发布（composer install 的 sand:publish 会生成）"
else
  (cd server && vendor/bin/deptrac analyse --config-file=deptrac.yaml) || fail "deptrac"
fi

# ---------- 3. strict types 与禁用写法 ----------
step "strict types 与禁用写法"
tools/check-strict-types.sh || fail "check-strict-types"

# ---------- 4. 命名自查（diff 口径） ----------
step "命名自查（diff：${PRE_MERGE_BASE:-origin/main}...HEAD）"
BASE=${PRE_MERGE_BASE:-origin/main}
if ! git rev-parse --verify "$BASE" >/dev/null 2>&1; then
  fail "找不到对照 ref：${BASE}（先 git fetch，或用 PRE_MERGE_BASE 指定）"
else
  DIFF=$(git diff -U0 "$BASE"...HEAD)
  echo "$DIFF" | python3 tools/naming_check.py code --allow naming-allow.txt --diff - \
    || fail "naming_check code"
  echo "$DIFF" | python3 tools/naming_check.py notes --diff - \
    || fail "naming_check notes"
  echo "$DIFF" | python3 tools/naming_check.py terms tools/naming-terms.tsv --allow naming-allow.txt --diff - \
    || fail "naming_check terms"
  MIGRATIONS=$(git diff --name-only --diff-filter=AM "$BASE"...HEAD -- 'server/database/migrations/*.sql' || true)
  if [ -n "$MIGRATIONS" ]; then
    # shellcheck disable=SC2086
    python3 tools/naming_check.py sql --allow naming-allow.txt $MIGRATIONS || fail "naming_check sql"
  fi

  # 基线只减不增：对照 BASE 行数不得变多；BASE 侧不存在按 0 行计。
  # bootstrap 语义（与快档 CI 同一条规则）：BASE 侧 merge-gates.yml
  # grep 不到 baseline-shrink = 本闸首次登记，行数增长随 PR 人审，
  # 只 WARN 不拦；门禁进了 main 之后一律硬 fail。
  step "基线只减不增（对照 ${BASE}）"
  BASE_GATE=$(git show "$BASE:.github/workflows/merge-gates.yml" 2>/dev/null || true)
  BOOTSTRAP=0
  if ! printf '%s' "$BASE_GATE" | grep -q 'baseline-shrink'; then
    BOOTSTRAP=1
    echo "基线闸首次落地，增长部分随本 PR 人审；合并入 main 后本检查生效"
  fi
  for f in server/phpstan-baseline.neon tools/strict-types-baseline.txt naming-allow.txt; do
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
        fail "基线只减不增：$f"
      fi
    else
      echo "OK: $f 行数 ${BEFORE} -> ${AFTER}"
    fi
  done
fi

# ---------- 5. 前端三道（web/ 存在才跑） ----------
if [ -d web ]; then
  step "前端：pnpm install --frozen-lockfile"
  (cd web && pnpm install --frozen-lockfile) || fail "pnpm install"
  step "前端：tsc --noEmit"
  (cd web && pnpm typecheck) || fail "tsc --noEmit"
  step "前端：eslint"
  (cd web && pnpm lint) || fail "eslint"
  step "前端：vitest run"
  (cd web && pnpm test) || fail "vitest"
fi

# ---------- 6. PHPUnit ----------
if [ "$MODE" = fast ]; then
  # 快档只跑 unit 套件：不需要数据库，三库矩阵/集成用例归 --full。
  step "PHPUnit unit 套件（--fast，无需数据库）"
  if [ ! -x server/vendor/bin/phpunit ]; then
    fail "server/vendor 未安装，先跑 cd server && composer install"
  else
    (cd server && vendor/bin/phpunit --testsuite unit) || fail "phpunit --testsuite unit"
  fi
else
step "PHPUnit（--full 全套，需配库）"
if [ -z "${PRE_MERGE_DB:-}" ]; then
  cat >&2 <<'EOF'
未配置数据库：设 PRE_MERGE_DB=pgsql|mysql80|mysql84 后重跑。
例：PRE_MERGE_DB=pgsql PRE_MERGE_DB_PORT=15432 \
    PRE_MERGE_DB_USER=postgres PRE_MERGE_DB_PASSWORD=<口令> tools/pre-merge.sh
可选 PRE_MERGE_SCHEMA_DIR 先铺基线再测。
EOF
  fail "PRE_MERGE_DB 未设置"
else
  case "$PRE_MERGE_DB" in
    pgsql)   DEF_PORT=5432; DEF_USER=postgres; SCHEME=pgsql ;;
    mysql80) DEF_PORT=3306; DEF_USER=root;     SCHEME=mysql ;;
    mysql84) DEF_PORT=3306; DEF_USER=root;     SCHEME=mysql ;;
    *) fail "PRE_MERGE_DB 只接受 pgsql|mysql80|mysql84（当前：${PRE_MERGE_DB}）"; SCHEME="" ;;
  esac
  if [ -n "$SCHEME" ]; then
    DB_HOST=${PRE_MERGE_DB_HOST:-127.0.0.1}
    DB_PORT=${PRE_MERGE_DB_PORT:-$DEF_PORT}
    DB_NAME=${PRE_MERGE_DB_NAME:-sand_test}
    DB_USER=${PRE_MERGE_DB_USER:-$DEF_USER}
    DB_PASS=${PRE_MERGE_DB_PASSWORD:-}
    export SAND_TEST_DB_URL="${SCHEME}://${DB_USER}${DB_PASS:+:${DB_PASS}}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
    echo "SAND_TEST_DB_URL=${SCHEME}://${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}（口令不回显）"

    if [ -n "${PRE_MERGE_SCHEMA_DIR:-}" ]; then
      if [ ! -d "$PRE_MERGE_SCHEMA_DIR" ]; then
        fail "PRE_MERGE_SCHEMA_DIR 不是目录：$PRE_MERGE_SCHEMA_DIR"
      else
        if [ "$SCHEME" = pgsql ]; then
          CLIENT=(env "PGPASSWORD=$DB_PASS" psql -v ON_ERROR_STOP=1
                  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME")
        else
          CLIENT=(mysql --protocol=tcp -h "$DB_HOST" -P "$DB_PORT"
                  -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME")
        fi
        for f in "$PRE_MERGE_SCHEMA_DIR"/*.sql; do
          echo "应用 $f"
          "${CLIENT[@]}" < "$f" || fail "迁移应用失败：$f"
        done
      fi
    fi

    (cd server && vendor/bin/phpunit) || fail "phpunit"
  fi
fi
fi

step "结果"
if [ "$FAILED" -eq 0 ]; then
  echo "全部门禁通过"
else
  echo "存在未过项，见上面 FAIL 标记" >&2
fi
exit "$FAILED"
