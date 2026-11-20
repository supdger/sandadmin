【任务】修 DeptsPage.test.tsx 的 Semi Tooltip 偶发红，审计后合并
背景：技术总监 11-19 要求在 main 分支保护设成必过之前合入，否则偶发红会随机卡住所有合并。
模型分工：主代理只做审计判断；查 MCP、改代码、跑命令、git、合并全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
一、修（从最新 main 开分支 fix/deptspage-tooltip-flaky，写基线 SHA）：
1. 这是前端任务：先用本机官方 Semi MCP（semi）查 Semi 2.104.0 Tooltip 的写法和异步显示机制，RESULT 列出每次 MCP 调用（工具名、查询）。
2. 找到 web 下 DeptsPage.test.tsx 里 Tooltip 异步竞态的根因，修测试本身（正确等待或控制计时器等），不许加重试、不许跳过用例、不许放宽断言。
3. 连跑该测试至少 50 次全绿，再跑 pnpm 类型检查、lint、单测和 smoke，普通 push，开 PR。
二、审计：只改了测试、无重试和跳过、根因说明成立。RESULT 第一行写结论和审计时 head。
三、结论“可合并”且 checks 全绿、无冲突时 gh pr merge --merge（不删分支），记录 main 新 SHA；否则不合写明。
通用禁止：不建库、不启库、不改 CI 和门禁脚本、不加豁免或白名单、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读秘密。

任务目录是 ~/agent-work/assistant/12w-fix-deptspage-flaky-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
