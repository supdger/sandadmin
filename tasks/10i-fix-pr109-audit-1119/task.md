【任务】按审计修 PR #109 后合并（SWE-2）
依据：~/agent-work/assistant/10f-audit-merge-pr109-1119/RESULT.md 结论“改后可合并”（审计时 head bc81663），逐条落实其“必须修改”各点（含 X5 安全日志下限改为与已定事项总表第 16 条一致的“6 个月（按自然月计）”等），不做审计未要求的改动。
在 docs/1x-design-standard 上加提交，跑 skill-sync 校验、naming_check.py notes、tools/pre-merge.sh --fast，普通 push。checks 全绿且无冲突后 gh pr merge 109 --repo supdger/sandadmin --merge（不删分支）。
交付：RESULT.md 写每条修改点的落实位置、检查结果、新 head、合并结果和 main 新 SHA。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/10i-fix-pr109-audit-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
