【任务】解决 PR #105 与 main 的冲突后合并（SWE-2）
依据：~/agent-work/assistant/10d-audit-merge-pr105-1119/RESULT.md 结论“改后可合并”，唯一必改是与 main 的合并冲突；审计时 head e5e3774。
做法：在 feat/id-generator 上 git merge origin/main（不 rebase、不强推），解决冲突只做合并取舍、不改发号器逻辑；重跑 phpunit unit、phpstan、tools/pre-merge.sh --fast，普通 push。等 gh pr checks 105 全绿且无冲突后 gh pr merge 105 --repo supdger/sandadmin --merge（不删分支）；若解冲突需要改动发号器逻辑，就不合，写明。
交付：RESULT.md 写冲突文件、解决方式、检查结果、新 head、合并结果和 main 新 SHA。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/10h-fix-pr105-conflict-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
