#!/usr/bin/env bash
# N3 验收：本地起 webman（端口限 19100–19199），断言 v1 API 与后台页面路由
# 的 7 项安全响应头。默认 CSP 灰度态，断言 Report-Only 头；
# 传 SAND_CSP_ENFORCE=true 则断言强制头。HTTP 起服，断言无 HSTS。
# 用法：tools/assert-security-headers.sh [端口]
set -uo pipefail

PORT="${1:-19107}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVER="$ROOT/server"
FAILED=0
STARTED=0

if [ "$PORT" -lt 19100 ] || [ "$PORT" -gt 19199 ]; then
    echo "FAIL\t端口 $PORT 不在 19100–19199 段" >&2
    exit 2
fi
if ! command -v curl >/dev/null 2>&1; then
    echo "FAIL\t缺少 curl" >&2
    exit 2
fi
if lsof -nP -i ":$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "FAIL\t端口 $PORT 已被占用" >&2
    exit 2
fi

stop_server() {
    if [ "$STARTED" = "1" ]; then
        (cd "$SERVER" && php start.php stop) >/dev/null 2>&1 || true
    fi
}
trap stop_server EXIT

# .env 缺失时 env() 全走默认值；SANDADMIN_SERVER_PORT 覆盖 listen 端口。
SANDADMIN_SERVER_PORT="$PORT" SANDADMIN_WEB_WORKERS=1 SANDADMIN_TASK_WORKERS=0 \
    php "$SERVER/start.php" start -d >/dev/null 2>&1
STARTED=1

READY=0
for _ in $(seq 1 50); do
    if curl -s -o /dev/null --max-time 2 "http://127.0.0.1:$PORT/" 2>/dev/null; then
        READY=1
        break
    fi
    sleep 0.2
done
if [ "$READY" != "1" ]; then
    echo "FAIL\t服务未在预期时间内监听 :$PORT" >&2
    exit 1
fi

# <描述> <头名> <期望值|EMPTY=必须缺席> <路径> <Accept>
check() {
    local label="$1" name="$2" want="$3" target="$4" accept="$5" got
    got="$(curl -s -D - -o /dev/null --max-time 5 -H "Accept: $accept" \
        "http://127.0.0.1:$PORT$target" \
        | tr -d '\r' | grep -i "^$name:" | head -1 | sed "s/^[^:]*:[[:space:]]*//")"
    if [ "$want" = "EMPTY" ]; then
        if [ -z "$got" ]; then
            echo "PASS\t$label\t$name 缺席（HTTP 下正确）"
        else
            echo "FAIL\t$label\t$name 应为空，实际 '$got'"
            FAILED=1
        fi
    elif [ "$got" = "$want" ]; then
        echo "PASS\t$label\t$name: $got"
    else
        echo "FAIL\t$label\t$name 期望 '$want'，实际 '${got:-<缺席>}'"
        FAILED=1
    fi
}

CSP_NAME="Content-Security-Policy-Report-Only"
CSP_API="default-src 'none'"
CSP_PAGE="default-src 'self'"
if [ "${SAND_CSP_ENFORCE:-false}" = "true" ]; then
    CSP_NAME="Content-Security-Policy"
fi

V1="/api/admin/v1/auth/password-policy"
PAGE="/"

for target in "$V1" "$PAGE"; do
    accept="application/json"
    [ "$target" = "$PAGE" ] && accept="text/html"
    check "$target" "X-Content-Type-Options" "nosniff" "$target" "$accept"
    check "$target" "X-Frame-Options" "DENY" "$target" "$accept"
    check "$target" "Referrer-Policy" "strict-origin-when-cross-origin" "$target" "$accept"
    check "$target" "Cross-Origin-Opener-Policy" "same-origin" "$target" "$accept"
    check "$target" "Permissions-Policy" "camera=(), microphone=(), geolocation=()" "$target" "$accept"
    check "$target" "Strict-Transport-Security" "EMPTY" "$target" "$accept"
done
# v1 JSON 响应取 csp.api；页面路由取 csp.page
check "$V1" "$CSP_NAME" "$CSP_API" "$V1" "application/json"
check "$PAGE" "$CSP_NAME" "$CSP_PAGE" "$PAGE" "text/html"

stop_server
STARTED=0

if [ "$FAILED" = "1" ]; then
    echo "== 安全响应头断言：存在 FAIL" >&2
    exit 1
fi
echo "== 安全响应头断言：全部 PASS"
