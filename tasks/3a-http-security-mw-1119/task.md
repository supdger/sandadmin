【任务】1.x HTTP 安全中间件：安全响应头（N3）＋Origin/自定义请求头校验＋跨域白名单，无库

依据：v4 第 880–890 行 N3 安全响应头（f1 第 1119 行 0.5–1 人天）；~/agent-work/grokbot/1x-gap-review/缺口检查.md 第 652 行第 14 条「安全基线收尾：校验 Origin 加自定义请求头；……跨域白名单」（弱密码已由 #84 完成）。均未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 880–890 行）；同目录 鉴权P0-v4.md（A-5 CSRF 与 v1 组）；上述缺口检查.md；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 server/src/Http、server/config/middleware.php、server/config/security.php、server/app/middleware（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（只读）。

要做：
1. 安全响应头中间件：nosniff、X-Frame-Options DENY、Referrer-Policy、HSTS（只对 HTTPS，max-age 可配）、CSP（后台页面 default-src 'self'，纯 JSON API default-src 'none'，先 Report-Only 再强制，用配置开关切）、COOP same-origin、Permissions-Policy 收紧。Referrer-Policy 在 v4 写「单值待确认」，做成配置项，默认用 strict-origin-when-cross-origin，并在 RESULT 标「待总监确认」。
2. Origin 校验加自定义请求头：v1 组写请求校验 Origin 白名单并要求自定义头；与 A-5 CSRF 口径一致；会话认证还没实现，不要依赖它。
3. 跨域白名单：配置化，默认空（同源）。
4. 测试：中间件单测；附一个 curl -sI 断言脚本（本地起服务只用 19100 到 19199 端口），按 v4 N3 验收标准断言 7 个头。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/http-security-mw，开工写明基线 SHA。

范围：server/src/Http/Middleware 新中间件、server/config/middleware.php、server/config/security.php、server/tests、tools/ 下 curl 断言脚本。不改 contract/openapi.yaml（若需登记新错误码，停下回报）、门禁白名单、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：各头的取值和配置键、Origin 与跨域规则、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp、curl 断言脚本输出）、待总监确认项、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/3a-http-security-mw-1119/，RESULT.md 写在这里。
