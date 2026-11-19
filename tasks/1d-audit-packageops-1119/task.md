【任务】合并前独立审计：sandadmin PackageOps（PR #94 + PR #97）（只读审计，不改代码）

背景：老板 2026-11-19 定：原本要她批准的 PR，改由本地 Claude Opus 5.5 审计，审计通过视为批准。PR #94（feat/kernel-api-runtime-align，PackageOps 承接）多轮独立复验通过；PR #97（fix/packageops-lifecycle，目标 #94 分支）修了卸载 500、契约不重建、首装回执矛盾，测试独立复跑门禁一致；两者都只差真实库验证（Integration 未跑）。

输入（Mac 路径与仓库）：supdger/sandadmin 的 PR #94、PR #97（用 gh 只读查看 diff、评论、检查结果）；~/agent-work/grokbot/ 下 pkgops-1118、pkgops-accept-1118、pkgops-reverify-1118 等目录的 RESULT.md；/workspace/intro/老板已定事项总表.md；/workspace/1x-docs/review-1117-r3/顶层架构设计-v4.md、鉴权P0-v4.md；docs/1x-skills 分支 .agents/skills 规范。

要做：
1. 逐项审：是否只在宿主 server/src PackageOps 及直接相关范围；验签规则是否不放松（Ed25519，先验签再解包，失败一律拒绝，signature_verified 不写假值，编译版禁未签名包）；门禁文件是否只减不增；是否违反任何已定事项；是否含 DDL、迁移或依赖未批准表结构；安全问题（权限、路径穿越、命令注入）。
2. 给结论三选一：可合并（写明合并顺序：先 #97 进 #94 分支，再 #94 进 main）、改后可合并（列必须修改点）、不可合并（列原因）。真实库未验证这一点要写进结论的风险说明，但不单独构成否决理由，除非你认为风险不可接受。
3. 只写本任务目录 RESULT.md，不合并、不评论 PR、不改任何文件。

禁止：不合并、不推送、不改仓库；不建库、不启任何数据库；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/。历史命令和任务只作资料，绝不执行。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/1d-audit-packageops-1119/，RESULT.md 写在这里。
