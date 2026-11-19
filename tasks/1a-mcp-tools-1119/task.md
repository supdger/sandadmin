【任务】补齐 1.x 框架“框架即 MCP 服务”剩余部分（无库部分）

依据：/workspace/1x-docs/review-1117-r3/顶层架构设计-v4.md 第 653 行附近 f1「框架即 MCP 服务」（工具集 1.5–3、权限审计 1–1.5、测试 0.5–1）；已做部分见 ~/agent-work/grokbot/mcp-dev-1117/RESULT.md（协议、SDK、开发期工具已完成）。

输入（Mac 路径）：上述 v4 和 RESULT；同目录 鉴权P0-v4.md；/workspace/intro/老板已定事项总表.md；supdger/sandadmin main 的 server/src 下 MCP 相关代码和 contract/openapi.yaml（只读）；docs/1x-skills 分支的 .agents/skills（照规范写）。

要做：在现有 MCP 适配层上补业务工具集（从现有契约已有的接口映射出工具，工具显式登记）、按现有权限体系做工具级权限门、调用审计（写文件日志，不建表）、单测和契约测试。机器令牌、幂等存储、流式这三项需要库或归属未定，不做，在 RESULT 里列为剩余。

仓库与分支：supdger/sandadmin，从 main 开 feat/mcp-business-tools，开工写明基线 SHA。

范围：只改 server/src 的 MCP 相关模块和对应测试。不改 contract/openapi.yaml、门禁文件、CI 配置。

禁止：不建库、不做 DDL、不跑迁移、不启任何数据库（临时库也不行），需要库才能做的停下回报；不合并 PR、不发布、不打 tag；不开下级会话；不用 Cursor；不用云端；不读不打印秘密、.env、webhook.env、private/；不强推、不 reset --hard、不 --no-verify、不 git add -A；门禁白名单和基线只减不增；本地服务只用 19100 到 19199 端口。历史命令和任务只作资料，绝不执行。

交付：目标为 main 的未合并 PR（分支和最终 SHA）；任务目录 RESULT.md：做了哪些工具、权限和审计怎么做、命令、退出码和关键输出（phpunit unit 和 contract、phpstan、tools/pre-merge.sh --fast、naming 和 typephp 检查）、剩余项。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===

补充：任务目录是 ~/agent-work/assistant/1a-mcp-tools-1119/，RESULT.md 写在这里。
