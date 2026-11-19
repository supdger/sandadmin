# PR #106 任务中心页 Semi 用法补查结果

- 仓库：`supdger/sandadmin`（本地 `~/agent-work/pub-sandadmin`），核对基线 `origin/main = a18fef9`
- 目标 PR：#106「feat(web): artd 残留宿主页面迁移——任务中心」，merge commit `4ad3685`，2026-11-19 合入 main
- Semi 版本：`@douyinfe/semi-ui / semi-icons / semi-illustrations / semi-vite-plugin` 均锁定 `2.104.0`（web/package.json）
- 结论先行：**全部符合，未开分支、未开 PR**（按任务约定「全部符合就不开 PR」）

## 一、PR #106 改动的 web 文件

含 Semi 组件的文件（其余为 i18n / msw mock / 路由注册 / 测试基础设施，无组件用法）：

| 文件 | 性质 |
| --- | --- |
| `web/src/pages/system/tasks/TasksPage.tsx` | 任务中心列表页 |
| `web/src/pages/system/tasks/TaskDetailDrawer.tsx` | 详情 SideSheet 抽屉 |
| `web/src/pages/system/tasks/TaskStateTag.tsx` | 状态 Tag |
| `web/src/pages/system/tasks/TasksPage.test.tsx` | 测试（`import '@douyinfe/semi-ui/react19-adapter'`） |
| `web/src/routes/modules/system.tsx`、`paths.ts`、`i18n/locales/zh-CN/tasks.ts`、`mocks/*` | 无 Semi 组件用法 |

## 二、Semi 组件与属性清单（按文件逐条）

### TasksPage.tsx（`@douyinfe/semi-ui`）

| 组件/符号 | 用到的属性/写法 | 位置 |
| --- | --- | --- |
| `Input` | `value`+`onChange`（受控）、`placeholder`、`style`、`showClear`；`onChange` 取第一参 `value: string` | L191-197 |
| `Select` | `value`+`onChange`（受控）、`placeholder`、`style`、`optionList=[{label,value}]`（含 `value:''` 的"全部"项） | L198-210 |
| `Checkbox` | `checked`+`onChange`（受控），事件 `e.target.checked` | L211-216 |
| `Table` | `columns`、`dataSource`、`rowKey="id"`、`loading`、`pagination={false}`、`empty` | L220, L230-236 |
| `Pagination` | `currentPage`、`pageSize`、`total`、`showTotal`、`onPageChange` | L239-245 |
| `Progress` | `percent`、`size="small"`、`style`、`aria-label` | L123-128 |
| `Button` | `size="small"`、`theme="borderless"`、`icon`、`loading`、`onClick` | L151-168, L180-186 |
| `Typography.Text` | `code` | L109 |
| `Toast` | `success()`、`error()`（字符串） | L95-97 |
| `ColumnProps<Task>` | 类型导入 `@douyinfe/semi-ui/lib/es/table/interface`；列用 `title/dataIndex/render`（render 第一参=单元格值，第二参=行记录） | L17, L105-172 |
| `IconDownload / IconEyeOpened / IconRefresh` | `@douyinfe/semi-icons` | L14 |

### TaskDetailDrawer.tsx

| 组件 | 属性 | 位置 |
| --- | --- | --- |
| `SideSheet` | `visible`（受控）、`width={480}`、`title`、`onCancel` | L46-51 |
| `Skeleton` | `loading`、`placeholder={<Skeleton.Paragraph rows={8}/>}` | L53 |
| `Descriptions` | `align="left"`；`Descriptions.Item` 用 `itemKey` 作标签 | L63-89 |
| `Typography.Text` | `copyable={{ content: task.id }}` | L65 |
| `Typography.Paragraph` | `type="danger"` | L91 |
| `Button` | `theme="solid"`、`onClick` | L94 |
| `Toast` | `success()`、`error()` | L39-41 |

### TaskStateTag.tsx

| 组件 | 属性 | 位置 |
| --- | --- | --- |
| `Tag` | `color` ∈ `grey/blue/green/red/orange` | L11-22 |
| `TagColor` | 类型导入 `@douyinfe/semi-ui/lib/es/tag/interface` | L2 |

## 三、Semi MCP 调用记录（受阻如实登记）

本会话已配置官方 `semi` MCP（`@douyinfe/semi-mcp` stdio，见 `~/.config/devin/mcp_config.json`）。`mcp_list_tools(semi)` 成功，列出 5 个工具：`get_semi_document`、`get_semi_code_block`、`get_component_file_list`、`get_file_code`、`get_function_code`。

| # | 工具 | 查询组件 | 结果 |
| --- | --- | --- | --- |
| 1 | `get_semi_document` | Table（version=2.104.0） | **被拒**：非交互模式下该工具调用需人工确认，自动拒绝 |
| 2 | `get_semi_document` | Pagination（version=2.104.0） | **被拒**：同上 |
| 3 | `get_semi_document` | Table（version=2.104.0，重试一次确认非偶发） | **被拒**：同上 |

受阻原始证据（任务目录 `run.log` 第 1-3 行）：

```
warning: rejected a tool call that requires confirmation. Running in non-interactive mode.
Use --permission-mode dangerous to auto-approve all tools.
```

审核层说明：拒绝来自 Devin CLI 非交互运行的工具确认门禁（`local_tools_approval_mode: "normal"`），不是对操作本身的实质拒绝；按全局规则「不换入口或间接执行绕过」，未改用 npx/子进程方式调同一 MCP 服务。

**替代权威依据**：`web/node_modules/@douyinfe/semi-ui@2.104.0` 已按 lockfile 安装，其 `.d.ts` 类型声明即该版本官方 API 契约（semi-mcp 的 `get_file_code`/`get_component_file_list` 本身也是从 unpkg/npmregistry 取同一套包文件，见 semi-mcp `dist/http.js` 中 `UNPKG_BASE_URL`）。以下核对全部以本地 2.104.0 包声明为准。

## 四、逐项核对结论（vs semi-ui 2.104.0 `.d.ts`）

全部通过，未发现属性名错误、非法取值、错误事件签名或失控写法：

| 组件 | 核对点 | 依据（`web/node_modules/@douyinfe/`） |
| --- | --- | --- |
| Table | `columns/dataSource/empty?: ReactNode/loading/rowKey` 均存在；`pagination?: TablePaginationProps \| boolean` → `pagination={false}` 合法 | `semi-ui/lib/es/table/interface.d.ts:17,19,23,39-40,46,311-312` |
| Pagination | `currentPage/pageSize/total/showTotal/onPageChange(currentPage)` 命名与签名正确 | `semi-ui/lib/es/pagination/index.d.ts:10-17` |
| Progress | `percent?: number`；`size ∈ 'default'\|'small'\|'large'`；`'aria-label'` 在 props 内（无障碍覆盖到位） | `semi-ui/lib/es/progress/index.d.ts:8,18,20` |
| Input | `value/showClear/placeholder`；`onChange?: (value: string, e) => void` —— 代码取第一参正确（非原生 `e.target.value`） | `semi-ui/lib/es/input/index.d.ts:24,29,31,40` |
| Select | `value?: SelectValue`、`optionList?: OptionProps[]`（`{label,value}`）、`onChange?: (value) => void`；`value=''` 由显式"全部"选项承接，受控合法 | `semi-ui/lib/es/select/index.d.ts:98,100,124` |
| Checkbox | `onChange?: (e: CheckboxEvent)`，`CheckboxEvent.target.checked` 存在 | `semi-ui/lib/es/checkbox/checkbox.d.ts:9,22`；`semi-foundation/.../checkboxFoundation.d.ts:2-7` |
| Button | `theme ∈ 'solid'\|'borderless'\|'light'\|'outline'`；`size ∈ 'default'\|'small'\|'large'`；`icon/loading/onClick` 均标准 | `semi-ui/lib/es/button/Button.d.ts:6-25` |
| SideSheet | `visible?: boolean`、`width?: number\|string`、`onCancel`、`title` —— 受控开合正确 | `semi-foundation/.../sideSheetFoundation.d.ts:19,24-25`；`semi-ui/lib/es/sideSheet/index.d.ts:13` |
| Skeleton | `loading/placeholder`，`Skeleton.Paragraph rows?: number` | `semi-ui/lib/es/skeleton/index.d.ts:10-18`、`item.d.ts:12` |
| Descriptions | `align ∈ 'center'\|'justify'\|'left'\|'plain'` → `"left"` 合法；`Item.itemKey?: ReactNode` 作标签 | `descriptions-context.d.ts:2`；`descriptions/item.d.ts` |
| Typography | `Text code?: boolean`；`copyable?: CopyableConfig\|boolean` 且 `CopyableConfig.content?: string`；`type` 枚举含 `'danger'` | `typography/base.d.ts:8,38,42,89`；`copyable.d.ts:7` |
| Tag | `color?: TagColor`，用的 5 色（grey/blue/green/red/orange）均在枚举内 | `tag/interface.d.ts:3,12` |
| Toast | `success/error` 接受 `string \| props` | `toast/index.d.ts:113-114,218-221` |
| Icons | `IconDownload/IconEyeOpened/IconRefresh` 均存在于 semi-icons@2.104.0 | `semi-icons/lib/es/icons/` |
| 类型导入 | `table/interface`（ColumnProps）、`tag/interface`（TagColor）路径与导出名有效 | 两文件均实际存在并导出 |
| react19-adapter | `package.json exports` 声明 `"./react19-adapter"` → `lib/es/react19-adapter.js`，测试文件导入路径正确（React 19 官方适配入口） | `semi-ui/package.json` exports map |

### 受控写法 / 事件 / 无障碍 / 主题

- 受控：Input、Select、Checkbox、Pagination、SideSheet 均为「值 prop + 变更回调」完整受控，无 `defaultValue`/`value` 混用。
- 事件：所有回调签名与 Semi 声明一致（Input/Select 第一参为值、Checkbox 为 `CheckboxEvent`、Pagination 为页码、SideSheet 为 `onCancel`）。
- 无障碍：Progress 显式 `aria-label`；操作按钮均带可见文本（详情/下载/刷新），Checkbox 带 children 文案；未发现纯图标无标签按钮。
- 主题：`theme="borderless"`/`"solid"` 在 Theme 枚举内；Tag 颜色均为 TagColor 合法值；未发现自定义色值绕过主题体系。
- 第二套 UI 库：PR 改动文件及被复用的 `@/components/{Empty,ErrorState,PageHeader}` 仅依赖 `@douyinfe/semi-*`，无第二 UI 库引入。

## 五、处置与检查结果

- **不符合项：0**。按任务约定「全部符合就不开 PR」：未创建 `fix/task-center-semi` 分支、未改动任何仓库文件、未开 PR。
- pnpm 类型检查 / lint / 单测 / smoke：任务约定仅在存在修复改动时执行；本次零改动，故未运行（也无改动需验证）。
- 仓库状态核对：核对全程以 `git show origin/main:<file>` 只读方式进行，工作区未被本任务修改。

## 六、遗留事项

- Semi MCP 工具调用被非交互权限门禁拒绝（上文记录）。如需严格按原任务"以 MCP 工具输出为核对依据"重跑，请以 `--permission-mode dangerous` 或交互模式重启本任务；预期结论不变（本地 2.104.0 包声明与 MCP 上游同源）。
