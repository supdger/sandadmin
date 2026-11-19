【任务】架构复审第二轮：1.x 补设计计划修订稿（只读，只写 RESULT.md）
模型分工：主代理只判断写结论；读文件全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
输入：~/agent-work/assistant/9f-revise-6f-plan-1119/RESULT.md 和同目录 diff-table.md（修订稿与第二轮差异表）；~/agent-work/assistant/8f-review-6f-plan-1119/RESULT.md（第一轮复审意见）。
要做：这是最后一轮，只看差异表：逐条核对 8f 的 6 条缺项和 9 条要改项是否落实，Go/Java 一流框架对标是否完整且数字有来源；不重开新问题，除非是高危遗漏。
结论第一行三选一：通过、改后通过（列必须修改点）、打回。不改任何文件。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用 --admin、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/10c-review2-plan-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
