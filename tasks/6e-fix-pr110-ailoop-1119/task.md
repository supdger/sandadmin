【任务】修 PR #110 AI 改代码闭环（SWE-2）
分支：feat/ai-loop-rollback（head d3e4783），在其上加提交，普通 push。
问题：DictServiceTest 用了 sqlite 内存库，违反不启任何库的规矩，也和本 PR 自己写的“Unit 不连库”规范矛盾；另外四个模块（Dict、Lifecycle、Notification、Package）的 Unit 测试已归另一任务（PR #104 的 4a）补，会撞车。
要做：从 #110 删掉 Dict 测试（及仅为它服务的改动）；其余部分（规范页、命名自查、发布登记、回退脚本）保留。重跑 phpunit unit、phpstan、pre-merge --fast，写进 RESULT.md。
注意：#104 和 #110 都改了 tools/pre-merge.sh，#110 要排在 #104 后面合；本任务不 rebase、不合并，只在 RESULT 写明这一点。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本（任务明说的除外）、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/6e-fix-pr110-ailoop-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
