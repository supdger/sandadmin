【任务】合并 PR #104（SWE-2）
依据：results/9h-audit-merge-pr104-1119 本地 Opus 审计结论“可合并”，审计时 head = 10ac4b562faf0d45f69ae0f4fef4d845e2f80164（技术总监已复验通过）。
当前 head 以 10ac4b5 开头、gh pr checks 104 全绿、无冲突，才 gh pr merge 104 --repo supdger/sandadmin --merge（不删分支）；否则不合，写明。
交付：RESULT.md 写合并前 head、checks、合并结果和 main 新 SHA。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/10a-merge-pr104-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
