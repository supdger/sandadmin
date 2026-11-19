【任务】修 PR #100 发版自检失败（SWE-2）

仓库与分支：supdger/sandadmin，feat/mcp-business-tools，在 927b7f3 上加提交（本机已有工作副本 ~/agent-work/grokbot/mcp-dev-1117/repo，先 git fetch 确认头是 927b7f3）。

背景：CI 的 release self-check 挂了（main 上是绿的）。原因是 server/src/Infra/Mcp/Biz/OrgActorSource.php 第 18、21 行注释里写了 dev-only 类名 DevPrincipal，被发版自检扫到。

要做：把 OrgActorSource.php 注释里对 DevPrincipal 的引用改写成不点名的描述（比如“与开发期身份同一判定口径”），代码不变；全仓 server/src 生产代码再查一遍有没有别的 dev-only 引用。本地跑 php tools/check-dev-refs.php（或 release self-check 第 7 步对应的脚本），以及 vendor/bin/phpunit --testsuite unit、--testsuite contract、vendor/bin/phpstan analyse、tools/pre-merge.sh --fast。

禁止：不往 tools/dev-only-paths.txt 或任何豁免、白名单里加条目；不改 CI 和门禁脚本；不建库、不启任何数据库；不合并；不强推、不 reset --hard、不 --no-verify、不 git add -A；不用云端、不用 Cursor；不开下级会话；不读不打印秘密。

交付：普通 push 到同分支；本任务目录 ~/agent-work/assistant/2g-mcp-devref-fix-1119/RESULT.md 写新 SHA、命令、退出码、关键输出；顺带写一句为什么本地 pre-merge --fast 没拦住这一项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
