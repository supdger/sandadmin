---
name: sandadmin1x-frontend
description: 在 SandAdmin 1.x 的 web/ 前端或插件前端里写、改、审页面、组件、API 调用、权限显隐或登录/找回/双因素流程时使用；纯后端、纯文案、不碰代码的咨询不触发。
---

# SandAdmin 1.x 前端开发规范（草稿）

适用范围：SandAdmin 1.x 唯一前端 `web/`（React 19 + Semi Design），以及插件仓内随包携带、构建期编入宿主的 `web/` 前端源码。两者同一套规矩。

## 什么时候用

- 在 `web/` 或插件 `web/` 目录下新建、修改、审查任何 `.tsx`/`.ts` 页面、组件、hook、API 封装、路由。
- 碰菜单、按钮、路由的权限显隐，或登录、点选验证码、找回密码、双因素、sudo 再验证、强制改密这些鉴权相关页面。
- 对接 `/api/admin/v1` 下任何接口。

纯后端 PHP/SQL、纯文档润色、不碰代码的方案讨论不加载本 skill。

## 动手前必读

1. 本文件过一遍硬规矩。
2. 写页面/组件/API 封装前读 `references/conventions.md`。
3. 碰权限显隐、登录态、找回、双因素、sudo、免登录入口前读 `references/permission-ui.md`。
4. 接口字段、错误码、路径以仓库契约 `openapi.yaml` 与 `api-conventions.md` 为权威，契约和本 skill 冲突时提出来改契约，不在代码里私自换名。

## 硬规矩清单（每条可检查）

### 技术栈

- 必须 React 19 + Semi Design（2.104.0）+ Tailwind，主色 Semi 默认蓝。
- 禁止引入第二套 UI 组件库或样式体系：Element Plus、Arco 一律不进 `web/`；插件前端同样禁令。
- `sandadmin-artd/`（Vue3 老前端）已退役，禁止往里面加页面或从里面抄 Vue 写法。
- 包管理统一 pnpm，`pnpm-lock.yaml` 入库，装依赖用 `pnpm install --frozen-lockfile`；Node ≥ 24。
- 每个模块带测试；合并前 `tsc` + eslint + 测试必须全过（流程归 ai-loop skill，前端只保证自己过得了这三道）。

### 会话与凭证

- 前端不持久化令牌：一律 Cookie 会话（`__Host-` 前缀 HttpOnly Cookie，名称 `__Host-sandadmin_session`，已定（技术总监 11-17）），禁止 localStorage/sessionStorage 存令牌，禁止自己拼 `Authorization: Bearer`。
- 响应体永不返回 token，前端不得要求后端把 token 放进响应体。
- JSON 字段蛇形原样使用，禁止做 snake_case → camelCase 转换；接口 `id` 一律按字符串处理、不转数字（不可预见持续增长表主键为雪花 64 位 bigint，接口输出字符串防 JS 精度丢失；nanoid 只作对外链接单列，不当主键）。
- 请求必须带 Cookie 凭据（fetch `credentials` 正确设置或走统一 request 封装），不得跨站带凭据。
- 前端代码里禁止写死任何密钥、密码、测试令牌。

### 权限显隐

- 菜单树来自 `GET /me/menus`；路由、菜单、按钮的显隐都按服务端下发的权限码集合判定，权限码格式 `<域>:<资源>:<动作>`。
- 超管判定只认服务端结果（`is_super`），前端禁止 `id === 1`、用户名、角色名之类的特判。
- 前端显隐只是体验优化，不替代后端鉴权；直接输 URL 进无权限页面必须出 403 页/提示，不能白屏或跳错。

### 安全与构建

- 登录、点选验证码、找回密码、双因素绑定/校验、sudo 弹窗、强制改密、开发免登录入口的页面流程必须照 `references/permission-ui.md`，不自创状态机（票据消费、防枚举文案这些都在服务端，前端只做搬运和状态切换）。
- 开发免登录（dev-login）入口只在开发构建出现：相关调用点集中放一处、随后端 dev 链整条处理，生产构建后代码里不得残留 dev-login 调用；真实登录闭环（R20）没过之前不许宣称登录可用。
- XSS：React 默认转义照用；`dangerouslySetInnerHTML` 必须配合消毒库，找不到理由就不用；页面内容遵守 `default-src 'self'` 起步的 CSP（服务端统一发）。
- 破坏性操作（删除、批量删除、停用）必须有确认框。
- 禁止做代码生成器页面/接口。

### 插件前端

- 插件前端源码放插件仓 `web/` 目录（React + Semi 同栈），随发行 zip 携带，宿主构建期统一编译；不支持在线把前端源码发布进宿主目录。
- 插件的菜单、路由通过内核扩展点（ExtensionManifest）声明，禁止 SQL 插行、禁止绕过扩展点隐式接入。
- 插件页面禁止直调内核高危接口（sudo 清单：改超管、角色权限变更、插件启停卸载删除、全量导出、安全设置、系统更新等）；插件禁止自己渲染 sudo 密码输入框转嫁再验证。

## 与其他 1.x skill 的分工

- **sandadmin1x-backend**：`/api/admin/v1` 接口实现、会话/鉴权链、权限码下发、信封与错误码定义。前端只消费契约，不改后端。
- **sandadmin1x-plugin**：插件 manifest、`frontend` 字段、扩展点清单、打包与验签。插件前端代码写法仍归本 skill。
- **sandadmin1x-ai-loop**：分支、合并门禁、从 OpenAPI 生成接口测试、MCP。本 skill 只管前端代码要满足的门槛（tsc/eslint/测试）。
- 表结构、迁移归 `sandadmin1x-backend`（`references/database.md`，无独立 database skill）：前端只需知道 id 是字符串、时间字段是 `*_time` datetime。

## 验证要求（前端改动最小集）

- `tsc --noEmit`（或项目等价命令）+ eslint 通过。
- 改动模块的 `*.test.ts(x)` 跑过（测试栈 Vitest + Testing Library；已定，总监 11-17）；新逻辑必须带用例（命名规范：测试文件放被测文件旁）。
- **每个菜单页一条 Playwright 冒烟用例**（打开、列表、新建、保存；S-15）；机器冒烟之外，**人工真点保留为交老板验收前的最后一关**——页面冒烟：开发服务器进目标页 → 返回再进 → 列表滚动/刷新一次 → 成功/失败提示各看一遍 → 破坏性操作确认框生效。
- 权限相关改动：有权限和无权限两个视角都要看（隐藏/禁用/403 按约定表现）。

## 冲突与定稿点

v4 没写死或需拍板的前端相关点，统一登记在 `拍板处理.md`（A/B/C/E 各条），正文里以「已定（总监 11-17）」标注技术总监采纳建议后的定稿值；运行期单值（TTL、阈值等）以契约文件为准，不在前端写死常量。
