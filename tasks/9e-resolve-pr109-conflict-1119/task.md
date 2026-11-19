【任务】解决 PR #109 与 main 的冲突（SWE-2）
PR #109（docs/1x-design-standard@469caddb）在 #100/#102/#106/#108 合入后与 main 冲突，疑似 AGENTS.md。
做法：在 docs/1x-design-standard 上 git merge origin/main（不 rebase、不强推），解决冲突时保留 main 上已合入的内容，再把本 PR 对 AGENTS.md 的改动（指向 sandadmin1x-design-standard）补回；不改 .agents/skills 下 skill-sync 比对的 4 个目录。跑 skill-sync 校验、naming_check.py notes、tools/pre-merge.sh --fast，普通 push。
交付：RESULT.md 写冲突文件、解决方式、各检查结果和新 SHA。不合并。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/9e-resolve-pr109-conflict-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
