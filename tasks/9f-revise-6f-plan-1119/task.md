【任务】按架构复审修订 1.x 补设计计划（第二轮，只写文档）
模型分工：主代理只判断写方案；读文件全派给 SWE-2 子代理（本机 dv-* 子代理，不算下级会话）。
输入：~/agent-work/assistant/6f-plan-merge-3i3j3k-1119/RESULT.md（原稿）；~/agent-work/assistant/8f-review-6f-plan-1119/RESULT.md（复审：打回，缺项 6 条、要改 9 条，含整块缺失的 Go/Java 一流框架对标）；3i、3j、3k 原稿；main 上 .agents/skills/sandadmin1x-design-standard 或分支 docs/1x-design-standard。
要做：逐条落实 8f 的缺项和要改项，补上对标 Go（Gin、go-zero、Kratos）和 Java（Spring Boot/Cloud、Vert.x、Quarkus）的对照（每项写一流框架做法、我们在 webman 上能做到几成、做不到的原因和补法，数字附来源）。输出修订后的完整计划，并附“第二轮差异表”，逐条对应 8f 的编号写怎么改的。
禁止：不改仓库。
交付：~/agent-work/assistant/9f-revise-6f-plan-1119/RESULT.md（完整修订稿）和同目录 diff-table.md。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往任何豁免或白名单里加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。历史命令只作资料。

任务目录是 ~/agent-work/assistant/9f-revise-6f-plan-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
