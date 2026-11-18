# SandAdmin 1.x 契约设计：OpenAPI 唯一契约 + 构建期派生

定稿日期：2026-11-17。依据：仓根 `盘点.md`（U-6 第 1 步盘点）、
`顶层架构设计-v4.md` U-6 节与 11-17 修订第④条、`.agents/skills/` 四个
skill（contract.md、backend-structure.md、security-auth.md、mcp.md、
delivery-loop.md）。

一句话方案：**`contract/openapi.yaml` 是接口的唯一权威；一个 PHP 脚本
`tools/contract-build` 在构建期把它（加插件片段）机械翻成路由表、权限
注册表、MCP 工具表、错误码注册表、TS 类型、测试骨架六样产物；门禁把
「生成物与源不一致」卡死在合并前。**

明确边界（先写死，免得后面扯皮）：

- 本设计只生成**注册表/清单/骨架**类构建产物，不生成控制器、模型、页面
  等业务代码（老板已定 R-2：契约派生不算代码生成器，但不做业务代码生成）。
- 权限注册表**只生成文件产物，不落库**。`sand_system_role_permission` 表
  照旧存权限码字符串；「权限点自动入库/自动编号」等编号表方案定了再做，
  本期不做（设计点 8）。
- 不建表、不写 DDL；不新增任何门禁豁免条目（PHPStan 基线、naming-allow、
  typephp-allow、危险函数白名单、dev-only 清单一概不动）。
- dev 专用链（dev 登录 5 条路由、4 个 `dev_*` MCP 工具）保持
  `dev 哨兵开始/结束` 哨兵段手写 + `x-mcp-tools` 的 `dev_only` 声明，
  不进正式接口契约面，理由见设计点 2、5。

---

## 一、结论速览：文件清单

| 角色 | 路径 | 说明 |
| --- | --- | --- |
| 宿主契约源 | `contract/openapi.yaml` | 唯一契约权威：paths + 三个扩展字段 + 错误码总表 + 权限树 + 独立 MCP 工具段 |
| 契约约定文档 | `contract/api-conventions.md` | 人读规范：信封、错误码用法、分页、幂等、版本前缀、三组路由清单口径 |
| 插件契约片段 | `<插件包根>/contract/openapi.yaml` | 装进宿主后落在 `server/plugin/<app>/contract/openapi.yaml` |
| 构建脚本（入口） | `tools/contract-build` | PHP CLI 薄壳，子命令：build（默认）/ `--check` / `--plugins <dir>` / `--kernel-api <dir>` |
| 构建逻辑（内核类） | `server/src/Contract/Build/` | 命名空间 `Sandadmin\Contract\Build`，PHPStan max 覆盖、随内核分发 |
| kernel_api 校验器 | `server/src/Contract/KernelApi/FragmentCheck.php` | 纯 PHP、不依赖 PHPUnit，宿主与插件共用同一份校验逻辑 |
| 生成物：宿主路由表 | `server/config/route.generated.php` | 直接调 `Route::` 注册，被 route.php `require` |
| 生成物：插件路由清单 | `server/config/plugin-routes.generated.json` | 已装插件路由入口文件清单，替换 bootstrap 的 scanDir 喂 Route::load |
| 生成物：插件路由表 | `server/plugin/<app>/config/route.generated.php` | 装/升插件时由 contract-build 生成进插件载荷目录 |
| 生成物：权限注册表 | `server/src/Contract/Generated/PermissionRegistry.php` | 权限码常量 + `CODES` + `TREE` + `ADMIN_CODES` + `MENU_KEYS` |
| 生成物：MCP 工具表 | `server/src/Contract/Generated/McpToolRegistry.php` | `SPECS` 清单（name/title/desc/tier/permission/route/input_schema），不含 runner |
| 生成物：错误码注册表 | `server/src/Contract/Generated/ErrorCodeRegistry.php` | 每码一个常量 + `HTTP_STATUS` 映射 + `ALL` 清单 |
| 生成物：前端 TS 类型 | `web/src/api/v1/contract.generated.ts` | openapi-typescript 形状的 `paths`/`operations`/`ErrorCode` 联合类型 |
| 生成物：测试骨架 | `server/tests/Contract/Generated/` | 每 tag 一个 `*ContractTest.php` + `ContractSurfaceTest.php` |
| 契约测试套件 | `server/tests/Contract/` | phpunit 新增 `contract` 套件；kernel_api 用例 `KernelApiTest.php` |
| 一致性报告 | `--report <path>` 输出 JSON | 默认 `var/contract-build-report.json`（var/ 不入仓） |

---

## 二、九个设计点逐条结论

### 1. OpenAPI 源文件放哪

**结论：**

- 宿主：**仓根 `contract/openapi.yaml`**（注意不是 `server/openapi.yaml`、
  也不是 `server/contracts/`），配套 **`contract/api-conventions.md`**。
- 插件片段：插件仓库根（= zip 包根）**`contract/openapi.yaml`**，随包
  分发，安装后位于 `server/plugin/<app>/contract/openapi.yaml`。
- `api-conventions.md` **要补**，放 `contract/api-conventions.md`。

**理由：**

- 契约是跨层的东西——它同时喂 `server/`（路由、注册表）、`web/`（TS
  类型）、`server/tests/`（骨架）、插件生态（片段合并、kernel_api）。
  塞进 `server/` 里面会暗示「这是后端的私有物」，放仓根与 `server/`、
  `web/`、`tools/`、`tests/` 平级最直白，也和 skill 里「openapi.yaml +
  api-conventions.md 是契约权威」的提法对得上。
- 插件包结构契约（contract.md §2）就是「包根放 manifest.json 这类总
  声明」，契约片段与 manifest.json 平级同目录，四类声明（OpenAPI 片段
  上挂着 MCP/权限/错误码三类）一处收集。zip 解进 `server/plugin/<app>/`
  后相对路径不变，宿主按固定相对路径 `plugin/<app>/contract/openapi.yaml`
  收集，不用配置。
- `api-conventions.md` 必须补的原因：skill 里多处把它当契约权威引用，
  仓里却没有这份实体（盘点第 1 节）；信封形状、错误码使用纪律、分页
  参数、幂等键、`/api/admin/v1` 前缀与三组路由清单（公开/must_change/
  数据范围受限）这些「跨所有接口的通用约定」写在 md 里人读，机器可读
  的部分（错误码总表、权限树）留在 yaml 里给生成器用，两者分工不重叠。

### 2. 扩展字段精确语法

**结论：三个扩展字段全部挂在 OpenAPI operation 上；另有三个顶层
`x-` 段放「不挂在具体接口上」的声明。**

**`x-permission`**（每个 operation 必写，取值四选一）：

```yaml
x-permission: system:user:index          # 单码：最常见形态
x-permission: authenticated              # 登录即可，无具体码（/me、工作台等）
x-permission: public                     # 不鉴权（登录、验证码、healthz）
x-permission: super                      # 超管判定（PackageOps::guard 那一族）
x-permission:                            # 对象形态：组合判定
  any_of: [system:user:update, system:user:delete]   # 任一即可（disable-check 这类）
```

- 单码是字符串；`any_of`（任一）/`all_of`（全要）是对象形态，数组至少
  两个码。`authenticated`/`public`/`super` 是三个保留字面量，不是权限码，
  生成器按保留语义翻译（`public` → 公开清单、`authenticated` → 只查登录、
  `super` → `is_super` 判定）。
- 「本人或持码可看全部」这类规则（file download、task viewAll、lifecycle
  show）写成 `any_of` + 在 `description` 里说明属主兜底由代码实现——契约
  只负责声明「这个接口与哪些权限码有关」，属主判定是代码逻辑不进契约；
  生成测试骨架时按「无码身份 → 403」断言，属主放行用例由人工补。
- 不挂接口的权限码（`system:task:index` 这类 viewAll 等效码、`mcp:dev:*`
  凭据 scope）声明在**顶层 `x-permissions`** 列表里，保证注册表覆盖全部
  45 个码而不是只有路由上出现的 39 个。

**`x-mcp`**（可选；写了才暴露成工具）：

```yaml
x-mcp:
  name: task_list          # 工具名，必填；插件工具必须 <插件词干>_ 前缀
  tier: read               # 三档：read / write / high_risk
  title: 查询任务列表       # 中文标题
  description: 列出后台任务（按调用方权限过滤）
  input:                   # JSON Schema 对象，工具入参
    type: object
    properties:
      status: {type: string}
    additionalProperties: false
```

- `tier` 只许 `read`/`write`/`high_risk` 三个值，对应 M-02 三档口径
  （read 直执；write 必带幂等键 + dry_run；high_risk 只生成待确认请求）。
- `input` 里**不写** `idempotency_key`/`dry_run`：write 与 high_risk 档由
  生成器自动注入这两个参数（`idempotency_key` 必填、`dry_run` 可选），
  写了就按「与注入形状逐字节一致」校验，不一致报错。这样三档规矩只有
  生成器一处实现，不存在某个工具忘加幂等键的漏子。
- 工具对应的 v1 路由不用写——`route` 字段直接取所在 operation 的
  `path`。**没标 `x-mcp` 的接口不暴露为工具；独立工具（不绑任何 v1 接口
  的，即现有 4 个 dev 工具）声明在顶层 `x-mcp-tools` 段**，每条额外加
  `dev_only: true`（见下面骨架示例）。规定：生产面工具必须绑 operation，
  顶层段的独立工具只允许 `dev_only: true`，出现 `dev_only: false` 或不写
  直接报错——把「生产工具 ⊆ 接口能力集」做成机器可查的。

**`x-error-codes`**（可选但建议写全）：

```yaml
x-error-codes: [ROLE_NAME_TAKEN, STALE_VERSION]
```

- 写**码的字面量**，不写 `$ref`。理由：逐接口列码是给人看和给测试用的
  声明，值本身就是引用对象；统一错误码总表用**顶层 `x-error-catalog`**
  段承载（`码 → {http_status, summary}`），生成器校验 `x-error-codes`
  里的每个码都在合并后的总表（宿主总表 + 各插件片段自带总表）里登记
  过，没登记直接构建失败——这就把「码先登记再使用」从纪律变成门禁。
- 四个通用码 `VALIDATION_FAILED`/`UNAUTHENTICATED`/`PERMISSION_DENIED`/
  `INTERNAL_ERROR` 在总表里标 `common: true`，接口不用逐个声明；业务码
  才需要写进 `x-error-codes`。
- 插件片段可以有自己的 `x-error-catalog` 段，码名冲突（与宿主或其他
  插件撞名）直接构建失败。
- 总表条目可加 `const: <大写蛇形常量名>` 给 PHP 常量另取短名：线上码值
  不变、只有 `ErrorCodeRegistry` 常量名变化（用于码名超 4 词上限的既有
  码，如 `MCP_HIGH_RISK_NOT_OPEN` → `MCP_HIGH_RISK_CLOSED`）；不写时
  常量名即码名，两个码声明同一 `const` 构建失败。

**YAML 骨架示例见第三节。**

### 3. 派生产物清单与落点

**结论：六样产物，全部带「自动生成勿手改 + 源哈希」头（格式见第四节）。**

| 产物 | 路径 | 形态与消费方 |
| --- | --- | --- |
| 宿主路由表 | `server/config/route.generated.php` | PHP 文件，被 `route.php` `require`，**自己调 `Route::get/post/...` 注册**（不是数据数组）。选「自注册」不选「route.php 遍历数据数组」，因为直接 Route:: 调用与现状逐行等价，Webman 语义零差异，PHPStan/人读都直观。装载期守卫：`Config::loadFromDir` 只排除基名 `route.php`，会把本表当配置文件 include（`webman start`/`start.php` 主进程路径），彼时无 FastRoute collector、自注册必崩——生成表开头判 `SAND_CONTRACT_ROUTE_LOAD` 未定义即 `return []`，标记由 `route.php` 在 `Route::load` 回调内定义 |
| 插件路由清单 | `server/config/plugin-routes.generated.json` | `{"_comment": [<头行>], "routes": ["plugin/sand-foo/config/route.generated.php", ...]}`，bootstrap 用它替换 `Util::scanDir` 喂 `Route::load` 的那段（见设计点 5）；用 JSON 而非 PHP return 形态——装载点 require 变量路径会被 TypePHP 门禁判动态加载 |
| 插件路由表 | `server/plugin/<app>/config/route.generated.php` | 装/升插件时 contract-build 生成进载荷目录；插件 `config/route.php` 变成一行 require 的薄壳 |
| 权限注册表 | `server/src/Contract/Generated/PermissionRegistry.php` | `final class`，每码一个 `public const`（`USER_INDEX = 'system:user:index'`），加 `CODES`（全量清单）、`TREE`（权限树，顶掉 `RoleService::MENUS`）、`ADMIN_CODES`（顶掉 `UserService::ADMIN_CODES`）、`MENU_KEYS`（顶掉 MenuService 手写菜单 key） |
| MCP 工具表 | `server/src/Contract/Generated/McpToolRegistry.php` | `SPECS`：与 `ToolSpec` 同形状的纯数组清单（runner 除外——runner 是代码不能生成，装配侧按 name 从手写 runner 表取，见设计点 5） |
| 错误码注册表 | `server/src/Contract/Generated/ErrorCodeRegistry.php` | 每码一个常量 + `HTTP_STATUS` 映射 + `ALL` 清单；异常类、`ApiOutcome::fail`、`OrgDbMap` 约束翻译逐步改引用常量 |
| 前端 TS 类型 | `web/src/api/v1/contract.generated.ts` | openapi-typescript 风格的纯类型声明：`paths` 映射 + `operations[operationId]` + `components.schemas` 接口 + `ErrorCode` 字符串联合类型。由 PHP 生成器直接吐 TS 文本（仓内自写，不引 openapi-typescript node 工具链——delivery-loop 明说「不引外部重工具链」）。只落 `web/`（React 目标前端）；sandadmin-artd 是现役 Vue 工程，接不接生成物归前端迁移另行决定 |
| 接口测试骨架 | `server/tests/Contract/Generated/` | 每 tag 一个 `<Tag>ContractTest.php` + 一张总表 `ContractSurfaceTest.php`；进 phpunit 新增 `contract` 套件 |

**骨架怎么避免假绿/假红（这块要想清楚）：**

- 生成两类用例。**确定性用例**（永远跑、不靠业务夹具）：① 路由表面
  断言——`Route::getRoutes()` 里每个契约 path+method 存在、没有契约外
  路由（插件路由单独算）；② 未认证请求打每个非 public 端点 → 401
  `UNAUTHENTICATED`（用测试桩 `HttpStack` + Unavailable 系身份提供方，
  生产口径本来就是这个行为，确定可断言）；③ 无对应码的身份 → 403
  `PERMISSION_DENIED`（用桩 principal 装配空权限集）；④ 每个声明的
  `x-error-codes` 码都在注册表里。这四类失败就是真失败，不存在假红。
- **业务成功路径**生成成 `markTestIncomplete` 的骨架，方法体里留
  `TODO(契约测试): 补夹具`，并在 JSON 报告里单列
  `incomplete_operations` 清单——incomplete 不算过也不算挂，但门禁可配
  「incomplete 数量不许净增」，防止永远停在骨架状态。不把成功路径写成
  空断言（那是假绿），也不硬猜夹具（那是假红）。

### 4. 构建脚本 `tools/contract-build`

**结论：**

- **语言：PHP 8.2**。`tools/contract-build` 是带 shebang 的可执行薄壳
  （与 `tools/check-dev-refs.php`、`tools/typephp_check.php` 同款风格），
  全部逻辑放 `server/src/Contract/Build/`（命名空间
  `Sandadmin\Contract\Build`）——逻辑进 src 意味着 PHPStan max、php -l、
  危险函数扫描、dev-only 门禁自然覆盖它，且随内核分发后插件安装期的
  收集步骤（lifecycle 钩子里调 `ContractBuild::collectPlugins()`）不靠
  tools/ 目录在不在场。
- **YAML 解析：引入 `symfony/yaml`**（^7，进 `require` 不是
  `require-dev`）。现状：`composer.json` 没有 yaml 依赖，`composer.lock`
  里只有别家包对它的约束声明、没有实体包。理由：契约收集是内核能力，
  装/升插件时在装包机器上也要跑，不能只存在于 dev 环境；symfony/yaml
  是纯 PHP 小组件、供应链风险低。自写 YAML 子集解析器是给自己挖坑，
  不干。代价：composer.json/lock 变更要过依赖评审，属正常流程。
- **子命令：**
  - `build`（默认）：解析宿主契约 + 收集插件片段 → 查冲突 → 写全部
    生成物 → 输出 JSON 报告。
  - `--check`：在临时目录重新生成，与仓内产物**逐字节比对**；不一致
    列出文件名 + 退出码 1。门禁就用这条。
  - `--plugins <dir>`：指定插件片段来源目录（`<dir>` 本身是包根、或
    `<dir>/*/contract/openapi.yaml` 逐个扫）。默认来源 =
    `server/plugin/*/contract/openapi.yaml`（已装插件）。
  - `--kernel-api <dir>`：对单个插件包跑 kernel_api 校验（与 phpunit
    套件同一套 `FragmentCheck`），输出 JSON。
  - `--report <path>`：报告落盘路径，默认 `var/contract-build-report.json`。
- **冲突检查范围**（跨插件 + 插件 vs 宿主，任一命中即失败）：
  ① path+method 撞车；② operationId 撞车；③ 权限码域段违规——插件
  只许 `sand_<插件词干>:` 域，声明 `system:`/`mcp:` 域即失败，同一码
  两处定义不同标题也算冲突；④ MCP 工具名撞车、插件工具名不带
  `<词干>_` 前缀；⑤ 错误码重复定义（插件总表与宿主或其他插件撞码）；
  ⑥ `x-error-codes` 引用了未登记码；⑦ public/authenticated 接口挂了
  `x-mcp` write/high_risk 档（写工具必须有明确权限码）。
- **报告是机器可读 JSON**：`{result, sources[], artifacts[{path,sha256,
  status}], conflicts[], warnings[], incomplete_operations[]}`，满足
  门禁自保护「过了要有证据」的要求。

### 5. 运行期怎么改

**结论（逐处落点）：**

- **`server/config/route.php`**：保留为手写文件，内容只剩三块——
  文件头注释、`dev 哨兵开始/结束` 哨兵段（use 语句 + dev 5 条路由 +
  `Route::disableDefaultRoute()` 前置声明不动）、末尾
  `require __DIR__ . '/route.generated.php';`。**哨兵段生成器一个字节
  都不碰**；生成器 `--check` 时反过来断言「route.php 里哨兵段之外不
  再有手写 `Route::` 调用」，防止有人绕开契约手挂路由。
- **`support/bootstrap.php` 插件路由扫描**：`Util::scanDir` 喂
  `Route::load` 的那段（140-147 行）改成
  读 `config_path().'/plugin-routes.generated.json'` 的 `routes` 字段逐目录喂 `Route::load`（JSON 清单，免动态 require）。
  清单由 contract-build 在装/升/卸插件时重算：契约片段存在的插件挂
  `config/route.generated.php`，无片段的 legacy 插件（即 sand-core 兼容
  层，它自带 route.php、没有 contract/）挂原 `config/route.php` 并在
  报告里记 warning——不替 sand-core 立契约，它是退役中的旧面，走
  退役流程不带进新契约。这样路由表彻底没有运行期目录扫描，M-12 达标。
  同文件里插件 `bootstrap`/`middleware` 的加载（86-138 行）是 webman
  插件配置机制，与路由注册表无关，本期不动。
- **启动期一致性校验**（「构建期生成 + 启动期校验」的后半句）：加
  一个启动守卫（放 `app/bootstrap/` 与现有 ProductionDebugGuard 并列），
  校验两件事——每个 `plugin-routes.generated.json` 里列的文件存在且头
  部源哈希 == 对应 `plugin/<app>/contract/openapi.yaml` 的 sha256；宿主
  `route.generated.php` 头部源哈希 == `contract/openapi.yaml` 的 sha256
  （无插件场景）。不一致 → 拒绝启动。这条把「生成物被手改/插件换包没
  重跑收集」挡在启动期。
- **权限码消费点**：`PermissionRegistry` 生成后，控制器 `run($request,
  '字面量', ...)`、`AnnouncementHttp::requirePermission()`、
  `FileHttp::PERMISSION_*` 常量、`DisableCheckAccess` 常量、
  `DevLifecycle/dev 任务执行者提供器` 的 viewAll 判定——字面量全部换成
  `PermissionRegistry::XXX` 常量（不是换成查表调用，是编译期就能解析
  的类常量，零运行期开销、PHPStan 可追）。`RoleService::MENUS` 改读
  `PermissionRegistry::TREE`（顺手把 file/task/plugin/update 六个判定
  码补进树，盘点 3.2 节的缺口）；`UserService::ADMIN_CODES`、
  `MenuService` 菜单 key 同理换生成常量。
- **MCP**：`dev 工具装配表::specs()` 改为「从 `McpToolRegistry::SPECS` 取
  dev_only 条目 + 按 name 从手写 runner 映射取 runner 拼 ToolSpec」；
  runner 映射（name → ToolRunner 实例）留在 dev 工具装配表 或并列的手写
  类里——runner 是代码，本来就不归契约管。`dev MCP 控制器` 的每请求
  装配本期**不动**（只换工具表数据来源；启动期一次装配是优化项，
  不在本设计承诺范围）。
- **错误码**：建 `ErrorCodeRegistry` 生成物；异常类、`ApiOutcome::fail`
  调用点、`OrgDbMap` 的约束名→码映射在实施阶段逐文件改引用常量，
  56 个码全部进 `x-error-catalog`（含 HTTP 状态映射）。

### 6. kernel_api 契约测试套件

**结论：**

- **校验逻辑放内核**：`server/src/Contract/KernelApi/FragmentCheck.php`，
  纯 PHP、不依赖 PHPUnit，输入 = 插件包目录 + 宿主契约上下文，输出 =
  结构化结果数组（给 CLI 和 PHPUnit 共用）。**放 src 不放 tests** 的
  理由：contract.md 定过「kernel_api 套件随内核提供」，它必须在装了
  `--no-dev` 的未编译版上也能跑；逻辑与 CLI 共用一份，不许抄第二份。
- **PHPUnit 套件**：`server/tests/Contract/KernelApiTest.php`——遍历
  `server/plugin/*/contract/openapi.yaml`（已装插件）+
  `server/tests/Fixtures/plugin/` 下的契约夹具（现有 sand-hello/
  sand-other 夹具目录正好扩展），每个插件跑 `FragmentCheck` 断言零
  冲突；进 phpunit 新 `contract` 套件。
- **插件仓库怎么复用**：不复制、不发独立公共包。约定路径——插件开发
  环境 = 宿主仓 + 插件以 composer path 仓库装入；插件 CI 里 checkout
  锁定版本的宿主、装插件、跑 `php tools/contract-build --kernel-api
  <插件包目录>`（输出 JSON 可直接当 CI 证据）。这符合 contract.md §10
  「契约包随宿主内核分发、插件开发期作 dev 依赖引用」的已定口径。
- **套件检查什么**（`FragmentCheck` 逐条）：① 片段能解析、是合法
  OpenAPI 3.x 且有 `openapi`/`info`/`paths`；② 所有 path 在
  `/api/admin/v1/plugin/<app>/` 前缀下（契约 67 行已定前缀形态）；
  ③ operationId 片段内唯一、全局（与宿主+其他已装插件）不撞；
  ④ 每个非 public operation 都有 `x-permission`，码形状
  `sand_<词干>:<资源>:<动作>`；⑤ `x-mcp` 的 tier 合法、工具名带
  `<词干>_` 前缀且不撞名；⑥ `x-error-codes` 每个码在片段自带
  `x-error-catalog` 或宿主总表里有登记；⑦ path+method/权限码/错误码
  与宿主和其他插件不撞。
- **拿 sand-workflow 跑示例**：`php tools/contract-build --kernel-api
  ~/agent-work/grokbot/wf-code-1116/repo`——它目前是老 0.x 布局
  （info.ini + install.sql、没有 contract/openapi.yaml），预期结果就是
  校验器报「缺契约片段」被拒，这本身演示了 fail-closed；等插件仓按新
  布局补 `contract/openapi.yaml` 后同一命令跑通即算示例验收。设计不
  替插件写片段，只把约定和命令定死。

### 7. 门禁接入

**结论：**

- `tools/pre-merge.sh --fast` **加第 2 步**（紧跟 php -l 之后，它不需要
  vendor、纯文件操作、跑得快），总步数 6 → 7，`echo` 编号全部改 `N/7`：
  1. PHP 语法检查
  2. **契约生成物一致性（`php tools/contract-build --check`）** ← 新增
  3. 危险函数白名单扫描
  4. dev-only 引用门禁
  5. TypePHP 禁用写法扫描
  6. PHPUnit Unit 套件
  7. PHPStan
  （`--full` 追加 integration、`--typephp` 追加可选编译，照旧。）
- **CI**：仓里现有 CI 只有 `.github/workflows/dependency-audit.yml`
  （composer/frontend 审计），还没有跑 PHP 检查的门禁 task。设计口径：
  一致性门禁的强制点先落在 pre-merge.sh（本地合并前必跑）；往 GitHub
  Actions 加 `contract` task 是**门禁文件改动、按 M-09 要走人审**，实施
  清单里列为单独一步、给出 task 草案，但不把它当本地门禁的前置条件。
- **「不一致即失败」判定细节**：`--check` = 重新生成到
  `sys_get_temp_dir()` 下临时目录，与仓内文件**逐字节 diff**，有差异
  列出文件名退出 1。**不吃白名单/空白容忍**——生成器输出必须确定性
  （LF 行尾、键序固定、**头部不写时间戳只写源哈希**，否则每次都漂移）。
  头部源哈希只是快速提示（人看一眼知道源变了没有），判定一律以逐字节
  比对为准。

### 8. 权限点落库边界

**结论写死：本期权限注册表只是 `PermissionRegistry.php` 文件产物，不做
任何自动落库。** `sand_system_role_permission` 照旧存权限码字符串、
角色授权接口照旧工作；「权限点清单表、编号表、启动时同步注册表入库」
等编号表方案定了再做。注册表的价值是「判定处/展示树/契约」三处从同一
个源生成、不再各写一遍，以及给 MCP/测试/文档提供机器可查的总表——这
两件事不依赖落库。

### 9. 信封不一致收口

**结论：统一成 skill 契约口径——成功信封补 `code` 与 `message`：**

```json
{"code": "OK", "message": "操作成功", "data": {...}, "request_id": "..."}
```

失败信封 `{code, message, fields, request_id}` 不变。

**理由：** ① 后端 skill（backend-structure.md §三）写明的契约就是成功
`code:"OK"`，且信封取值域「已定（总监 11-17）」——契约文档是权威，
现状代码 `{data, request_id}` 是不一致的一方，收编代码而不是收编文档；
② 成功失败都带 `code`，前端分支只需要看一个字段，`x-error-codes` 的
语义也干净（「信封 code 字段的取值域」）；③ 改动收口在
`ApiOutcome::ok()` 一个函数 + 前端 `web/src/api` 成功判定处 +
`tests/http-error-data-test.php` 预期，面不大但要前后端同步改（见风险
节）。

---

## 三、openapi.yaml 骨架示例

骨架原则：OpenAPI 3.1；每个 path 的 operation 必写 `operationId` +
`x-permission`；`x-mcp`/`x-error-codes` 按需。下面 5 个代表性接口覆盖
四种权限形态 + 三档 MCP + 错误码声明，另附三个顶层段。

```yaml
openapi: 3.1.0
info:
  title: SandAdmin 1.x 管理接口
  version: "1.0"
  description: 唯一契约权威。路由表、权限注册表、MCP 工具表、错误码注册表、TS 类型、测试骨架均由 tools/contract-build 从本文件生成。

# ── 顶层段一：错误码总表（56 个现存码全量登记，此处只示意 4 个）──
x-error-catalog:
  VALIDATION_FAILED:   {http_status: 422, summary: 请求内容有误，请按提示修改, common: true}
  UNAUTHENTICATED:     {http_status: 401, summary: 请先登录, common: true}
  PERMISSION_DENIED:   {http_status: 403, summary: 你没有权限做这个操作, common: true}
  ROLE_NAME_TAKEN:     {http_status: 422, summary: 这个角色名称已经有人用了}

# ── 顶层段二：不挂接口的权限码（viewAll 等效码、MCP 凭据 scope）──
x-permissions:
  - {code: system:task:index,  title: 查看全部后台任务, note: TaskActor.viewAll 等效码}
  - {code: system:plugin:index, title: 查看全部插件申请, note: LifecycleActor viewAll}
  - {code: mcp:dev:routes,     title: dev 路由检查工具,   note: MCP 凭据 scope，不进权限树}

# ── 顶层段三：权限树（顶掉 RoleService::MENUS；动作段自动拼成权限码）──
x-permission-tree:
  - group_key: org
    group_title: 组织与用户
    menu_key: system:user
    title: 用户管理
    actions:
      - {action: index,          label: 查看}
      - {action: create,         label: 添加}
      - {action: reset-password, label: 重置密码}
      - {action: assign-role,    label: 分配角色}

# ── 顶层段四：独立 MCP 工具（不绑 v1 接口，只许 dev_only）──
x-mcp-tools:
  - name: dev_inspect_routes
    tier: read
    title: 路由与契约检查
    description: 读取当前路由表与契约文件（只读）
    permission: mcp:dev:routes
    dev_only: true
    input: {type: object, properties: {}, additionalProperties: false}

paths:
  # ① 单码 + 错误码声明（最常见形态）
  /api/admin/v1/users:
    get:
      operationId: usersIndex
      summary: 用户列表
      x-permission: system:user:index
      x-error-codes: [VALIDATION_FAILED]
      parameters:
        - {name: page, in: query, schema: {type: integer, minimum: 1}}
      responses:
        '200': {description: 契约信封, content: {application/json: {schema: {$ref: '#/components/schemas/UserListEnvelope'}}}}

  # ② any_of 组合码（disable-check：update 或 delete 任一）
  /api/admin/v1/users/{user_id}/disable-check:
    get:
      operationId: usersDisableCheck
      summary: 停用前检查
      x-permission:
        any_of: [system:user:update, system:user:delete]
      parameters:
        - {name: user_id, in: path, required: true, schema: {type: integer}}
      responses:
        '200': {description: 契约信封, content: {application/json: {schema: {$ref: '#/components/schemas/DisableCheckEnvelope'}}}}

  # ③ super 判定 + high_risk MCP（插件上传：机器身份只能生成待确认请求）
  /api/admin/v1/plugins/upload:
    post:
      operationId: pluginsUpload
      summary: 上传插件包
      x-permission: super
      x-mcp:
        name: plugin_upload
        tier: high_risk
        title: 上传插件包
        description: 登记插件安装申请（高危，只生成待确认请求）
        input:
          type: object
          properties:
            package_ref: {type: string}
          required: [package_ref]
          additionalProperties: false
      x-error-codes: [PLUGIN_PACKAGE_INVALID, PLUGIN_BUSY]
      responses:
        '200': {description: 契约信封, content: {application/json: {schema: {$ref: '#/components/schemas/LifecycleRequestEnvelope'}}}}

  # ④ authenticated + read 档 MCP（任务列表：登录即可，viewAll 码看全部）
  /api/admin/v1/tasks:
    get:
      operationId: tasksIndex
      summary: 后台任务列表
      description: 登录即可；持 system:task:index 看全部，否则只看本人（属主判定在代码内）。
      x-permission: authenticated
      x-mcp:
        name: task_list
        tier: read
        title: 查询任务列表
        input:
          type: object
          properties:
            state: {type: string, enum: [queued, running, succeeded, failed, cancelled]}
          additionalProperties: false
      responses:
        '200': {description: 契约信封, content: {application/json: {schema: {$ref: '#/components/schemas/TaskListEnvelope'}}}}

  # ⑤ public 公开端点（找回能力查询；写端点走敏感限流桶，见 api-conventions）
  /api/admin/v1/auth/recovery/capability:
    get:
      operationId: recoveryCapability
      summary: 查询找回渠道显隐
      x-permission: public
      responses:
        '200': {description: 契约信封, content: {application/json: {schema: {$ref: '#/components/schemas/RecoveryCapabilityEnvelope'}}}}

components:
  schemas:
    Envelope:        # 成功/失败信封的公共形状，写契约时统一引用
      type: object
      properties:
        code:       {type: string}
        message:    {type: string}
        data:       {}
        fields:     {type: object, additionalProperties: {type: array, items: {type: string}}}
        request_id: {type: string}
      required: [code, request_id]
```

dev 说明：dev 登录 5 条路由**不进 paths**——它们是开发构建私有链，
契约面之外，继续在 route.php 哨兵段手写；4 个 dev 工具也不绑接口、
走顶层 `x-mcp-tools`（上面已示例）。

---

## 四、生成产物头部格式与哈希算法

**文件头**（PHP 与 TS 同一形状，注释符按语言换；**不写时间戳**）：

```php
<?php

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml + 插件片段 2 份
// 源哈希: sha256:<64 位小写十六进制>
// 生成器: contract-build v1
```

**源哈希算法**（`source_hash`）：

1. 收集全部输入源文件：宿主 `contract/openapi.yaml` + 每份被收集的插件
   `contract/openapi.yaml`（按仓内相对路径排序）。
2. 对每个文件算 `sha256(原始字节)`。
3. 拼成文本：每行 `<相对路径>\t<文件sha256>`，末尾再加一行
   `contract-build\tv1`（生成器版本行——生成器逻辑变了哈希也变，
   产物自然失效，不需要单独的产物版本号）。
4. `source_hash = sha256(上面那段文本的 UTF-8 字节)`。

**为什么用文件字节哈希不用 YAML 语义哈希**：契约源是人写的，换行、
注释、键序差异都该让产物重生成一次——宁可多生成不可漏生成；反正
`--check` 判定的是产物逐字节一致，源哈希只是人读的提示与启动期校验
的比对基准。

**`--check` 判定**：逐字节比对仓内产物与临时目录重生成物；任一文件
不同即失败并列出文件名。启动期一致性校验（设计点 5 的守卫）只比头部
`source_hash` 与源文件 sha256——运行期不重新生成，只查「这份产物是不
是从当前源出来的」。

---

## 五、分阶段实施清单（可直接当派工单）

| # | 工作包 | 内容 | 验证方式 |
| --- | --- | --- | --- |
| 0 | 依赖 | `composer require symfony/yaml:^7`（进 require），lockfile 入库 | `composer validate --strict`；lockfile 变更过依赖评审 |
| 1 | 契约文件 | 手写 `contract/openapi.yaml`（105 条常驻路由全量转写、45 权限码、4 dev 工具、56 错误码）+ `contract/api-conventions.md` | contract-build 能解析；与 `盘点.md` 清单逐条对平（路由 105、权限码 45、工具 4、错误码 56 数量与明细一致）——这一步是整个项目最大的写字量 |
| 2 | 构建核心 | `server/src/Contract/Build/`：YAML 解析、模型、冲突检查七条、JSON 报告；`tools/contract-build` 薄壳 | `tests/Unit` 加 Build 用例（合法源过、七类冲突各一条反例）；`--kernel-api` 报缺失片段 |
| 3 | 生成器 + 路由切换 | 六个生成器；route.php 改 require 生成表；route.generated.php 落仓 | 启动后 `Route::getRoutes()` 快照与现状 105 条逐条相等（写一次性比对脚本）；`php -l`、危险函数扫描、dev-only 门禁过 |
| 4 | 权限/错误码消费面 | 控制器 `run()`/`requirePermission`/`FileHttp` 常量/`DisableCheckAccess`/`RoleService::MENUS`/`UserService::ADMIN_CODES`/`MenuService` 菜单 key 全部换 `PermissionRegistry::`/`ErrorCodeRegistry::` 常量 | grep 全仓不再出现 `system:`/`ROLE_` 等字面量散落在判定处（注册表文件与注解除外）；既有 unit/integration 全绿 |
| 5 | MCP 消费面 | `dev 工具装配表::specs()` 改读 `McpToolRegistry` + runner 映射；ToolSpec.route 填 v1 路径 | dev 构建下 4 个工具行为不变（现有 MCP 测试全绿）；SPECS 与 x-mcp-tools 一致 |
| 6 | 信封收口 | `ApiOutcome::ok()` 输出 `{code:"OK",message,data,request_id}`；`web/src/api` 成功判定改 `code === 'OK'`；`tests/http-error-data-test.php` 预期同步 | 全量 unit + 前端 vitest/tsc；手工冒烟一次登录→列表（确认前端不误判失败） |
| 7 | 插件收集与启动校验 | `--plugins`/默认收集；`plugin-routes.generated.json` 替换 scanDir 喂 Route::load；启动守卫比哈希；lifecycle 装/升/卸后调 `ContractBuild::collectPlugins()` | 用 `tests/Fixtures/plugin/` 夹具做装插件事务级用例：无片段 legacy 插件走 route.php 回退 + warning；有片段插件生成 route.generated.php；改片段不重跑收集 → 启动拒启 |
| 8 | kernel_api 套件 | `FragmentCheck` + `KernelApiTest` + contract 套件进 phpunit.xml.dist | 套件在仓内绿；`php tools/contract-build --kernel-api ~/agent-work/grokbot/wf-code-1116/repo` 输出「缺 contract/openapi.yaml」被拒（演示 fail-closed） |
| 9 | 测试骨架接入 | `tests/Contract/Generated/` 生成物落仓 + `contract` 套件；确定性用例四类断言跑通 | `--check` 通过；contract 套件绿；incomplete_operations 进 JSON 报告 |
| 10 | 门禁接入 | pre-merge.sh 加第 2 步改 `N/7`；CI contract task 草案交人审（门禁文件改动） | 故意手改一个生成物 → `pre-merge.sh --fast` 在第 2 步挂 |

**先后顺序说明**：0→1→2 必须串行（没依赖没法解析、没契约没东西可
生成）；3/4/5 都依赖 2 且彼此可并行分包；6 独立可插队（纯收口改动）；
7 依赖 3；8 依赖 2；9 依赖 3；10 放最后（等生成物稳定了再把它变门禁，
否则天天被自己的漂移打）。

**风险最大的一处**（如实说）：第 6 步信封收口——`code:"OK"` 要前后端
+ 测试断言 + 可能的前端「有 code 字段即当失败」存量逻辑同步改，一处
漏改就是「全接口误报失败」级别的回归；其次第 1 步把 105 条路由的
「静态先于参数路径」顺序语义和权限字面量无损搬进 yaml 也极易出错，
靠第 3 步的路由快照比对兜底。
