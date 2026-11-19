【任务】合并第二批已通过本地 Opus 审计的 PR（SWE-2）

依据：老板 2026-11-19 定“本地 Opus 审计通过视为批准”；技术总监已验收。审计结论均为“可合并”：results/4d-audit-pr102-1119（#102）、4e-audit-pr100-1119（#100）、6a-audit-pr106-1119（#106）、6b-audit-pr108-1119（#108）、6c-audit-pr109-1119（#109）。

按 #100 → #102 → #106 → #108 → #109 的顺序逐个做，每个 PR：
1. 从对应审计 RESULT.md 取审计时的 head SHA；当前 head 与之不一致就不合该 PR，写明。
2. gh pr checks <n> 全绿、无冲突才 gh pr merge <n> --repo supdger/sandadmin --merge（不删分支）。前一个合入后若产生冲突，停下写明，不要自行解决。
禁止：不用 --admin，不绕过分支保护或检查，不强推，不改代码，不建库，不开下级会话，不用云端，不读不打印秘密。
交付：~/agent-work/assistant/7a-merge-audited-2-1119/RESULT.md，写每个 PR 的审计 head、合并前 head、checks、合并结果和 main 新 SHA。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
