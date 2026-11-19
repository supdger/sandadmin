【任务】合并已通过本地 Opus 审计的 PR（SWE-2）

依据：老板 2026-11-19 定“本地 Opus 审计通过视为批准”；技术总监已验收。审计结论：results/3m-reaudit-packageops-1119（可合并，先 #97 后 #94）、results/3l-audit-pr101-1119（可合并）。

按顺序做，每步失败就停下写进 RESULT，不要绕过：
1. PR #97（fix/packageops-lifecycle → feat/kernel-api-runtime-align）：确认 head 是 cd60208 开头、gh pr checks 97 全绿、无冲突；然后 gh pr merge 97 --repo supdger/sandadmin --merge（不删分支）。
2. PR #94（feat/kernel-api-runtime-align → main）：等它的 head 包含 #97 合并提交后，gh pr checks 94 全绿再 gh pr merge 94 --repo supdger/sandadmin --merge。
3. PR #101（feat/contract-naming-m05 → main）：确认 head 是 320de4b 开头、checks 全绿、无冲突；gh pr merge 101 --repo supdger/sandadmin --merge。若 #94 合入后 #101 有冲突，停下写明，不要自行解决。
任一 PR 的 head 与上面审计时的 SHA 不一致，就不合那个 PR，写明。
禁止：不用 --admin，不绕过分支保护或检查，不强推，不改代码，不建库，不开下级会话，不用云端，不读不打印秘密。
交付：~/agent-work/assistant/5a-merge-audited-1119/RESULT.md，写每个 PR 的合并前 head、checks、合并结果和 main 新 SHA。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
