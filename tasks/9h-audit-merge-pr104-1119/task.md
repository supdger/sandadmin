【任务】PR #104 合并前独立审计，通过即合并
依据：老板定本地 Opus 审计通过视为批准；PR #104 测试门禁（feat/test-gate） 已由技术总监复验通过，CI 全绿。
模型分工：主代理只判断写结论；读 diff、跑只读命令和最后的合并命令都派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
一、审计：范围与描述一致；安全与正确性；门禁文件只减不增；与 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md 不冲突；重点看 Unit 测试里没有任何库连接（含 sqlite 内存库），覆盖率阈值 80%。 RESULT 第一行写结论（可合并 / 改后可合并并列必须修改点 / 不可合并）和审计时 head。
二、只有结论为“可合并”、当前 head 以 8530ce2 开头、gh pr checks 104 全绿且无冲突时，才执行 gh pr merge 104 --repo supdger/sandadmin --merge（不删分支），记录 main 新 SHA；否则不合，写明原因。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/9h-audit-merge-pr104-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
