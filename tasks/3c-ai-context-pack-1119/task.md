【任务】1.x AI 上下文打包脚本（独立审计新增表 #23），无库

依据：v4 第 1164 行「AI 上下文打包（对照 8 附带）：skill 给 OpenAPI/路由/权限摘要打包脚本」0.3–0.5 人天，未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 1164 行、U 章第 2144 行起）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md（第 21、22 条）；supdger/sandadmin main 的 contract/openapi.yaml、server/src/Contract/Generated、server/config/route.generated.php、tools/（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills/sandadmin1x-ai-loop（只读）。

要做：tools/ 下新增一个打包脚本，从契约和生成产物输出给 AI 读的摘要（接口清单、路由、权限码、错误码、MCP 工具表），控制大小，输出到仓库外或被忽略的目录；带一个最小测试或自检。skill 里引用这个脚本要等 PR #98 合并后再补，本任务不改 .agents/skills，在 RESULT 写好要加的一行。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main 开 feat/ai-context-pack，开工写明基线 SHA。

范围：只新增 tools/ 下脚本及其测试；如需加入 .gitattributes 的发布排除，只追加一行。不改 tools/pre-merge.sh、server/src、contract、.agents/skills、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：脚本用法、输出样例与大小、命令、退出码和关键输出（脚本运行、tools/pre-merge.sh --fast、naming_check）、待 #98 合并后补的 skill 行。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/3c-ai-context-pack-1119/，RESULT.md 写在这里。
