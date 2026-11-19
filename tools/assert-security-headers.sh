#!/usr/bin/env bash
# N3 验收：本地起 webman（端口限 19100–19199，仅绑 127.0.0.1），断言 v1 API、
# SPA 页面路由与静态资源的 7 项安全响应头。默认 CSP 灰度态，断言 Report-Only 头；
# 传 SAND_CSP_ENFORCE=true 则断言强制头。HTTP 起服，断言无 HSTS。
# 用法：tools/assert-security-headers.sh [端口]
set -uo pipefail

PORT="${1:-19107}"
CHANNEL_PORT=$((PORT + 1))
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVER="$ROOT/server"
FAILED=0
STARTED=0

log() { # <PASS|FAIL> <目标> <说明>
    printf '%s\t%s\t%s\n' "$1" "$2" "$3"
}

# webman 起服会带 channel 进程（config/plugin/webman/channel/process.php），
# 端口走 SANDADMIN_CHANNEL_PORT：HTTP 口与 channel 口都要落在段内。
if [ "$PORT" -lt 19100 ] || [ "$PORT" -gt 19198 ]; then
    log FAIL "-" "端口 $PORT 需在 19100–19198（channel 口取 PORT+1）" >&2
    exit 2
fi
if ! command -v curl >/dev/null 2>&1; then
    log FAIL "-" "缺少 curl" >&2
    exit 2
fi
for p in "$PORT" "$CHANNEL_PORT"; do
    if lsof -nP -i ":$p" -sTCP:LISTEN >/dev/null 2>&1; then
        log FAIL "-" "端口 $p 已被占用，不会借用或顶替" >&2
        exit 2
    fi
done

# 只停掉本脚本在本目录启动的实例：核对该服务 pid 文件里的进程确实是 php。
stop_server() {
    if [ "$STARTED" != "1" ]; then
        return
    fi
    local pid_file="$SERVER/runtime/webman.pid" pid
    if [ -f "$pid_file" ]; then
        pid="$(head -1 "$pid_file" | tr -d '[:space:]')"
        if [ -n "$pid" ] && ps -p "$pid" -o comm= 2>/dev/null | grep -q php; then
            (cd "$SERVER" && php start.php stop) >/dev/null 2>&1 || true
        fi
    fi
}
trap stop_server EXIT

# .env 缺失时 env() 全走默认值；SANDADMIN_SERVER_LISTEN 覆盖监听并只绑回环。
# 启动命令成功即计清理责任：进程是我们起的，就绪超时退出也必须收尸；
# 启动失败则什么也没起来，直接退出不清场。
if ! SANDADMIN_SERVER_LISTEN="http://127.0.0.1:$PORT" \
    SANDADMIN_CHANNEL_PORT="$CHANNEL_PORT" \
    SANDADMIN_WEB_WORKERS=1 SANDADMIN_TASK_WORKERS=0 \
    php "$SERVER/start.php" start -d >/dev/null 2>&1; then
    log FAIL "-" "webman 启动命令失败" >&2
    exit 1
fi
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
    log FAIL "-" "服务未在预期时间内监听 127.0.0.1:$PORT" >&2
    exit 1
fi

# 段外监听断言：本实例（master+子孙进程）全部 TCP 监听口必须落在 19100–19199
master_pid="$(head -1 "$SERVER/runtime/webman.pid" 2>/dev/null | tr -d '[:space:]')"
if [ -z "$master_pid" ]; then
    log FAIL "listen" "读不到 webman master pid，无法断言段内监听"
    FAILED=1
else
    tree_pids="$master_pid"
    for child in $(pgrep -P "$master_pid" 2>/dev/null); do
        tree_pids="$tree_pids $child"
        for grand in $(pgrep -P "$child" 2>/dev/null); do
            tree_pids="$tree_pids $grand"
        done
    done
    listen_ok=1
    for port in $(lsof -nP -iTCP -sTCP:LISTEN -a -p "${tree_pids// /,}" 2>/dev/null \
        | awk '{print $9}' | grep -oE '[0-9]+$' | sort -u); do
        if [ "$port" -lt 19100 ] || [ "$port" -gt 19199 ]; then
            log FAIL "listen" "实例监听段外端口 ${port}（约束 19100–19199）"
            listen_ok=0
            FAILED=1
        fi
    done
    if [ "$listen_ok" = "1" ]; then
        log PASS "listen" "实例全部监听口均在 19100–19199（HTTP ${PORT} + channel ${CHANNEL_PORT}）"
    fi
fi

# <目标> <头名> <期望值|EMPTY=必须缺席> <路径> <Accept>
check() {
    local label="$1" name="$2" want="$3" target="$4" accept="$5" got
    got="$(curl -s -D - -o /dev/null --max-time 5 -H "Accept: $accept" \
        "http://127.0.0.1:$PORT$target" \
        | tr -d '\r' | grep -i "^$name:" | head -1 | sed "s/^[^:]*:[[:space:]]*//")"
    if [ "$want" = "EMPTY" ]; then
        if [ -z "$got" ]; then
            log PASS "$label" "$name 缺席（HTTP 下正确）"
        else
            log FAIL "$label" "$name 应为空，实际 '$got'"
            FAILED=1
        fi
    elif [ "$got" = "$want" ]; then
        log PASS "$label" "$name: $got"
    else
        log FAIL "$label" "$name 期望 '$want'，实际 '${got:-<缺席>}'"
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
STATIC_FILE="/favicon.ico"

check_target() { # <路径> <Accept> <CSP 期望值>
    local target="$1" accept="$2" csp="$3"
    check "$target" "X-Content-Type-Options" "nosniff" "$target" "$accept"
    check "$target" "X-Frame-Options" "DENY" "$target" "$accept"
    check "$target" "Referrer-Policy" "strict-origin-when-cross-origin" "$target" "$accept"
    check "$target" "Cross-Origin-Opener-Policy" "same-origin" "$target" "$accept"
    check "$target" "Permissions-Policy" "camera=(), microphone=(), geolocation=()" "$target" "$accept"
    check "$target" "Strict-Transport-Security" "EMPTY" "$target" "$accept"
    check "$target" "$CSP_NAME" "$csp" "$target" "$accept"
}

check_target "$V1" "application/json" "$CSP_API"
check_target "$PAGE" "text/html" "$CSP_PAGE"
check_target "$STATIC_FILE" "*/*" "$CSP_PAGE"

stop_server
STARTED=0

if [ "$FAILED" = "1" ]; then
    log FAIL "-" "安全响应头断言：存在 FAIL" >&2
    exit 1
fi
printf '== 安全响应头断言：全部 PASS\n'
