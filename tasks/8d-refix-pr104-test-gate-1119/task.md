【任务】再修 PR #104 测试门禁（SWE-2）
分支：feat/test-gate。技术总监复验打回两处：
1. PR 上看不到 4a 的 8530ce2，head 还是 ee08b60，CI 仍红。先 git fetch 核实远端分支状态；8530ce2 若只在本地，就普通 push 上去（不强推）；推送后用 gh pr view 104 只读确认 head 已更新。
2. 4a 给 Dict、Lifecycle、Notification、Package 补的 Unit 测试用了 sqlite 内存库，违反“Unit 不连库、不启任何库”。改法：Unit 测试改用内存假实现（仓储接口的数组版、server/tests/Support 的工厂），不建任何库连接；必须走 SQL 的用例挪到 Integration 目录，等建库放行后再跑。PackageGateTest 不用改。
改完重跑 phpunit unit、phpstan、tools/pre-merge.sh --fast、check-module-tests、release self-check 第 6 步，命令、退出码、关键输出和新 SHA 写进 RESULT.md。不合并。

通用禁止：不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。
不建库、不启任何数据库。

任务目录是 ~/agent-work/assistant/8d-refix-pr104-test-gate-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
