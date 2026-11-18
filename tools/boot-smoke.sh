#!/usr/bin/env bash
# 真实启动冒烟门禁：把 webman 真正起起来再打 HTTP 断言，堵住
# 「单测/静态检查全绿但 start 即崩」这类缺口（PR #91 事故，#92 才修复）。
#
#   tools/boot-smoke.sh --dev | --prod | --all [--tar 包路径] [--timeout 秒]
#
#   --dev   在 server/ 源码检出直接起（php start.php start）
#   --prod  先跑 tools/build-release.sh 产出 tar.gz，解到临时目录后起
#   --all   两种形态各起一次（pre-merge --full 用）
#   --tar   复用已有发布包，跳过重新构建
#   --timeout 端口就绪与单步 curl 的总预算（默认 20 秒；--fast 档由
#           pre-merge 传 15 控制整段冒烟 ≤30 秒）
#
# 进程与端口规矩：
#   - 只杀自己拉起的 PID（trap EXIT/INT/TERM 兜底），不按进程名杀；
#   - 端口让 OS 随机分空闲端口，避开黑名单（8787/2206/3006/27xxx 段等，
#     撞了重取），就绪前最多重试 3 个端口；
#   - 临时目录、PID 文件、解开的发布包全在 mktemp -d 里，退出即清。
#
# 不依赖数据库：脚本不建 .env 也不读它；server/.env 存在时直接拒绝跑，
# 避免误连真实库。无库时依赖 DB 才能到的判定（v1 未登录 401 语义等）
# 以 SKIP/NOTE 形式写明，不算 FAIL。
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE=""
TIMEOUT=20
TAR_PATH=""
while [ $# -gt 0 ]; do
    case "$1" in
        --timeout) TIMEOUT="$2"; shift 2 ;;
        --tar) TAR_PATH="$2"; shift 2 ;;
        --dev|--prod|--all) MODE="$1"; shift ;;
        *) echo "未知参数：$1" >&2; exit 2 ;;
    esac
done
MODE="${MODE:---dev}"

# 占用/保留端口黑名单：默认端口、历史占用段与既知冲突端口
port_allowed() {
    case "$1" in
        8787|2206|3006|24879|24206|24937|24511|24512|24513|24613) return 1 ;;
    esac
    [ "$1" -ge 27000 ] && [ "$1" -le 27999 ] && return 1
    return 0
}

pick_port() {
    local port
    for _ in $(seq 1 40); do
        port=$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1]); s.close()')
        if port_allowed "$port"; then
            echo "$port"
            return 0
        fi
    done
    echo "FAIL 取不到不在黑名单内的空闲端口" >&2
    return 1
}

TMP="$(mktemp -d /tmp/boot-smoke.XXXXXX)"
SERVER_PID=""
PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0
FAILED_ITEMS=""

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill -TERM "$SERVER_PID" 2>/dev/null || true
        for _ in $(seq 1 40); do
            kill -0 "$SERVER_PID" 2>/dev/null || break
            sleep 0.25
        done
        kill -KILL "$SERVER_PID" 2>/dev/null || true
    fi
    rm -rf "$TMP"
}
trap cleanup EXIT INT TERM

report() { # 状态 项目 [细节]
    local status="$1" item="$2" detail="${3:-}"
    case "$status" in
        PASS) PASS_COUNT=$((PASS_COUNT + 1)) ;;
        FAIL) FAIL_COUNT=$((FAIL_COUNT + 1)); FAILED_ITEMS="$FAILED_ITEMS $item" ;;
        SKIP) SKIP_COUNT=$((SKIP_COUNT + 1)) ;;
    esac
    if [ -n "$detail" ]; then
        printf '%-4s %s（%s）\n' "$status" "$item" "$detail"
    else
        printf '%-4s %s\n' "$status" "$item"
    fi
}

# http_code <方法> <url> → 打印状态码，body 写 $TMP/body
http_code() {
    curl -s -o "$TMP/body" -m 5 -w '%{http_code}' -X "$1" "$2" || true
}

# 起一次 webman 并跑全部检查。$1=形态名 $2=server 目录 $3..=追加 env（VAR=VAL）
run_form() {
    local form="$1" dir="$2"
    shift 2
    local log="$TMP/stdout-$form.log"
    local port channel_port attempt started
    started=$SECONDS
    SERVER_PID=""

    if [ -f "$dir/.env" ]; then
        report FAIL "$form 启动" "server/.env 存在，冒烟一律不碰真实库配置，请先移除再跑"
        return
    fi

    # 端口就绪探测用 favicon.ico（任何形态都存在，不依赖前端构建产物）
    local ready=0
    for attempt in 1 2 3; do
        port=$(pick_port) || return 1
        channel_port=$(pick_port) || return 1
        (
            cd "$dir" && exec env -u SAND_DEV_LOGIN -u SAND_MCP_DEV_TOKEN \
                SANDADMIN_SERVER_PORT="$port" \
                SANDADMIN_CHANNEL_PORT="$channel_port" \
                SANDADMIN_WEB_WORKERS="${BOOT_SMOKE_WEB_WORKERS:-1}" \
                SANDADMIN_TASK_WORKERS=1 \
                "$@" \
                php start.php start
        ) >"$log" 2>&1 &
        SERVER_PID=$!
        local deadline=$((SECONDS + TIMEOUT))
        while [ "$SECONDS" -lt "$deadline" ]; do
            if ! kill -0 "$SERVER_PID" 2>/dev/null; then
                break
            fi
            if [ "$(http_code GET "http://127.0.0.1:$port/favicon.ico")" = "200" ]; then
                ready=1
                break
            fi
            sleep 0.3
        done
        [ "$ready" = "1" ] && break
        # 本轮没起来：收掉进程（可能是端口竞争输掉），换端口再来
        echo "-- 第 $attempt 次启动未就绪，末段输出：" >&2
        tail -5 "$log" >&2 || true
        cleanup_one
    done

    if [ "$ready" != "1" ]; then
        report FAIL "$form 端口就绪" "3 次尝试均未在 ${TIMEOUT}s 内监听起来"
        tail -20 "$log" >&2 || true
        return
    fi
    local base="http://127.0.0.1:$port"
    echo "-- ${form} 已就绪：端口 ${port}（channel ${channel_port}），就绪耗时 $((SECONDS - started))s"

    # a. 进程还活着
    if kill -0 "$SERVER_PID" 2>/dev/null; then
        report PASS "$form 进程存活"
    else
        report FAIL "$form 进程存活"
        return
    fi

    # b. SPA 页面：server/public/index.html 存在才严格断言（生产包必有；
    # 源码检出未构建 web/ 时前端由 vite dev server 提供，此处降级 SKIP）
    if [ -f "$dir/public/index.html" ]; then
        local spa_ok=1
        for path in / /login; do
            if [ "$(http_code GET "$base$path")" != "200" ] || ! grep -q 'id="root"' "$TMP/body"; then
                spa_ok=0
            fi
        done
        if [ "$spa_ok" = "1" ]; then
            report PASS "$form SPA 页面" "/ 与 /login 返回含 id=root 挂载点的 HTML"
        else
            report FAIL "$form SPA 页面" "/ 或 /login 未返回前端页面"
        fi
    else
        report SKIP "$form SPA 页面" "public/index.html 不存在，源码检出未构建 web/；已验证 / → $(http_code GET "$base/")"
    fi

    # c. 健康检查：仓内无通用 /healthz；systemUpdate/probe 是回环+随机挑战的
    # 私有探针，无挑战按设计 404，以此确认路由注册与拒绝行为
    local probe_code
    probe_code=$(http_code GET "$base/app/sandpackage/systemUpdate/probe")
    if [ "$probe_code" = "404" ]; then
        report PASS "$form 探针端点" "无挑战令牌按设计 404；仓内无通用 health 端点"
    else
        report FAIL "$form 探针端点" "期望 404 实得 $probe_code"
    fi

    # d. 契约路由未登录：v1 路由在无库环境走到的是 INTERNAL_ERROR 信封
    # （鉴权依赖会话存储/DB，401 语义未测——依赖 DB），此处断言统一信封
    # 且 code 非成功值；另测插件 CheckLogin 路由拿到真实 401 码
    local route env_ok=1
    for route in /api/admin/v1/tasks /api/admin/v1/files /api/admin/v1/announcements; do
        http_code GET "$base$route" >/dev/null
        if ! grep -q '"code":"[A-Z_]*"' "$TMP/body" || grep -q '"code":"OK"' "$TMP/body"; then
            env_ok=0
        fi
    done
    if [ "$env_ok" = "1" ]; then
        report PASS "$form 契约路由非成功信封" "3 条 v1 路由均回 {code,message,request_id} 且非 OK"
    else
        report FAIL "$form 契约路由非成功信封"
    fi
    http_code GET "$base/core/system/user" >/dev/null
    if grep -q '"code":401' "$TMP/body"; then
        report PASS "$form 未登录拒绝" "/core/system/user 未登录返回 code=401"
    else
        report FAIL "$form 未登录拒绝" "期望 code=401，实得 $(head -c 80 "$TMP/body")"
    fi
    report SKIP "$form v1 未登录 401 语义" "依赖 DB 会话存储，无库时 v1 路由只能走到 INTERNAL_ERROR"

    # e. 仅生产形态：开发登录/开发 MCP/SSE 演示端点必须全部 404
    if [ "$form" = "prod" ]; then
        local gone=1 spec verb path code
        for spec in \
            "GET /api/auth/dev-login/available" \
            "GET /api/admin/v1/auth/dev-login/available" \
            "POST /api/auth/dev-login" \
            "POST /api/admin/v1/auth/dev-login" \
            "POST /api/admin/mcp" \
            "GET /api/dev/stream/ping" \
            "GET /api/dev/stream/sse" \
            "GET /core/dev-login/available" \
            "POST /core/dev-login"; do
            verb="${spec%% *}"
            path="${spec#* }"
            code=$(http_code "$verb" "$base$path")
            if [ "$code" != "404" ]; then
                gone=0
                echo "   未 404：$verb $path → $code" >&2
            fi
        done
        if [ "$gone" = "1" ]; then
            report PASS "$form dev-only 端点剔除" "9 条开发端点全部 404"
        else
            report FAIL "$form dev-only 端点剔除"
        fi
    fi

    # f. runtime 日志无 Fatal/Uncaught（task_worker 无库初始化失败属受控重试，
    # 不匹配该模式；#92 前的装载期 Fatal 会命中）
    if grep -rEl 'Fatal error|Uncaught|PHP Fatal' "$dir/runtime/logs/" "$log" 2>/dev/null | grep -q .; then
        report FAIL "$form 运行日志无 Fatal" "$(grep -rEl 'Fatal error|Uncaught|PHP Fatal' "$dir/runtime/logs/" "$log" 2>/dev/null | head -3 | tr '\n' ' ')"
    else
        report PASS "$form 运行日志无 Fatal"
    fi

    echo "-- $form 冒烟耗时 $((SECONDS - started))s"
    cleanup_one
}

cleanup_one() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill -TERM "$SERVER_PID" 2>/dev/null || true
        for _ in $(seq 1 40); do
            kill -0 "$SERVER_PID" 2>/dev/null || break
            sleep 0.25
        done
        kill -KILL "$SERVER_PID" 2>/dev/null || true
    fi
    SERVER_PID=""
}

run_dev() {
    run_form dev "$ROOT/server"
}

run_prod() {
    local tar="$TAR_PATH"
    if [ -z "$tar" ]; then
        echo "== 生产形态：先构建发布包"
        bash "$ROOT/tools/build-release.sh"
        tar=$(ls -t "$ROOT/dist/"sandadmin-*.tar.gz 2>/dev/null | head -1)
    fi
    if [ -z "$tar" ] || [ ! -f "$tar" ]; then
        report FAIL "prod 发布包" "找不到 $tar"
        return
    fi
    tar -xzf "$tar" -C "$TMP"
    local pkg_dir
    pkg_dir=$(find "$TMP" -maxdepth 1 -type d -name 'sandadmin-*' | head -1)
    # 生产语义：APP_ENV=production + APP_DEBUG=false 起（制品无 dev 标记，
    # 守卫应放行）；不设 SAND_DEV_LOGIN/SAND_MCP_DEV_TOKEN（env -u 兜底）
    run_form prod "$pkg_dir/server" APP_ENV=production APP_DEBUG=false
}

case "$MODE" in
    --dev) run_dev ;;
    --prod) run_prod ;;
    --all) run_dev; run_prod ;;
esac

echo "== 冒烟汇总：PASS=$PASS_COUNT FAIL=$FAIL_COUNT SKIP=$SKIP_COUNT"
if [ "$FAIL_COUNT" -gt 0 ]; then
    echo "== 未过项：$FAILED_ITEMS" >&2
    exit 1
fi
echo "== boot-smoke 通过"
