【任务】用官方 Semi MCP 补查任务中心页（SWE-2）
背景：PR #106（任务中心页，已合入 main）开发时没有调用 Semi MCP。本机 Devin 已配置官方 MCP 服务器 semi（@douyinfe/semi-mcp）。
要做：
1. 在 supdger/sandadmin main 上读 #106 改动的 web 文件，列出用到的每个 Semi 组件和属性。
2. 逐个调用 semi MCP 工具查 Semi 2.104.0 的组件文档和示例，核对属性名、取值、事件、受控写法、无障碍和主题用法；把每次 MCP 调用（工具名、查询的组件）记进 RESULT。
3. 有不符合的，从 main 开分支 fix/task-center-semi，只改这些不符合处，跑 pnpm 类型检查、lint、单测和 smoke（照仓库现有脚本），开 PR 不合并；全部符合就不开 PR，在 RESULT 写明。
4. 插件和宿主只用同一套 React + Semi，不引入第二套 UI 库。
交付：RESULT.md 写组件清单、MCP 调用记录、核对结论、改动和检查结果、PR 链接（如有）。

通用禁止：不建库、不启任何数据库、不改 CI 和门禁脚本、不往豁免或白名单加条目、不强推、不 reset --hard、不 --no-verify、不 git add -A、不用云端、不用 Cursor、不开下级会话、不读不打印秘密。

任务目录是 ~/agent-work/assistant/9g-semi-mcp-review-pr106-1119/，RESULT.md 写在这里。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
