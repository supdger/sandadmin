# 12g：M-18a 可观测基础（队列 1-15，SWE-2）

日期 2026-11-20。交付物：**未合并 PR #118**（supdger/sandadmin），分支 `feat/observability-base`。

- 基线 SHA（origin/main）：`5360129459241f4e0531fe3483f7c1f19119f7cd`（开工时 fetch 确认未变）
- 提交：`7369bd4`（主体，25 文件）+ `022ea09`（trace-context 文档补充）+ `3fbee6f`（验收返工：request_id 全站收口）
- PR：https://github.com/supdger/sandadmin/pull/118 （state=OPEN，不合并；合并前置：#81 仍 OPEN，只卡合并不卡开工）
- #109 清单 skill 已合并进 main（e325e0e），清单编号按 main 上版本回答。

## 需求逐项回答（#109 清单编号）

| 编号 | 条目 | 本 PR 做了什么 | 剩什么（后续队列行） |
|---|---|---|---|
| O1 | 日志 | 两条日志通道（default/security）改 JSON 行格式（`JsonLogFormatter`+`RequestScope` processor）；`request_id`/`task_id`/`attempt` 自动注入；request_id 唯一入口收口：全局 `RequestContextMiddleware`→`RequestContextStore::ensure`→`RequestIds`，10 个自建编号的控制器、BizToolRunner、TaskController/McpController 分叉实现全部清除（grep 证明 `REQUEST_ID_PATTERN`/`req_`+bin2hex 第二套逻辑零残留）；任务侧经 payload 保留键 `__context.request_id` 贯穿（不改表结构），TaskExecutor 执行期绑定并 finally 清理，TaskCli 同通道 | 日志留存分级依赖既有 LogRetention*（X5 口径未变）；OTel 日志关联属 1.1 |
| O2 | 指标 | **只交契约文本**：`server/docs/observability/README.md` 指标名契约表（sandadmin_http_requests_total、_duration_seconds、db_connections/limit、task_queue_depth、process_start_time_seconds、schedule_*、slo_requests_total 等），供队列 2-6 的 `/metrics` 实现照抄 | `/metrics` 端点、进程汇总、node exporter 依赖（DB CPU 告警）属队列 2-6 |
| O3 | 链路追踪 | `server/docs/observability/trace-context.md` 成文：request_id/traceparent（W3C）在 HTTP 入站、内部调用、→任务、任务→派生任务、出网、跨进程的传播规则表；内部跳间 `x-md-global-*`（跨跳）/`x-md-local-*`（单跳）前缀语义（参照 Kratos metadata）；声明 1.0 只透传不接 OTel SDK；写明 M-10 出网透传 traceparent 的接口约定；补充 MCP Fiber 边界（SDK 自建 Fiber 内 Webman\Context 不可见，由 LogAuditSink 显式 context 携带） | OTel SDK + exporter 属 L-02（1.1）；出网实际透传实现属 M-10 |
| O4 | 告警 | `server/docs/observability/alerts.yml`：Prometheus 规则格式 10 条——SandadminHigh5xxRate（>1%/10m/critical）、P99（>2s/10m/warning）、DB 连接（>70% max_connections/10m）、任务堆积（queued>100/15m）、进程重启（start_time<300s/info）、DB CPU（依赖 node exporter）、SandadminScheduleMissedRun（漏跑 >2 周期/5m/critical）、错误预算冻结发版 SandadminErrorBudgetFreeze（≤0/15m/critical）+ 2 条 recording rule；README 写清每条阈值依据、43 分钟错误预算算法（99.9%/99.5% SLO）、与 1.1 告警通道 L-03 的关系（本任务只交规则文本不接 Alertmanager） | 告警通道/值班属 L-03（1.1，运维暂缓）；规则触发演练属后续 |

对应计划行：队列 1-15 = M-18a（M-18 行 O1/O2/O4 的 1.0 基础部分）。`/metrics` 端点与任务 retry/cancel/dead-letter API 属 M-18 其余部分 / 队列 2-6，不在本任务。

## 验收硬指标复核（独立验收代理 subagent_general 新上下文，初判不通过→返工→复验通过）

| 指标 | 结果 | 证据 |
|---|---|---|
| 一条请求的日志能凭 request_id 串起来 | ✅（返工后） | 初验发现 B1：10 个控制器自建 `req_<16hex>` 与 scope 的 32-hex 分叉。返工 3fbee6f 全站收口；复验 grep 证明第二套逻辑零残留，`ControllerRequestIdTest` 断言信封==X-Request-Id 头==RequestScope 日志字段；实测 `POST /api/admin/mcp` 带 `X-Request-Id: req-mcplivecheck8`，`security-2026-11-20.log` 单行 JSON 顶层 `request_id` 同值；无头请求生成 32-hex。任务侧由 TaskDispatcher/TaskExecutor 单测覆盖（无 DB 不做真实执行） |
| 告警规则通过 promtool 语法检查 | ✅ | `/tmp/prometheus-2.53.4.darwin-arm64/promtool check rules server/docs/observability/alerts.yml` → `SUCCESS: 10 rules found`，exit 0（本机无 promtool，下载官方 release 二进制；Docker 不可用故未走容器） |

## 门禁命令与结果（server/ 下，除注明外）

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `composer install` | 0 | vendor 齐全；sand:publish 副作用已还原未入库 |
| `vendor/bin/phpunit --testsuite unit` | 0 | OK（765 tests, 6949 assertions） |
| `vendor/bin/phpstan analyse` | 0 | No errors（569 files） |
| `tools/pre-merge.sh --fast`（仓根） | 0 | 全部通过（契约一致性、危险函数、dev-only、TypePHP diff、PHPUnit Unit、PHPStan、命名自查） |
| `python3 tools/naming_check.py code/notes --diff` | 0 | exit 0；notes 仅 INFO 注释占比提示（非阻断） |
| `scripts/security/run-security-baseline.sh` | — | 本仓无此脚本（该脚本属 business-server 仓），跳过 |
| Integration/Contract 套件 | — | 需 `SAND_TEST_DB_URL` 连库，按约束未跑（pre-merge --fast 不含） |

## 设计要点与边界

- **request_id 作用域**：`Webman\Context`（fiber 隔离；无协程时退化进程内单槽，webman 每请求 reset/destroy）——无可变 static/`$GLOBALS`，与在途 feat/runtime-context 的 ResidentStateRule 兼容。
- **任务串联**：`TaskDispatcher::dispatch` 写 payload 保留键 `__context.request_id`（先 `unset` 调用方伪造值再校验 PATTERN 写入，防伪造落库）；TaskExecutor 执行期 `RequestScope::bind(request_id, task_id, attempt)`，finally 清理；任务内再派生沿用同一编号。**不改表结构**（sand_system_task_run 补字段属后续编号表审批工单）。
- **格式变化（预期）**：无 `X-Request-Id` 头时编号从旧控制器 `req_<16hex>` 统一为 RequestIds 的 32-hex；合法头仍透传。审计/日志/信封对账口径已统一。
- **MCP 边界**：MCP SDK 在自建 Fiber 内执行，`Webman\Context` 不可见；`LogAuditSink` 显式携带 context（已写入 trace-context.md）。
- TypePHP 扫描 WARN×2：`->bind(` 方法名触碰「闭包重绑定」规则误报（RequestScope::bind 为作用域绑定语义），pre-merge 中属提示级已说明。

## 偏差与遗留

- 本机无 DB：任务侧真实库执行未验，靠单测（TaskDispatcher/TaskExecutor 新增用例）覆盖；业务接口实测均 500 属既有 DB 未配置降级。
- task_worker 无 DB 时每秒刷 `error_log` 为既有行为，非本次引入。
- `TaskController::requestId()` 保留为 `ensure` 薄封装（命名同旧模式，后续可内联改名）。
- 合并前置：#81（merge-gates）仍 OPEN；合并时需先确认 #81 已合并、merge main、CI phpunit/phpstan 绿、过 Fusion 审计——本任务不合并。

## 模型记录
- 主 Agent：SWE-2（本会话模型）
- 子 Agent：dv-scout = 请求 → model unavailable（not_found）；subagent_general（调研，agent d9c394c7）= 跟随主模型 SWE-2 → 完成；subagent_general（实现，agent 63a0e195）= 跟随主模型 SWE-2 → 完成；subagent_general（返工，agent f1a82af8）= 跟随主模型 SWE-2 → 完成；subagent_general（独立验收，agent bc156633 / 复验 98f0e310）= 跟随主模型 SWE-2 → 完成。dv-* 档案报 model unavailable（与 12a 相同），按「不用 Claude」约束改用跟随主模型的内置档案，未使用任何 Claude 模型。

## 任务记录
- 已修改：supdger/sandadmin 分支 feat/observability-base（3 commits，38 files，全部在 server/）；本目录新增 RESULT.md
- 已验证：phpunit unit 765 tests OK、phpstan 0 errors、pre-merge --fast 全过、naming exit 0、promtool check rules SUCCESS 10 rules、request_id 串联实测与单测（独立验收两轮确认）
- 已提交/已部署：已提交并 push 分支、已开 PR #118（未合并、未部署）
- 未完成：合并需等 #81 合并后 merge main + CI phpunit/phpstan 绿 + Fusion 审计（本任务不合并）；/metrics 端点、告警通道、OTel SDK 属后续队列
