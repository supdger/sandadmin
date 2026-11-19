【任务】PR #110 跟上 main（SWE-2）
前置：PR #104 必须已合并（gh pr view 104 只读确认 state 为 MERGED）。未合并就什么都不做，RESULT 写“等 #104”并结束。
做法：在 feat/ai-loop-rollback 上 git merge origin/main（不 rebase、不强推），解决与 #104 在 tools/pre-merge.sh 等处的冲突，保留两边意图；重跑 phpunit unit、phpstan、tools/pre-merge.sh --fast、check-module-tests，普通 push。
交付：RESULT.md 写冲突、解决方式、检查结果和新 SHA。不合并。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/9j-rebase-pr110-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
