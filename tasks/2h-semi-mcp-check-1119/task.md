【任务】检查并接上 Semi Design 的 MCP（给 1.x 前端开发用）

背景：1.x 前端定为 React 19 + Semi Design 2.104.0 + Tailwind。老板问开发 1.x 是否用 Semi 的 MCP。

要做：
1. 列出本机 Devin 已配置的 MCP 服务器名称（~/.config/devin/mcp_config.json 及 devin mcp 命令能看到的），只记名称和命令，不打印任何密钥、令牌或环境变量值。
2. 判断里面有没有 Semi Design 的 MCP。也看 ~/.shared-skills/semi-ui-skills（SKILL.md、WORKFLOWS.md、BEST_PRACTICES.md）里有没有写 Semi MCP 的用法或包名。
3. 没有的话：先到 Semi Design 官方文档或官方 GitHub（DouyinFE/semi-design）确认官方提供的 MCP 服务及正确包名和启动方式；只接官方的，查不到官方 MCP 就停下写明，不装第三方包。确认后加到用户级 ~/.config/devin/mcp_config.json（保留原有条目，先备份成同目录 mcp_config.json.bak-1119），再用 devin mcp 命令验证能列出它的工具。
4. 在本任务目录 ~/agent-work/assistant/2h-semi-mcp-check-1119/RESULT.md 写：原有 MCP 名单、是否已有 Semi MCP、官方来源链接、做了什么、验证输出、以后前端任务怎么调用它。

禁止：不删除任何文件；不改仓库；不建库、不启数据库；不开下级会话；不用云端；不读不打印秘密；不装非官方包。

全部做完后最后单独输出一行：
=== RUN COMPLETE ===
