#!/usr/bin/env bash
# strict types 与禁用写法检查（R-8，快档口径）：
#   检查 A：server/ 下 tracked PHP 文件头 10 行须有 declare(strict_types=1)
#           （兼容 shebang 或 <?php 占头行的情况）。
#   检查 B：禁用魔术方法定义 __get/__set/__call/__callStatic、eval(、extract(、
#           可变变量 $$。双引号字符串里的 "$$" 之类误报不逐条甄别，进基线即可。
# 范围：git ls-files 'server/*.php'，排除 vendor/ 与 plugin/（composer 产物，
#       非本仓源码权威）。
# 口径：违规集合 − tools/strict-types-baseline.txt = 新增违规，非空逐行打印并
#       exit 1；基线里已不对应任何违规的旧行打印 WARN，不判失败。
#       基线只许减不许加，新增条目须人审并登记到期日（M-09）。

set -uo pipefail

cd "$(dirname "$0")/.."

BASELINE=tools/strict-types-baseline.txt
CURRENT=$(mktemp)
BASE_SORTED=$(mktemp)
trap 'rm -f "$CURRENT" "$BASE_SORTED"' EXIT

list_files() {
  git ls-files 'server/*.php' | grep -vE '^server/(vendor|plugin)/'
}

# 逐文件产出「路径<TAB>规则名」，规则名与基线文件第二列一致。
check_file() {
  local file=$1
  if ! head -n 10 "$file" \
      | grep -qE 'declare[[:space:]]*\([[:space:]]*strict_types[[:space:]]*=[[:space:]]*1'; then
    printf '%s\t%s\n' "$file" 'no-strict-types'
  fi
  grep -qE '__(get|set|call|callStatic)' "$file" \
    && printf '%s\t%s\n' "$file" 'magic-method'
  # BSD grep 不认 \b，用「行首或非标识符字符」当词边界
  grep -qE '(^|[^[:alnum:]_])eval[[:space:]]*\(' "$file" \
    && printf '%s\t%s\n' "$file" 'eval-call'
  grep -qE '(^|[^[:alnum:]_])extract[[:space:]]*\(' "$file" \
    && printf '%s\t%s\n' "$file" 'extract-call'
  grep -qF '$$' "$file" \
    && printf '%s\t%s\n' "$file" 'variable-variable'
  return 0
}

while IFS= read -r file; do
  check_file "$file"
done < <(list_files) | sort -u > "$CURRENT"

if [ -f "$BASELINE" ]; then
  grep -vE '^[[:space:]]*($|#)' "$BASELINE" | sort -u > "$BASE_SORTED"
else
  echo "WARN: 基线文件不存在：${BASELINE}（全部违规按新增处理）" >&2
  : > "$BASE_SORTED"
fi

# comm 两列口径：只在违规里 = 新增；只在基线里 = 误留旧行。
NEW=$(comm -23 "$CURRENT" "$BASE_SORTED")
STALE=$(comm -13 "$CURRENT" "$BASE_SORTED")

if [ -n "$STALE" ]; then
  echo "$STALE" | while IFS= read -r line; do
    printf 'WARN: 基线误留旧行（已不违规，可删）：%s\n' "$line"
  done
fi

if [ -n "$NEW" ]; then
  echo "$NEW" | while IFS= read -r line; do
    printf 'FAIL: 新增违规 %s\n' "$line" >&2
  done
  echo "共 $(echo "$NEW" | wc -l | tr -d ' ') 条新增违规；确属误报/暂不能改的，" >&2
  echo "经人审后追加进 ${BASELINE}（格式：路径<TAB>规则名）。" >&2
  exit 1
fi

echo "strict types 与禁用写法检查通过（违规 $(wc -l < "$CURRENT" | tr -d ' ') 条，全部命中基线）"
exit 0
