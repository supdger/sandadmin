# SandAdmin 1.x 接口约定（api-conventions）

本文件是 `contract/openapi.yaml` 的人读配套文档：写清跨所有接口的通用约定。
机器可读的部分（错误码总表、权限树、MCP 工具表）以 openapi.yaml 的 `x-` 段为
唯一权威，本文只解释用法与纪律，不重复登记。

## 1. 适用范围与版本前缀

- 管理端接口统一挂在 `/api/admin/v1` 前缀下；插件接口统一挂
  `/api/admin/v1/plugin/<app>/`（`plugin/` 段做命名空间隔离）。
- 路由表、权限注册表、MCP 工具表、错误码注册表、前端 TS 类型、测试骨架
  六样产物全部由 `tools/contract-build` 从契约机械生成；手改产物会在
  `tools/contract-build --check` 一致性门禁被打回。
- 三组路由清单口径（openapi.yaml `x-permission` 取值决定归属）：
  - **公开清单**（`public`）：不鉴权。当前 100 条常驻路由里没有公开端点。
  - **登录即可**（`authenticated`）：只要求有效身份，无具体权限码。
  - **持码清单**（单码或 `any_of`/`all_of` 对象形态）：要求对应权限码；
    `super` 为超管判定（PackageOps::guard 一族）。
  - 「本人或持码可看全部」这类属主规则：路由层只声明 `authenticated` 或
    `any_of`，属主兜底是代码逻辑，在 operation `description` 里写明。

## 2. 响应信封

### 2.1 成功信封

```json
{"code": "OK", "message": "操作成功", "data": {}, "request_id": "req_xxx"}
```

- HTTP 恒为 200；`code` 固定字符串 `OK`；`data` 为业务负载（对象、数组、
  标量或 null，空结果用 `{}`）。
- 成功判定只看 `code === 'OK'`；前端 `web/src/lib/adminClient.ts` 已按此收口。

### 2.2 失败信封

```json
{"code": "ROLE_NAME_TAKEN", "message": "这个角色名称已经有人用了", "fields": {"name": ["这个角色名称已经有人用了"]}, "request_id": "req_xxx"}
```

- HTTP 用真实状态码（4xx/5xx）；`code` 是 `x-error-catalog` 登记的稳定码，
  只用于分支判断，不直接展示；`message` 是大白话可展示文案。
- `fields` 是 `{字段名: [文案]}` 的字段级错误，空集输出 `{}`。
- `request_id` 与响应头 `X-Request-Id` 一致，链路追查用。

### 2.3 二进制下载端点

`/files/{file_id}/download`、`/tasks/{task_id}/download` 成功时返回文件流；
失败仍按 2.2 返回 JSON 信封（`Content-Type: application/json` 区分）。

### 2.4 错误码总表

权威清单是 openapi.yaml 顶层 `x-error-catalog`（`码 → {http_status, summary}`），
生成 `ErrorCodeRegistry::HTTP_STATUS`/`ALL` 常量表。

- 条目可加 `const: <大写蛇形常量名>` 给 PHP 常量另取短名：线上码值不变，
  只有常量名变化（用于码名超 4 词上限的既有码，如
  `MCP_HIGH_RISK_NOT_OPEN` → `ErrorCodeRegistry::MCP_HIGH_RISK_CLOSED`）；
  不写 `const` 时常量名即码名。两个码声明同一 `const` 构建失败。

- 五个通用码 `VALIDATION_FAILED`/`UNAUTHENTICATED`/`PERMISSION_DENIED`/
  `CSRF_REJECTED`/`INTERNAL_ERROR` 标 `common: true`，接口不用逐个在
  `x-error-codes` 声明。`CSRF_REJECTED` 由全局 CsrfMiddleware 对
  `/api/admin/v1` 写操作发出。
- 业务码必须在 `x-error-catalog` 先登记再在接口 `x-error-codes` 引用；
  未登记引用直接构建失败（「码先登记再使用」是门禁不是纪律）。
- `VALIDATION_FAILED` 在装配层有 400/422 两种状态取值，语义相同。

## 3. 请求通用约定

- **分页**：列表接口查询参数 `page`（≥1，默认 1）与 `page_size`（1–100，
  默认 20）；响应 `data` 为 `{items, total, page, page_size}`。
- **幂等**：非 GET 写操作支持 `Idempotency-Key` 请求头
  （`^[A-Za-z0-9._~-]{1,128}$`）；同键不同体返回 409
  `IDEMPOTENCY_KEY_REUSED`，同键同体重放返回首次快照。
- **请求标识**：`X-Request-Id` 头可传入（合法字符集
  `^[A-Za-z0-9._~-]{1,128}$`），否则服务端生成 `req_` 前缀随机值。
- **并发控制**：编辑类写接口用 `version` 字段乐观锁，冲突返回 409
  `STALE_VERSION`。
- **参数命名**：路径/查询/JSON 字段一律小写蛇形，与数据列同名。

## 4. 权限码与扩展字段

- 权限码形如 `<域>:<资源>:<动作>`；宿主域为 `system`，插件域为
  `sand_<插件词干>`；`mcp:dev:*` 是 MCP 凭据 scope 命名空间，不进权限树。
- operation 级扩展字段：`x-permission`（必写）、`x-mcp`（可选）、
  `x-error-codes`（可选，业务码）、`x-handler`（路由表处理器，宿主内部用）。
- 顶层扩展段：`x-error-catalog`（错误码总表）、`x-permissions`（不挂接口
  的码）、`x-permission-tree`（角色配置展示树）、`x-sidebar-menus`
  （侧栏菜单键）、`x-admin-permissions`（管理员高危码清单）、
  `x-mcp-tools`（不绑接口的独立工具，只许 `dev_only: true`）、
  `x-connections`（插件命名连接声明，宿主契约不出现）。

## 5. MCP 工具三档

`x-mcp.tier` 与 `x-mcp-tools[].tier` 只许 `read`/`write`/`high_risk`：

- `read`：只读直执。
- `write`：普通写，必带 `idempotency_key`，支持 `dry_run`（只回显不执行）。
- `high_risk`：高危写，不执行，只生成待确认请求（待确认链路未开放前按
  `MCP_HIGH_RISK_NOT_OPEN` 返回）。

`input` 里不写 `idempotency_key`/`dry_run`：生成器按档位自动注入
（write/high_risk 注入 `idempotency_key` 必填 + `dry_run` 可选），
手写与注入形状不一致直接构建失败。`public`/`authenticated` 接口挂
write/high_risk 档同样构建失败（写工具必须有明确权限码）。

## 6. 插件命名连接（x-connections）

插件使用独立命名连接（独立库，如 SandAI 的 `sandai`）时在片段顶层声明
`x-connections` 列表，每条连接五个必填键加两个条件必填键：

```yaml
x-connections:
  - name: sandai                    # 小写蛇形，片段内唯一；default/pgsql/mysql 保留
    connections_per_process: 2      # 每进程占用连接数（主从各 1 = 2），正整数
    ledger: per_connection          # 固定值：迁移账本记在本连接的库、迁移锁按连接加
    outbox: business_database       # 或 none；outbox 表随业务所在连接
    outbox_relay: per_connection    # outbox 为 business_database 时必填固定值
    audit_sink: kernel_async        # 或 none；审计经事件/队列异步落内核审计面
    audit_records: local            # audit_sink 为 kernel_async 时必填固定值
```

- `outbox`/`audit_sink` 取 `none` 表示该连接不用 outbox/审计，此时对应的
  `outbox_relay`/`audit_records` 不许出现；取了 `none` 又声明伴随键按
  构建失败处理，「未声明」与「声明不用」由此可分。
- `connections_per_process` 汇总进构建产物
  `server/config/connection-budget.generated.json`
  （`plugin_per_process`），容量评审按公式「进程数 ×（宿主连接数 +
  命名连接数）」重算连接账；装带命名连接的插件必须先看这份产物漂移。

## 7. dev 面边界

开发构建私有的 5 条免登录路由与 4 个 `dev_*` MCP 工具属开发链：
路由在 `server/config/route.php` 的哨兵段手写、不进本契约 paths，
打包时由开发构建剔除；dev 工具走顶层 `x-mcp-tools` 且必须
`dev_only: true`。生产制品整条链不存在。
