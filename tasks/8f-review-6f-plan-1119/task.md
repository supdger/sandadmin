【任务】以架构评审身份复审 1.x 补设计计划（只读，只写 RESULT.md）
依据：技术总监 2026-11-19 交办；老板定审计在本地用 Opus 做。
模型分工：主代理只判断写结论；读文件、对照资料全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
输入：~/agent-work/assistant/6f-plan-merge-3i3j3k-1119/RESULT.md（被审稿）；原稿 ~/agent-work/assistant/3i-design-async-concurrency-1119/RESULT.md、3j-design-standard-1119/RESULT.md（含 gap-table.md）、3k-audit-security-stability-1119/RESULT.md；PR #109 的高标准清单 skill（supdger/sandadmin main 上 .agents/skills/sandadmin1x-design-standard/，若未合并则读分支 docs/1x-design-standard）；~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md。
要做：
1. 对照三份原稿和清单逐项出差异表，逐行判“通过 / 要改 / 缺项”，写理由和依据行号。
2. 缺项一律打回；检查 3k 七项高危是否全在 1.0、PR 拆法和顺序是否可执行、并行/串行/等建库标注是否正确、人天去重是否站得住、完成率口径是否只算已合并且有同范围独立验收。
3. 对照 Go、Java 一流框架对标要求（老板 2026-11-19 定）看有无漏项。
4. 最多两轮：本轮给出结论；若“要改”，列出修改清单，供 6f 作者改后第二轮只看差异表。
结论第一行三选一：通过、改后通过（列必须修改点）、打回（列缺项）。
禁止：不改任何文件、不建库、不开下级会话、不用云端、不读秘密。
交付：~/agent-work/assistant/8f-review-6f-plan-1119/RESULT.md。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
