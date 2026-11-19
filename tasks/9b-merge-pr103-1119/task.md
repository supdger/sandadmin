【任务】合并 PR #103（SWE-2）
依据：老板定本地 Opus 审计通过视为批准；results/8b-audit-pr103-1119 结论“可合并”，审计 head ba4dca73277843e6470b1ba2708243bea00d8b11；技术总监已复验。
当前 head 与审计 head 一致、gh pr checks 103 全绿、无冲突，才 gh pr merge 103 --repo supdger/sandadmin --merge（不删分支）；否则不合，写明。不用 --admin。
交付：RESULT.md 写合并前 head、checks、合并结果和 main 新 SHA。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/9b-merge-pr103-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
