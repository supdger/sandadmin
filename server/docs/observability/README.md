# 可观测基础：指标契约、告警规则与错误预算（M-18a / O1–O4）

出处：队列 1-15「可观测基础 M-18a」，对应顶层架构设计 v4 O 组——
O1 结构化日志与 request_id、O2 指标、O3 链路追踪、O4 告警与错误预算。
本批只交**契约文本与规则文本**：`/metrics` 端点实现在队列 2-6（O2 的
「做：基础计数 + metrics 端点」中端点部分未做），告警通道接入属 1.1
（L-03），OTel SDK 接入属 1.1（L-02）。

## 一、日志格式契约（O1，已实现）

`config/log.php` 两个通道（`default`→`runtime/logs/webman.log`、
`security`→`runtime/logs/security.log`）统一为单行 JSON，由
`Sandadmin\Infra\Log\JsonLogFormatter` 输出，字段：

| 字段 | 说明 |
| --- | --- |
| `time` | ISO8601 时间（Monolog datetime 改名） |
| `level` / `level_name` | Monolog 级别数值与名称 |
| `channel` | `default` / `security` |
| `message` | 日志文案（固定短句，变量走 context） |
| `context` | 调用方传入的上下文对象；空时省略 |
| `request_id` | 请求/任务作用域编号；无绑定时整个字段省略 |
| `task_id` / `attempt` | 仅任务执行期出现：任务 public_id 与当前尝试次数 |
| `extra` | Monolog 其余 extra；空时省略 |

request_id 注入链路：`RequestContextStore::ensure()`（全局
`RequestContextMiddleware` + 各业务中间件/控制器统一入口）解析
`x-request-id`（合法透传，否则生成 32 位 hex）→ 写 `RequestContext` →
`RequestScope::bind()` 存入 `Webman\Context`（fiber/请求作用域，无静态
可变状态）→ Monolog processor 注入 `extra` → formatter 提升为顶层字段。
CLI 与 task_worker 无请求上下文时 processor 静默跳过，不报错。

## 二、request_id 与 trace 传播（O3 文本部分）

传播规则全文见 [trace-context.md](trace-context.md)：request_id 与
`traceparent` 在 HTTP、后台任务、出网调用间的传递约定（`x-md-global-*`
跨跳、`x-md-local-*` 单跳）。1.0 只透传不实现 OTel SDK。

## 三、指标名契约（O2 文本部分）

`/metrics` 端点（队列 2-6 实现）必须按下表逐字输出指标名与标签；
告警规则 [alerts.yml](alerts.yml) 按此表编写，改名需同步两处。
全部指标加 `sandadmin_` 前缀，避免与 node exporter / client_golang
同名指标（如 `process_*`）混淆。

| 指标名 | 类型 | 标签 | 口径 |
| --- | --- | --- | --- |
| `sandadmin_http_requests_total` | counter | `method`、`route`（契约路径模板）、`status_class`（`2xx`/`3xx`/`4xx`/`5xx`） | 全部 HTTP 请求计数 |
| `sandadmin_http_request_duration_seconds` | histogram | `method`、`route` | 请求耗时，`le` 桶须覆盖 ≤0.1s…≥5s |
| `sandadmin_db_connections` | gauge | `state`（`active`/`idle`） | 当前 DB 连接数（主库口径） |
| `sandadmin_db_connection_limit` | gauge | 无 | 主库 `max_connections` 配置值 |
| `sandadmin_task_queue_depth` | gauge | `state`（`queued`/`running`） | `sand_system_task_run` 按状态计数 |
| `sandadmin_task_oldest_queued_seconds` | gauge | 无 | 最老 queued 任务的等待秒数 |
| `sandadmin_process_start_time_seconds` | gauge | `worker_id`（`hostname-pid`） | 各 webman 进程（web/task_worker/monitor/log_retention 等）启动 unix 秒；进程重启告警依据 |
| `sandadmin_process_resident_memory_bytes` | gauge | `worker_id` | 各进程 RSS 字节 |
| `sandadmin_schedule_interval_seconds` | gauge | `schedule` | 各定时任务周期秒数 |
| `sandadmin_schedule_last_success_timestamp_seconds` | gauge | `schedule` | 各定时任务最近一次成功 `schedule_run` 的 unix 秒 |
| `sandadmin_slo_requests_total` | counter | `class`（`login`/`authz`/`sync`/`ai`）、`result`（`success`/`failure`） | SLO 分类请求计数，错误预算计算依据 |

部署注意：

- `route` 标签用契约路径模板（如 `/api/admin/v1/tasks/{task_id}`），
  禁止把 path 参数原值打进标签，控制基数。
- `sandadmin_db_*` 只统计主库连接；`node_cpu_seconds_total`（主库 CPU
  告警用）来自 node exporter，采集侧须把主库节点的 instance 筛出。
- SLO `class` 归类由 /metrics 实现按路由前缀映射：登录/鉴权恢复走
  `login`/`authz`，数据同步类走 `sync`，AI 调用走 `ai`；映射表随
  队列 2-6 实现定稿并回写本表。

## 四、告警规则清单（O4，规则文本已交，通道未接）

文件：[alerts.yml](alerts.yml)，`promtool check rules` 通过。

| 告警 | 含义 | 阈值依据 |
| --- | --- | --- |
| `SandadminHigh5xxRate` | 5 分钟 5xx 占比 >1% 持续 10 分钟 | v4 I-4 错误率预算，1% 已属事故级 |
| `SandadminHighP99Latency` | 全路由 P99 >2s 持续 10 分钟 | v4 I-4 常规接口 P99 ≤2s（压测复核） |
| `SandadminDbConnectionsHigh` | 主库 active 连接 >max_connections 70% | O4 升档线 |
| `SandadminDbCpuHigh` | 主库节点 CPU 非 idle >60% | O4 升档线；依赖 node exporter |
| `SandadminTaskQueueBacklog` | queued 任务 >100 持续 15 分钟 | 首期低频任务的起步哨兵线，压测后校准 |
| `SandadminProcessRestarted` | 进程启动 <5 分钟（worker_id=hostname-pid） | 重启侦测；发版窗口可临时静默 |
| `SandadminScheduleMissedRun` | 距上次成功 schedule_run >2 个周期 | M-15 漏跑口径 |
| `SandadminErrorBudgetFreeze` | 任一 SLO class 预算用完（≤0） | O4 冻结发版规则 |

验收口径（O4 行）：规则有清单可审（本文件 + alerts.yml）；演练触发
「即使先落日志」——本批不接 Alertmanager，触发证据 = Prometheus 评估
`ALERTS` 指标或告警历史，通道接入后补演练记录。

## 五、错误预算算法（O4）

- 滚动窗口：30 天（`[30d]` range）。
- SLO 目标（3i H8）：`login`/`authz`/`sync` = 99.9%，`ai` = 99.5%。
- 预算总量 = (1 − SLO) × 窗口分钟数：99.9% 档 = 0.001 × 43200 ≈
  **43 分钟**；99.5% 档 ≈ 216 分钟。
- 剩余预算（分钟）= (实际可用性 − SLO) × 43200，由
  `sandadmin:error_budget_remaining_minutes:30d` 记录；
  `SandadminErrorBudgetFreeze` 在 ≤0 持续 15 分钟时触发 → **冻结发版**。
  可用性回升（好请求占比恢复）后剩余预算回正，告警自动解除。
- 注意：窗口内请求量为 0 时可用性为 NaN，预算规则不触发——
  低流量环境以 `sandadmin_slo_requests_total` 增量监控补位。

## 六、design-standard 逐项回答（O1–O4）

按 `.agents/skills/sandadmin1x-design-standard/SKILL.md` 要求，
本任务涉及的 O 组条目逐项回答（其余编号不在本任务范围）：

- **O1 日志（1.0 做什么列）**：结构化日志 + request_id 贯穿请求与任务——
  已实现：JSON 行格式（JsonLogFormatter）、RequestContextStore::ensure
  统一入口、RequestScope 作用域注入、TaskDispatcher `__context`
  保留键把 request_id 带进任务执行上下文。留存分级沿用既有
  LogRetentionPolicy（security ≥183 天、general ≥30 天）。验收：单请求
  全部日志行共享同一 request_id（实测见 PR 描述）。
- **O2 指标**：1.0 做「基础计数 + metrics 端点」——本任务只定
  **指标名契约**（上表）与告警规则引用，不实现 /metrics 端点与采集；
  端点属队列 2-6。以后补的代价：契约已成文，端点实现照抄即可，
  无返工；若先写代码后定名会返工告警与文档。
- **O3 链路追踪**：1.0 不接 OTel SDK——本任务只交传播规则文本
  （trace-context.md）：request_id/traceparent 的传递约定与头名表，
  HTTP 中间件、DBAL、外呼的注入点 1.1 接 SDK 时直接复用。
  不涉及实现；以后补的代价 = 接 SDK + 配置（注入点已留）。
- **O4 告警**：阈值告警规则 + 错误预算冻结发版——规则文本已交
  （alerts.yml + 本文档清单），通道接入（Alertmanager/L-03）属 1.1。
  不涉及通知实现；以后补的代价 = 实现通知接口，小改。

## 七、遗留项

- `/metrics` 端点与采集实现：队列 2-6。
- 告警通道（Alertmanager/邮件/webhook）：1.1 L-03。
- OTel SDK 接入：1.1 L-02；`traceparent` 出网透传的接口约定见
  trace-context.md（M-10 落地时按此实现）。
- `sand_system_task_run` 补 `request_id` 列：后续工单（本批走
  payload `__context` 保留键，不改表结构）。
- SLO class 路由映射表、各阈值的压测校准：随 /metrics 实现一并定稿。
