# Semi Design MCP 检查结果（2026-11-19）

## 结论

**Semi Design 官方 MCP 已经接好，无需新增。** 本机 `~/.config/devin/mcp_config.json` 已配置名为 `semi` 的服务器，指向全局安装的官方包 `@douyinfe/semi-mcp`（v1.0.22），当前会话可正常列出其 5 个工具。未修改任何配置文件，因此也没有生成 .bak 备份（无变更可备份）。

## 1. 本机已配置的 MCP 服务器名单

`devin mcp list` 输出（只记名称与命令，不含任何密钥/环境变量值）：

| 名称 | 命令 / URL | 来源 |
| --- | --- | --- |
| figma | `https://mcp.figma.com/mcp`（HTTP） | `~/.claude.json` |
| codegraph | `/Users/supdger/Documents/Codex/tools/codegraph/node_modules/.bin/codegraph serve --mcp` | `~/.claude.json` |
| snyk | `npx -y snyk@latest mcp -t stdio` | `~/.claude.json` |
| semi | `node /Users/supdger/.nvm/versions/node/v26.13.0/lib/node_modules/@douyinfe/semi-mcp/dist/stdio.js` | `~/.config/devin/mcp_config.json` |

`mcp_config.json` 中 `semi` 无 env 配置项。

## 2. 是否已有 Semi MCP：有

- 名称：`semi`，stdio 方式，入口为全局 npm 包 `@douyinfe/semi-mcp` 的 `dist/stdio.js`，本机版本 **1.0.22**（与 npm registry 当前最新版一致，发布于 2026-02-13）。
- 等价于官方文档推荐的全局安装方式 `npm i -g @douyinfe/semi-mcp`，只是把 `npx -y @douyinfe/semi-mcp` 换成了绝对路径直调 node，避免了 npx 每次解析。
- 官方要求 Node > 20.19.0，本机 v26.13.0 满足。
- `~/.shared-skills/semi-ui-skills/` 三个文件均围绕 Semi MCP 编写：
  - `SKILL.md` 前置条件写明配置 `"command": "npx", "args": ["-y", "@douyinfe/semi-mcp"]`
  - `WORKFLOWS.md` 详述 `get_semi_document` / `get_semi_code_block` / `get_component_file_list` / `get_file_code` / `get_function_code` 的查询流程
  - `BEST_PRACTICES.md` 含 `react19`、`tailwind`、`customize-theme` 等专题文档的 MCP 查询示例

## 3. 官方来源

`@douyinfe/semi-mcp` 确认为官方包，未安装任何第三方替代品：

- 官方文档：https://semi.design/en-US/start/mcp-skills （"AI Agent MCP/Skills"，Skill 中旧链接 `/start/ai-mcp` 已迁至此处）
- npm：https://registry.npmjs.org/@douyinfe/semi-mcp ，maintainers 含 `semi-bot <semi-team@bytedance.com>` 及多名 @bytedance.com 账号
- 官方仓库：github.com/DouyinFE/semi-design 的 `ecosystem/semi-mcp/`（配套 skill 在 `ecosystem/semi-ui-skills/`，与本机 `~/.shared-skills/semi-ui-skills` 同源）

## 4. 验证证据

`devin mcp get semi`（CLI 位于 `/Applications/Devin.app/Contents/Resources/app/extensions/windsurf/devin/bin/devin`，不在 PATH）：

```
Server: semi
Source: ~/.config/devin/mcp_config.json
    Command: /Users/supdger/.nvm/versions/node/v26.13.0/bin/node
             /Users/supdger/.nvm/versions/node/v26.13.0/lib/node_modules/@douyinfe/semi-mcp/dist/stdio.js
```

`devin mcp` 无 tools 子命令，工具清单以本会话 MCP 枚举为准——`semi` 服务器实际暴露：

- `get_semi_document`：组件文档/组件列表/专题文档（changelog 分页、react19、tailwind、customize-theme、update-to-v2 等），支持 `version` 参数
- `get_semi_code_block`：取大文档中被占位符隐藏的代码块
- `get_component_file_list`：组件在 `@douyinfe/semi-ui` + `@douyinfe/semi-foundation` 的源码文件列表
- `get_file_code`：读源码（>500 行默认折叠函数体，`fullCode` 可取全量）
- `get_function_code`：取指定函数完整实现
- resource：`semi://components` 组件列表

## 5. 以后 1.x 前端任务怎么用（React 19 + Semi 2.104.0 + Tailwind）

1. 任务开始时先加载 `semi-ui-skills` 技能，按其工作流走。
2. 找组件：`get_semi_document`（不传 componentName 返回组件列表）。
3. 查 API：`get_semi_document` `componentName=<组件名>`、`version="2.104.0"` 锁定项目版本。
4. 专题文档：`componentName="react19"`（React 19 兼容注意项）、`"tailwind"`（与 Tailwind 共存）、`"customize-theme"`（主题定制）、`"update-to-v2"`。
5. 文档中代码块被占位时，用 `get_semi_code_block` 按序号取。
6. props 解决不了要改内部行为时：`get_component_file_list` → `get_file_code` / `get_function_code` 读实现，按 BEST_PRACTICES 的继承扩展法做。

## 边界确认

未删除任何文件；未改任何仓库与配置；未建库/启库；未开下级会话；未读取或打印任何密钥；未安装非官方包（全程零安装，利用既有官方全局包）。
