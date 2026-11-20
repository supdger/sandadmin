# request_id 与 trace 传播规则（M-18a / O3 文本部分）

出处：队列 1-15「可观测基础 M-18a」O3 行——1.0 以 request_id +
结构化日志覆盖单体内链路，**不接 OTel SDK**（L-02 属 1.1）；本文件
只定传播约定，供 1.1 接 SDK 与 M-10 出网透传直接照做。

内部跳间语义参照 Kratos metadata：跨进程/出网传播的头用
`x-md-global-*` 前缀（全链路有效），只传一跳的用 `x-md-local-*`
（到下游即止，不再继续传播）。头名一经采用即契约，改名走契约变更。

## 一、标识符定义

| 标识 | 生成方 | 格式 | 作用域 |
| --- | --- | --- | --- |
| `request_id` | `RequestIds`（透传合法 `x-request-id`，否则生成 32 hex） | `[A-Za-z0-9_-]{8,128}` | 一次 HTTP 请求 / 一个被投递任务的执行上下文 |
| `trace_id` / `span_id` | 上游或本服务（1.1 OTel SDK 起生成） | W3C traceparent 格式 | 一条分布式调用链 |
| `task_id` / `attempt` | 任务子系统（task_run.public_id / attempt 列） | uuid / int | 日志辅助串联，不进请求头 |

`request_id` 是业务侧串联键（日志、错误信封、`X-Request-Id` 响应头、
审计记录共用同一值）；`traceparent` 是标准 trace 语境，1.1 接 SDK 后
两者并存：日志同时带 `request_id`（顶层字段）与 `trace_id`/`span_id`
（trace-context 头透传值），不互相替代。

## 二、传播规则表

| 跳 | 方向 | 头 | 规则 |
| --- | --- | --- | --- |
| HTTP 入站 | 客户端 → 本服务 | `x-request-id` | 合法格式透传，否则 `RequestIds` 重新生成；值写入 `RequestContext` 与 `RequestScope`，本请求全部日志/错误信封/审计共用 |
| HTTP 内部 | 中间件 → 控制器 → Logic/Repository | 无头（进程内） | 经 `RequestContextStore`（挂 `Request::$context`）与 `RequestScope`（`Webman\Context`）传递，不走 HTTP 头 |
| HTTP → 后台任务 | `TaskDispatcher::dispatch` | payload 保留键 `__context.request_id` | 发起请求的 request_id 写入 payload（不落新列）；`TaskExecutor` 认领后绑进 `RequestScope`，任务日志带 `request_id`+`task_id`+`attempt`；handler 约定不读 `__context` |
| 任务 → 再派生任务 | `TaskDispatcher::dispatch`（任务内调用） | 同上 | 任务内再投递时沿用当前 `RequestScope` 的 request_id，保持同一条业务链 |
| 本服务 → 出网调用 | M-10 外呼封装 | `traceparent`（W3C）+ `x-md-global-request-id` | 1.1 起外呼统一经封装层注入：`traceparent` 原样透传/续链，`x-md-global-request-id` 携带当前 request_id；1.0 只定义约定不实现 |
| 出网 → 对端回传 | 对端响应 → 本服务 | `x-md-global-request-id`（若对端支持） | 响应头仅作参考记录进日志 context，不回写已生成的 request_id |
| 跨进程内部调用（plugin/子进程） | 进程 A → 进程 B | `x-md-global-request-id`、`x-md-global-traceparent` | 跨进程必须走 global 前缀头；`x-md-local-*` 头到对端即止，禁止二次转发 |
| 单跳内部调用 | 本服务 → 直接下游一跳 | `x-md-local-*`（按需定义） | 只表达「本跳上下文」，下游不得继续向它的下游传播 |

## 三、头名约定（成文成表）

| 头 | 前缀类别 | 语义 | 1.0 状态 |
| --- | --- | --- | --- |
| `x-request-id` | 既有契约 | 入站请求编号透传/生成 | 已实现 |
| `X-Request-Id`（响应头） | 既有契约 | 成功响应盖章（ErrorMiddleware/TaskController） | 已实现，保持 |
| `traceparent` | W3C 标准 | trace 语境（version-traceid-spanid-flags） | 1.0 透传不解析；1.1 OTel 接入 |
| `x-md-global-request-id` | global | 跨进程/出网传播的 request_id | 约定文本，1.1/M-10 实现 |
| `x-md-global-traceparent` | global | 跨进程传播的 traceparent（与标准头同值时可只发 traceparent） | 约定文本 |
| `x-md-local-*` | local | 单跳上下文，具体键位按调用点登记 | 预留，首个使用点出现时成文 |

约束：

- `x-md-global-*` 头值必须过 `RequestIds` 同口径校验
  （`[A-Za-z0-9_-]{8,128}`）才允许写入日志/审计，防日志注入。
- `traceparent` 按 W3C 规范校验（version `00`、32 hex trace-id、
  16 hex span-id）；非法值丢弃并重新生成，不拒绝请求。
- 响应侧只允许 `X-Request-Id` 一个追踪头对外承诺；其余头不进
  对外契约，内部跳不经 HTTP 头传递。

## 四、与既有机制的关系

- `request_id` 已贯通：HTTP（RequestContextStore::ensure）→ 结构化
  日志（RequestScope + JsonLogFormatter）→ 错误信封
  `{code,message,fields,request_id}` → 任务执行（payload `__context`）。
- `traceparent`/`x-md-global-*` 为 1.1 预留：本批不解析、不生成
  span、不接 SDK；外呼封装（M-10）落地时按本文件第二节执行，
  宿主只保证「注入点存在」——中间件链、DBAL 事件、任务执行边界
  均已是 RequestScope/RequestContext 可达的位置。
- 审计日志（security 通道）同受 RequestScope 注入，安全事件可凭
  request_id 与业务日志对账。
- 边界：`Webman\Context` 按 Fiber 隔离，第三方 SDK 在自建 Fiber 内
  执行的代码（如 MCP SDK 的 StatelessProtocol）看不到请求作用域；
  这类路径必须像 `McpKernel → LogAuditSink` 一样把 request_id 显式
  放进日志 context，不能依赖 processor 自动注入。
