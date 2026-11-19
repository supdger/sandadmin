【任务】1.x「AI 改代码闭环」U-6 无库部分：模块测试规范与样例、保留上一版与回退命令

依据：v4 第 655 行 U-6（5–10 人天：模块测试规范与样例 1–2 + 接口测试从 OpenAPI 生成 2–3〔已并入审计表 #5，已验收〕+ 合并门禁脚本 0.5–1 + 保留上一版与回退命令 0.5–1 + 迁移回退脚本与「升级→回退→再升级」验证 1–3）；总表第 24 条「AI 改代码须自动测试，不过就不合并、打回重改，并保留可回退」。未验收。

输入（Mac 路径）：~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md（第 655 行、U-6 节第 2204 行起、第 524–531 行迁移不支持清单）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；supdger/sandadmin main 的 tools/pre-merge.sh、tools/build-release.sh、server/tests（只读起步）；PR #98 分支 docs/1x-skills 的 .agents/skills（尤其 sandadmin1x-ai-loop，只读）。

要做：
1. 模块测试规范与样例：以一个现有模块（如 server/src/Module/Dict 或 Announcement）为样板，补一套 Unit 层样例测试和一页规范（放 server/docs/），说明新模块必须带哪些测试。
2. 对照 U-6 核对 tools/pre-merge.sh 现有 7 步是否已满足「合并门禁脚本」，缺什么列在 RESULT，只补无库可做的缺项。
3. 保留上一版与回退命令：发布产物保留上一版，加回退命令（切回上一版产物，不碰数据库），带干跑模式和测试。
4. 不做：迁移回退脚本与「升级→回退→再升级」验证（要库），列为剩余。

仓库与分支：supdger/sandadmin，先 git fetch，从 origin/main（若 test-gate PR 已合并就基于它）开 feat/ai-loop-rollback，开工写明基线 SHA。

范围：server/tests 样例、server/docs/ 规范文档、tools/ 回退脚本、tools/build-release.sh、tools/pre-merge.sh 的必要接入。不改 server/src 业务代码、contract/openapi.yaml、CI。

禁止：不建库、不做 DDL、不写迁移文件、不跑迁移、不启任何数据库或 Redis（临时库、容器也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；不改 GitHub 仓库设置；本地服务只用 19100 到 19199 端口；不自行拍板未定事项（国际化、workflow 归属、MySQL 最低版本、双因素适用范围、CLA/DCO），碰到就停下列入 RESULT。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支、基线 SHA、最终 SHA）；任务目录 RESULT.md：样例与规范位置、pre-merge 对照结果、回退命令用法和测试、命令、退出码和关键输出（phpunit unit、phpstan、tools/pre-merge.sh --fast、naming_check、typephp、回退命令干跑）、剩余项（迁移回退验证）。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/3n-ai-loop-rollback-1119/，RESULT.md 写在这里；测试判定口径（2c）已完成，见 ~/agent-work/assistant/2c-test-gate-1119/RESULT.md，若它的 PR 未合并，基于 main 开分支并避免改同一段 tools/pre-merge.sh，冲突处在 RESULT 写明。
