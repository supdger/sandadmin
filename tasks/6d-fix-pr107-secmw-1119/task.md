【任务】修 PR #107 HTTP 安全中间件（SWE-2）
分支：PR #107 的分支（当前 head 977c396），在其上加提交，普通 push。
技术总监已定两点：
1. CSRF_REJECTED 准许补登进 contract/openapi.yaml 的 x-error-catalog；派生产物只用 php tools/contract-build 生成，不手改。
2. 生产环境必须配置 security.site_origin，未配置就拒绝启动（参照 ProductionDebugGuard 的做法）；开发期保持现在的行为。补对应测试。
全局挂载、按前缀自限作用域的做法保留。
改完重跑 phpunit unit、phpstan、php tools/contract-build --check、tools/pre-merge.sh --fast，命令、退出码、关键输出写进 RESULT.md，并写新 SHA。不合并。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/6d-fix-pr107-secmw-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
