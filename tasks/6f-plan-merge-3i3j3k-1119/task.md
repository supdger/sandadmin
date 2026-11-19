【任务】把 3i、3j、3k 合成 1.x 补设计计划（只写文档）
模型分工：主代理只判断和写结论；读文件、查资料全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
输入：~/agent-work/assistant/3i-design-async-concurrency-1119/RESULT.md、3j-design-standard-1119/RESULT.md、3k-audit-security-stability-1119/RESULT.md；PR #109 的清单 skill（分支 docs/1x-design-standard）；~/agent-work/grokbot/review-1117-r3/ 下的 v4 及同目录各稿；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md；~/agent-work/assistant/1b-core-breakdown-1119/RESULT.md。
要做：
1. 三份去重合并，得出一张总表，每行写：清单编号、缺口、1.0/1.1/不做、理由、以后补的代价、验收指标、人天、依赖、是否需要建库。
2. 3k 的 7 项高危全部放 1.0，每项给出落成 PR 的拆法和先后顺序。门禁闭环一项写明：CI 跑 PHPUnit 和 PHPStan、main 开分支保护（技术总监已定），按改动 CI 和仓库设置单列成 PR 和步骤。
3. 3i 的 7 个 PR 和 3k 的补项排成统一的 PR 队列：标出能并行的、必须串行的，以及哪些要等建库放行。
4. 框架核心分母（150–260 人天）加上净新增人天，重新给出完成率区间，只算已合并并验收的部分。
禁止：不改仓库。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/6f-plan-merge-3i3j3k-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
