【任务】PR #110 小改、审计、合并
依据：技术总监复验 10b 小改后通过，改完直接审计和合并；老板定本地 Opus 审计通过视为批准。
模型分工：主代理只做审计判断；改文件、跑命令、合并全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
一、小改（feat/ai-loop-rollback）：
1. server/docs/module-tests.md 第 23 行和第 30 到 32 行删掉“Unit 可用 sqlite 内存库”的说法，改成：Unit 只用 tests/Support 的工厂、Fake 和 Disconnected，要走 SQL 的用例一律放 Integration。
2. git merge origin/main（不 rebase、不强推），跟上 #104、#107 合入后的 main。
3. 重跑 phpunit unit、phpstan、tools/pre-merge.sh --fast、bash tests/rollback-release-test.sh，普通 push。
二、审计：范围与描述一致；Unit 无任何库连接；门禁文件只减不增；与已定事项总表不冲突。RESULT 第一行写结论和审计时 head。
三、结论为“可合并”、gh pr checks 110 全绿且无冲突时，gh pr merge 110 --repo supdger/sandadmin --merge（不删分支），记录 main 新 SHA；否则不合，写明原因。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/10j-fix-audit-merge-pr110-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
