# SandAdmin 1.x 前端写法约定（草稿）

技术栈、目录、API 封装、页面模式、类型安全。鉴权相关页面（登录/找回/双因素/sudo/权限显隐）见 `permission-ui.md`。

## 一、技术栈（已定）

| 项 | 口径 | 出处 |
| --- | --- | --- |
| 框架 | React 19 | 顶层架构设计-v4 |
| UI 组件库 | Semi Design 2.104.0，主色 Semi 默认蓝 | 老板规矩 + 顶层架构设计-v4 |
| 样式 | Semi 组件 + Tailwind，不引入第二套样式体系 | 顶层架构设计-v4:426 |
| 语言 | TypeScript，strict | 老板规矩第 4 条（对应 TypePHP 的前端面） |
| 包管理 | pnpm 单一 lockfile，`--frozen-lockfile` 安装 | 顶层架构设计-v4:1378 |
| Node | ≥ 24 LTS | 顶层架构设计-v4:1387 |
| 构建/测试工具 | Vite + Vitest + Testing Library；e2e 只要每个菜单页一条 Playwright 冒烟（打开、列表、新建、保存；S-15，独立审计轮），不做全量 e2e | 已定（总监 11-17）+ S-15 |
| 界面文案 | 一律走语言包键（i18n），禁止把文案写死在代码里；1.0 只出中文语言包 | 老板口径第 5 条 |

禁止：Element Plus、Arco Design 及任何第二组件库进 `web/`；从退役的 `sandadmin-artd/`（Vue3）抄写法；jQuery 等旧工具。

## 二、目录结构

宿主 `web/` 已有事实基础（`web/src/pages/auth/ClickCaptcha.tsx`、`web/src/api/auth.ts`、`web/src/mocks/`），沿用并对齐如下：

```
web/src/
  api/            # 接口封装，一资源一文件，camelCase.ts
  pages/          # 页面，按模块分目录：pages/<模块>/<页面>/
  components/     # 跨页共享组件（含字典、权限包装等通用封装）
  hooks/          # useXxx.ts
  mocks/          # 开发 mock（不进生产包）
  types/          # 跨模块共享类型（可选）
```

文件与命名（命名规范第四节，硬条款）：

- 组件文件 `PascalCase.tsx`，一个文件一个主组件，文件名 = 组件名；页面组件可用 `Page` 后缀，弹层用 `Modal`/`Drawer`，表单用 `Form`，表格用 `Table`。
- hook 文件 `useXxx.ts`；其他文件 `camelCase.ts`；目录名小写；测试 `同名.test.ts(x)` 放被测文件旁。
- 类型/接口不加 `I`/`T` 前缀；组件属性类型叫 `<组件名>Props`；事件属性 `onXxx`、处理函数 `handleXxx`。
- 测试：`describe('<被测对象>')` + `it('<做什么会怎样>')`，全仓测试描述只用一种语言。
- 禁用空泛后缀（`Manager`/`Helper`/`Util`/`Info`/`Data`）、禁用未登记缩写（`btn`/`msg`/`cfg` 等），词表以 `命名与注释规范` 附录为准。

插件前端包内结构按契约草案 `plugin-web/<app>/{pages,components,api,routes.ts}`（草案，标注待契约文档定稿；插件侧约定归 sandadmin1x-plugin skill）。

## 三、TypeScript 类型安全（硬条款）

对应老板规矩第 4 条「TypePHP」在前端的等价要求：

- `strict: true` 必须开着。
- 禁止 `as any`、显式 `: any`、 `@ts-ignore`/`@ts-nocheck`、用 eslint-disable 关 `no-explicit-any`；`Record<string, any>` 仅在范围极小且注释说明时允许，接口参数/响应一律给准确类型。
- 外部数据（接口响应、本地存储、URL 参数）按 `unknown` 进、类型守卫或 schema 校验收窄后用。
- 接口字段类型与契约 snake_case 字段同名同形，不做驼峰转换；`id` 是 `string`，时间字段是 `string`（`*_time` datetime 文本）。
- **接口类型防漂移（S-14）**：TypeScript 类型从 `openapi.yaml` 构建期生成（机械派生的构建产物，不写业务逻辑【老板 2026-11-17 已定 R-2】）；未接生成管线前，至少要有「类型与契约比对」门禁——手写类型与契约不一致即 CI 失败。前端不许出现与契约字段名/类型漂移的本地类型。
- 组件 props 用 `Props` 类型声明，不用 `as any` 绕过；第三方库缺类型时补最小 `interface` 或 `*.d.ts`。

## 四、API 封装层

### 4.1 统一 request 封装（必须有且只有一个）

所有请求走 `web/src/api/` 下的统一 request 封装，页面不直接 `fetch`/axios。封装负责：

- 统一加 `/api/admin/v1` 前缀；带 Cookie 凭据，不加 Authorization 头。
- 解析契约信封 `{code, message, fields, request_id}`——形态（已定，总监 11-17）：`code` 为字符串（成功 `"OK"`，失败用错误码总表登记的 `UPPER_SNAKE` 码）、`data` 为业务数据载体（失败时 `null` 或省略）、`fields` 为字段级校验错误容器（没有就省略）、`message` 为中文提示直接做页面文案；HTTP 状态码用语义码（200/401/403/429/503）。最终以 `api-conventions.md` 契约为准。
- 统一分流契约错误码：会话失效类 → 跳登录页（带回来地址）；`sudo` 窗口失效类 → 打开全局 sudo 弹窗（见 permission-ui.md）；`503 + Retry-After` → 重试提示，不当登出处理。
- 业务失败把 `message` 透给页面做提示，前端不自己编错误文案覆盖服务端中文提示。

### 4.2 资源 API 文件写法

一资源一文件（`api/user.ts`、`api/role.ts`……），导出带类型的函数对象；路径参数用 `{x_id}` 形式由函数拼接：

```ts
// api/dict.ts（示意，非完整实现）
export interface DictItem {
  id: string
  dict_type: string
  label: string
  value: string
  status: number
}

export const dictApi = {
  list(params: DictListParams) {
    return request.get<ApiPage<DictItem>>('/dicts', { params })
  },
  read(id: string) {
    return request.get<ApiData<DictItem>>(`/dicts/${id}`)
  },
  create(data: DictFormData) {
    return request.post('/dicts', data)
  },
  update(id: string, data: DictFormData) {
    return request.put(`/dicts/${id}`, data)
  },
  remove(ids: string[]) {
    return request.del('/dicts', { ids })
  },
}
```

硬规矩：

- 接口路径与契约一致：全小写、多词连字符、资源复数、动作放末段；禁止在 `api/` 里拼 `/core/...`、`/tool/...` 旧面地址（旧路由已全部处置，调了也是拒绝）。
- 参数和返回都写类型，不写 `Record<string, any>` 偷懒；树形数据接口的 `tree` 参数、分页参数写进 params 类型。
- 插件接口走 `/api/admin/v1/plugin/<name>/...` 前缀（已定，总监 11-17），同样经统一 request 封装；禁止插件页面直调内核高危接口（见 SKILL.md 硬规矩）。

## 五、页面模式（继承老栈通用约定，按 React+Semi 重写）

老栈（saiadmin6 frontend.md）的页面组织约定继承，实现按新栈：列表页 = 搜索区 + 表格头 + 表格 + 编辑弹层；树列表在其上加展开/收起与父级选择。

### 5.1 列表页骨架

```
pages/<模块>/<页面>/
  IndexPage.tsx        # 或 <Name>Page.tsx，列表页主入口
  components/
    SearchForm.tsx     # 搜索区
    EditDrawer.tsx     # 新增/编辑弹层
```

必须做到：

- 搜索字段超过 3 个时启用展开/收起，第 4 个起放展开区（继承老栈硬规矩）。
- 表格头操作区：新增按钮、批量删除按钮按权限码显隐（见 permission-ui.md）；批量删除在未选行时禁用。
- 行内操作（编辑/删除）逐行按权限码显隐。
- 分页、排序、筛选参数走后端分页接口，不做前端假分页。
- 数据加载中/空态/失败三态都有表现；失败给可重试入口。
- 删除、批量删除必须有确认框。

### 5.2 编辑弹层（Drawer）

- 打开时初始化：先重置表单草稿，编辑态再回填 `props.data`；关闭/取消清干净，不留脏数据给下次打开。
- 提交前前端校验（required 等）+ 服务端契约错误（`fields` 字段错误回显到对应表单项）。
- 提交防重（提交中按钮 disabled/loading）；成功后提示 + `emit` 由父页刷新列表，弹层自己不直接改列表数据。
- 区分三种失败：校验未过（用户改）、用户取消（无提示）、接口失败（服务端 message 提示）。
- 树形数据的父级选择用树选择组件，数据来自同一资源 `?tree=true` 接口，包一层根节点；编辑时禁止把父级选成自身或自身后代（前端挡一遍，后端仍校验）。

### 5.3 字典与枚举展示

- 字典字段（类型、状态等）的选项和标签来自服务端字典数据（`dict_type`），禁止在页面里手写一份枚举文案表；共享取数/展示组件放 `components/`（共享组件清单见第六节；已定，总监 11-17）。
- 表格里字典字段渲染成标签（颜色/文案由字典配置），编辑表单里渲染成对应的选择/单选/开关控件。
- `status`（1 启用 / 2 停用）与 `state`（流转状态）是两个东西，展示和筛选都要分清，不混用。

### 5.4 表单与详情杂项

- 时间字段展示 `*_time` 原样或统一格式化函数，不自造多套格式。
- 金额、比例等带单位字段按接口单位原样展示，前端不猜单位换算。
- 导出按钮直接调服务端导出接口拿文件，不在前端拼 CSV；前端不重复实现后端的公式注入转义。
- 富文本/代码展示如需渲染 HTML，必须过消毒（见 SKILL.md XSS 条）。

## 六、组件职责边界（继承通用规矩）

- 页面组件组合业务；局部组件拥有自己的表单草稿；跨页共享组件只表达稳定语义（字典标签、`<Permission>` 权限包装、确认框等）。
- 共享组件第一批清单（只做有真实第二处复用的；已定，总监 11-17）：字典标签 / 字典选择器（服务端 `dict_type` 驱动）、`<Permission code>` 权限包装、确认框封装、加载/空态/失败三态组件、时间格式化展示；老栈 sa-* 组件清单不照搬，逐个按「有没有第二处用」重评。
- 布局差异用插槽/children，行为差异用事件回调（`onXxx`），不靠累加业务特例开关 prop。
- `v-model` 类受控写法照 React 惯例（`value` + `onChange`）；弹层受控 `visible` + `onClose`。
- 共享数据的失效边界要写明：列表提交后由父页刷新；字典等全局数据变更后要有失效/刷新策略，不拿过期缓存当事实。
- 旧请求的成功/失败/收尾都不能覆盖新请求状态：组件卸载或参数变化后落地响应要丢弃（取消标志或竞态守卫）。
- 复用现有 api/hook/store，不为一个页面另起第二份同义封装；不为「统一风格」顺手重构无关旧页面。

## 七、环境与构建

- 开发用 dev 服务器 + `--noEmit` 类型检查；只有出发布包/构建制品时才跑生产构建（老板规矩：生产不安装不编译，制品只在 CI 构建）。
- mock 只放 `web/src/mocks/`，属开发资产，不进生产构建；开发用发信桩（短信/邮件页面回显）同口径——只在开发构建出现。
- 环境差异走构建期常量/环境变量，禁止运行时探测 dev 入口再决定行为（dev 链是编译期整条剔除的，见 permission-ui.md）。
