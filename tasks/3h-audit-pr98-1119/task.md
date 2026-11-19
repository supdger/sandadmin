【任务】PR #98 合并前独立审计（只读，只写 RESULT.md）

前置：先确认 ~/agent-work/assistant/3g-pr98-agents-pointer-1119/status.log 里有 “done 3g-pr98-agents-pointer-1119”，且 docs/1x-skills 已含 AGENTS.md 指向 LEGACY-ABSORBED.md 的那句；没有就等 10 分钟再查，最多等 2 小时，仍没有就停下写明。

背景：老板 2026-11-19 定：原本要她批准的 PR，改由本地 Opus 5.5 审计，审计通过视为批准。PR #98（docs/1x-skills → main）把 1.x 开发规范整理成 skill，只改 .agents/skills/ 和 AGENTS.md；技术总监已验收内容。

模型分工：主代理只判断和写结论；读 diff、查文件、跑只读命令全部派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。

要审：
1. 改动范围只在 .agents/skills/、AGENTS.md（含 COVERAGE.md、LEGACY-ABSORBED.md），没有动 server/src、contract、门禁、CI。
2. 新 skill 与 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md、~/agent-work/grokbot/review-1117-r3/顶层架构设计-v4.md 没有冲突；未拍板事项只登记、没有自行拍板。
3. ~/.shared-skills 没被改动（与 restore-1119 的 diff-after 一致）。
4. CI 和 PR 检查状态（gh pr checks 98，只读）。
结论三选一：可合并、改后可合并（列必须修改点）、不可合并（列原因）。

禁止：不合并、不推送、不评论 PR、不改任何文件；不建库；不开下级会话；不用云端；不读不打印秘密。

交付：~/agent-work/assistant/3h-audit-pr98-1119/RESULT.md，第一行写结论。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
