【任务】PR #105 合并前独立审计，通过即合并
依据：老板定本地 Opus 审计通过视为批准；PR #105 雪花发号器（含 env 基值 0 修复） 已由技术总监复验通过，CI 全绿。
模型分工：主代理只判断写结论；读 diff、跑只读命令和合并命令都派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
一、审计：范围与描述一致；安全与正确性；门禁文件只减不增；与 ~/agent-work/grokbot/plan-xref-1117/老板已定事项总表.md 不冲突；若有上一轮审计（results/8a、8c 下），核对其必改点已落实。RESULT 第一行写结论（可合并 / 改后可合并并列必须修改点 / 不可合并）和审计时 head。
二、只有结论为“可合并”、当前 head 以 e5e3774 开头、gh pr checks 105 全绿且无冲突时，才 gh pr merge 105 --repo supdger/sandadmin --merge（不删分支），记录 main 新 SHA；否则不合，写明原因。前一个 PR 合入后若产生冲突，停下写明。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/10d-audit-merge-pr105-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
