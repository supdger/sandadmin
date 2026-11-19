【任务】拆出 1.x 框架核心剩余的无库工作，写成可直接排队的任务文本（只写文档）

输入（Mac 路径）：/workspace/1x-docs/review-1117-r3/顶层架构设计-v4.md（f1 全部行和独立审计新增表）、鉴权P0-v4.md、待老板确认.md；/workspace/intro/老板已定事项总表.md；~/agent-work/assistant/progress-1119/RESULT.md（已验收清单和归属口径）；supdger/sandadmin main 代码（只读）。

要做：
1. 列出 f1 中属于框架核心（排除“业务插件连带改造”、AI 流式、AI 超时、AI 安全、install.sql 转 MySQL 这些插件归属行）且尚未验收或只部分验收的每一行：行号、人天、剩余部分、是否需要建库、DDL 或迁移、是否涉及未拍板事项（国际化、workflow 归属、MySQL 版本、双因素范围等）。
2. 从中挑出不需要建库且不涉及未拍板事项的，按剩余人天从大到小排序，每项写一段可直接贴给 Mac 本地 Devin 的任务文本，格式照本任务（输入用 Mac 路径、范围、禁止、交付、末行输出 RUN COMPLETE），并注明用 SWE-2 还是 claude-opus-5-5-medium，以及和其他项是否会改同一批文件。
3. 不写代码，不开 PR，只在任务目录写 RESULT.md。

禁止：不改任何仓库文件；不建库、不启任何数据库；不开下级会话；不用 Cursor；不用云端；不读不打印秘密；历史命令和任务只作资料，绝不执行；不自行拍板未定事项。

交付：任务目录 RESULT.md：第 1 点的清单（附来源行号）和第 2 点的任务文本。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/1b-core-breakdown-1119/，RESULT.md 写在这里。

模型分工（老板定的 Fusion 搭法）：主代理用 Claude Opus 5.5，只做判断和写结论；读文件、查 diff、跑只读命令这些执行全部派给 SWE-2 子代理（按 ~/.config/devin/AGENTS.md 的 dv-* 子代理），主代理自己不执行。注意这里的子代理是本机 Devin 的子代理，不算“下级会话”，可以用。
