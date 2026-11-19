# 1.x 框架「异步任务与并发能力」补充设计（技术总监，2026-11-19）

> 范围：只写方案，不改仓库、不建库、不建表。所有表结构都**待编号表审批，未建表**。
> 写法：先按成熟框架的高标准把能力列全（§2），再逐项标「1.0 做 / 1.1 做 / 不做」并写理由；砍掉的项在文末 §12 单独列表。
> 引用写法：`v4:<行号>` = `review-1117-r3/顶层架构设计-v4.md`；`AUTH:<行号>` = `鉴权P0-v4.md`；`BOSS:<行号>` = `老板已定事项总表.md`；`1b:` = `1b-core-breakdown-1119/RESULT.md`；`main:` = supdger/sandadmin `origin/main`（977427a，本地克隆 `~/agent-work/assistant/q`，未 fetch）；`skills:` = `origin/docs/1x-skills`（b7bc4c5）。

---

## 0. 结论先行

1. **1.x 的运行模型不变**：webman 常驻多进程、每进程一个事件循环、业务代码不开协程（skills:`sandadmin1x-backend/references/backend-structure.md:141`，v4:1830 ADR）。异步全部靠「**进程组隔离 + 数据库任务队列 + outbox**」实现；单个请求内的并发 I/O 只开放给内核自己的流式层（SSE），不开放给业务。
2. **队列不另起炉灶**：main 上已有可用骨架——`sand_system_task_run` + `FOR UPDATE SKIP LOCKED` 认领 + 租约 + 幂等键 + 按类型并发上限（main:`server/src/Task/PdoTaskStore.php:104-109`、`TaskDispatcher.php:12-14`、`config/task.php:11-24`）。1.0 只补齐：退避抖动、死信、延迟/优先级、续租、进度与取消、事务内投递、可观测、优雅停止。**不引入 webman/redis-queue**（无 ack，进程崩溃丢消息，见 §2.1）。
3. **现有代码有 5 个缺口，必须在 1.0 内补上**（只读核实）：
   - 定时调度没接上：`ScheduleClaim::dueCodes` 没有任何调用方（main:`server/src/Infra/Plugin/ScheduleClaim.php:14-42`）。
   - outbox 表有了，但没有 relay 消费进程。
   - 限流只在进程内做，而且每个请求都 `new` 一次，等于没有限流（main:`server/src/Http/McpController.php:71`、`Infra/Mcp/WindowRateLimiter.php:13-20`），违反「限流计数不得用进程内近似」（v4:1805）。
   - 数据库连接每次调用都新建，没有每进程复用（main:`Infra/Db/DbalConnection.php:14-45`）。这是高性能目标里最大的单点损耗。
   - 没有 `/healthz` 和指标端点。
4. **PHP 8.6（2026-11-19 GA）核心只交付了 `Io\Poll` 轮询原语**。协程、调度器、非阻塞 PDO 都不在 8.6 里。True Async RFC 已 Canceled，继任的引擎级调度 RFC 还在 Under Discussion，目标是 8.7（§8）。所以「原生异步」走的是 2.x 路线。1.0 能做的是：**守住代码规矩**，把**接口做成以后能换实现**，再加上 **CI 兼容矩阵 + 试验分支**。
5. **工作量**：1.0 新增 7 个 PR，约 **19–31 人天**。其中约 6–9 人天和 v4/1b 里已有的行重叠，按「合并」计，不重复算，扣除后净新增约 **13–22 人天**（§10）。
6. **高性能目标**（数字有出处，§9）分两层：
   - 业务门禁沿用 v4 P-4：750 req/s + 300 路 AI，P95/P99 分档达标，平滑重载零失败。
   - 框架能力门禁按 TechEmpower R23 公开基准定**相对目标**：在同一台机器上，整套框架（含鉴权中间件）的 JSON 吞吐 ≥ 裸 webman 的 15%，单查询 ≥ 25%。按 R23 的数字折算，大约相当于 Spring MVC / Gin 的水平。
   - 能不能达到，要等基线压测 PR 实测确认。

---

## 1. 输入与现状核对

| 项 | 现状（证据） | 对本方案的影响 |
|---|---|---|
| 运行模型 | 常驻多进程、不开协程（skills backend-structure.md:141；v4:1830） | 1.x 不变 |
| 进程组 | v4:1316 定四类：Web / AI 流式（`ai_stream_workers=8`）/ 任务 / outbox relay；main `config/process.php:25-80` 实际有 webman(`cpu*4`)、monitor、`task_worker`(默认 1)、`log_retention`、channel(2206) | 补齐 relay、schedule 进程；task_worker 生产默认值要改成 ≥2（v4:1373） |
| 队列 | main 已有库表队列骨架（见 §0.2）；v4:1372/1426 定「首期库表，留驱动接口」 | 扩展，不重写 |
| 定时 | 插件清单声明 + 库里只存编号（B-13，v4:59；skills plugin contract.md:53）；调度进程未接线 | 1.0 接线 |
| 事件 | webman/event 已装，配置为空（main `config/event.php:5`）；v4:1378 定「EventBus 只发同步本地事件，可靠外发走 outbox」 | 1.0 做清单注册，不做运行期扫描（TypePHP M-12） |
| 锁/限流/闸/熔断 | v4:1380 `LockInterface` 三驱动；v4:1520 全局限流阈值；v4:610/1326 本机并发闸（AC-7）；v4:1381 熔断统一入口 | 1.0 收拢成一个 PR，和 AC-7 合并 |
| 审计异步写 | v4:1379 `AuditSinkInterface` 库表 + 异步批量写；1b 任务 2-H2（进程内缓冲 + 定时冲刷） | 不并入本方案，保留 2-H2；本方案只规定它和任务队列的边界 |
| 连接 | 每进程预算 ≤4 条（v4:1290-1292）；main 每次新建连接 | 1.0 做每进程连接工厂（断线重连、请求结束断言无残留事务） |
| 流式 | J-5 基础层已有（`SseStreamWriter` 非阻塞 + onBufferFull 背压；`config/stream.php` web 80 路 / ai_stream 100 路） | 不重做，只纳入容量门禁 |
| PHP 版本 | v4:1637 SCM-10 定 PHP ≥8.4；但 main `server/composer.json:37` 写的是 `>=8.2`，`config.platform.php` 是 8.2.0；CI 只跑 8.2 单版本（`.github/workflows/dependency-audit.yml`） | **不一致**，§8.5 给出处理建议 |
| CI | 只有依赖审计 / 前端审计 / 发版自检，没有 phpunit / phpstan / 双库矩阵 job | 本方案的 CI 项依赖 1b 的 CI 门禁行，不重复计 |

---

## 2. 高标准能力清单与 1.0 / 1.1 / 不做

对照对象：Laravel Queue + Horizon、Symfony Messenger、webman/redis-queue + workerman 进程模型、Hyperf（async-queue / AMQP / crontab / 协程），并参考 Go（Gin、go-zero、Kratos）和 Java（Spring Boot / Cloud、Vert.x、Quarkus）。资料出处见 §11。

### 2.1 后台任务队列

| # | 能力 | 成熟实现怎么做 | 判定 | 理由 / 我们的做法 |
|---|---|---|---|---|
| Q1 | 投递 | Laravel `dispatch`；Messenger `bus->dispatch` | **1.0** | 已有 `TaskDispatcher`；收敛成 `TaskQueueInterface::dispatch(TaskSpec)` |
| Q2 | 事务内投递 / 提交后投递 | Laravel `after_commit` / `afterCommit()` | **1.0** | 库表队列的天然优势：同一连接时，任务行跟业务写在**同一事务**里（等同 outbox 原子性）；跨命名连接时走 `dispatchAfterCommit` 挂钩，回滚即丢 |
| Q3 | 延迟 | Laravel `delay`；Messenger `DelayStamp`；redis-queue zset | **1.0** | `available_time` 列；认领条件 `available_time <= 库服务器 now()` |
| Q4 | 重试次数 + 时间截断 | `tries` / `retryUntil`；Messenger `max_retries` | **1.0** | 已有 `max_attempts=3`；补 `deadline_time`（超过即进死信，不再重试） |
| Q5 | 退避 | Laravel `backoff` 数组；Messenger `multiplier` + `max_delay` | **1.0** | 指数退避 + 全抖动：`delay = rand(0, min(max_delay, base·2^n))`，默认 base 5s、max 600s；和 outbox 同一算法（v4:1378） |
| Q6 | 死信 / 失败任务 | Laravel `failed_jobs` + `queue:retry`；Messenger `failure_transport` | **1.0** | 不另建表，`task_run.state='dead'`，查询走部分索引；重放要权限码 + sudo + 审计 + 限速（与 v4:1378 outbox 死信同口径） |
| Q7 | 优先级 | Laravel 多队列按顺序消费；Messenger `PriorityStamp` | **1.0（简化）** | `priority smallint` 三档（0 高 / 5 普通 / 9 低），认领时 `ORDER BY priority, available_time`。代价一个索引列，用来防止大导出饿死通知类任务 |
| Q8 | 多队列 / 进程组隔离 | Horizon supervisor 分组；redis-queue fast/slow 目录 | **1.0** | `queue` 列 + 进程配置 `queues=[...]`；默认 `default`、`bulk`（导入导出）、`ai` 三组 |
| Q9 | 唯一 / 幂等 | `ShouldBeUnique`（缓存锁） | **1.0** | 已有 `(type, idempotency_key)` 去重；规定处理器**必须幂等**（至少一次语义），契约测试强制「同一任务跑两次结果相同」 |
| Q10 | 超时 | `--timeout`、`$timeout`、pcntl 信号 | **1.0** | 已有 `timeout 300s`。不开协程，没法中断正在执行的 PHP，所以用「租约到期即视为失败 + 处理器在检查点自查超时」。真正卡死的由监控进程按「单进程运行超时 × 1.5」杀进程，master 重拉 |
| Q11 | 租约 / 续租 | Laravel `retry_after`；SQS visibility timeout | **1.0** | 已有 60s 租约；补心跳续租 `TaskContext::heartbeat()`（v4:1312：AI 任务不续租会被重复扣费）；**租约时间一律用库服务器时钟** |
| Q12 | 按类型并发上限 / 任务限速 | Laravel `RateLimited`、`WithoutOverlapping` | **1.0** | 已有按类型 `concurrency`；补「每用户 / 每租户同类在途 ≤N」（导出防刷） |
| Q13 | 背压（队列满拒收） | Messenger 无原生；Horizon 靠告警 | **1.0** | 每队列 `max_queued`，超过时用户触发的投递返回 429 + `Retry-After`，系统内部投递只告警不拒（防止丢补偿任务） |
| Q14 | 长任务进度 | Laravel 只有 batch 级 progress | **1.0** | `progress_percent` + `progress_note`，处理器按检查点写，同时节流（≥2s 才写一次） |
| Q15 | 取消 | Laravel batch `cancel()` | **1.0（协作式）** | `cancel_request_time` 置位，处理器在检查点调 `TaskContext::throwIfCancelled()`；排队中的直接置 `canceled` |
| Q16 | 链式 / 批量（chain / batch） | `Bus::chain`、`Bus::batch` | **1.1** | 1.0 的业务（导入导出、AI 后台、文件清理、Webhook）都是单任务；需要时用「处理器结束时投递下一个」手写链 |
| Q17 | 结果与产物 | Horizon 存结果；导出要下载链接 | **1.0** | 已有 `/tasks/{id}/download`；产物走文件存储 + nanoid 链接 + 过期（v4:2135） |
| Q18 | 可观测 | Horizon 吞吐 / 运行时长 / 失败面板 | **1.0 指标；1.1 管理页** | 1.0 出指标：队列深度、等待时长 p50/p95、运行时长、失败率、死信数、续租失败数，并接告警「任务堆积」（v4:1038）。完整管理页（重试 / 取消按钮）放 1.1，1.0 先给 API + CLI |
| Q19 | 驱动可换 | Laravel / Messenger 多 transport | **1.0 接口；1.1 Redis Streams 驱动；1.x 不做 MQ** | 见 §12 |
| Q20 | 优雅停止 | `queue:restart`；Messenger SIGTERM 完成后退出 | **1.0** | 收到停止 / 重载：停止认领 → 当前任务在 `task.drain_seconds`（默认 30）内跑完 → 超时就靠租约接管。注意 main `server.php` 的 `stop_timeout=2` 对任务进程太短，任务进程要单独配置 |
| Q21 | 多实例安全 | Laravel / Horizon 依赖 Redis 原子操作 | **1.0** | SKIP LOCKED 认领已经天然多实例安全；门禁「≥2 台任务机、拔一台不丢任务不重跑」（v4:1328/1373） |
| Q22 | 载荷安全 | Laravel 可加密载荷 | **1.0（禁存凭据）** | 已有 FORBIDDEN_KEYS；载荷只存 id 和参数、不存密钥，所以不加密 |

**为什么不用 webman/redis-queue**：官方文档写明「可以看作自动 ack」，异步投递经本地内存再同步到 Redis，「重启可能丢未同步消息」；没有优先级、唯一、超时、进度。它的投递也不能和业务写进同一事务。所以 1.0 主队列不用它，Redis 驱动留到 1.1，用 Redis Streams + 消费组（有 ack 和 pending 列表）实现。

### 2.2 事件与订阅

| # | 能力 | 成熟实现 | 判定 | 理由 |
|---|---|---|---|---|
| E1 | 同步本地事件 | Symfony EventDispatcher（优先级、stopPropagation）；webman/event（不是异步） | **1.0** | `EventBusInterface::publish(DomainEvent)`；监听器在插件清单 / 内核注册表的**构建期生成**，不做运行期反射扫描（skills typephp.md:32 M-12） |
| E2 | 监听器优先级与隔离 | Symfony priority；webman `emit` 吞异常 | **1.0** | 优先级 int；同步监听器抛异常 = 本事务回滚（同事务可见），不吞异常 |
| E3 | 异步 / 可靠订阅 | Laravel `ShouldQueue` 监听器 | **1.0** | 监听器声明 `async: true` 时，事件写进 outbox（同事务）→ relay → 投递成任务（`queue='event'`），幂等键 = 事件 id + 监听器编号 |
| E4 | 外发（Webhook / 跨系统） | outbox 模式 | **1.0（已在 v4）** | v4:1378 / 1677，本方案只出通用 relay，Webhook 工作池仍归 v4:1677 那一行 |
| E5 | 插件订阅内核事件 | — | **1.0** | v4:1600；插件只能订阅清单里声明过的事件名，审计落内核账本（v4:500） |
| E6 | 事件溯源 / 事件存储重放 | Axon、EventStoreDB | **不做** | 后台管理框架不需要 |

### 2.3 定时任务与队列的关系

| # | 能力 | 成熟实现 | 判定 | 理由 |
|---|---|---|---|---|
| S1 | 秒 / 分级 cron | workerman/crontab 6 段秒级；Laravel 子分钟 | **1.0** | 分钟级就够，秒级不开放（后台业务不需要，还容易压库） |
| S2 | 调度和执行分离 | Laravel `runInBackground`；Hyperf 分发到 worker | **1.0** | **调度进程只负责到点投递任务**（`schedule → task_run`），自己不执行业务；避免 webman crontab「同进程内一个任务阻塞拖累其他任务」的问题 |
| S3 | 多实例只跑一次 | Laravel `onOneServer`；Hyperf `mutexPool` | **1.0** | 每台机器都起调度进程；用 `schedule_run (schedule_code, period_key)` 唯一键防重跑（v4:1374），不依赖 Redis |
| S4 | 防重叠 | `withoutOverlapping` | **1.0** | 投递时带幂等键 `schedule:<code>:<period>`；上一次还在跑时按策略 `skip` / `queue` |
| S5 | 错过补跑 | — | **1.0** | 策略 `none` / `last_once`（默认只补最近一次）/ `all`（上限 N）；补跑在 `schedule_run` 里记一行 |
| S6 | 漏跑告警 | Laravel 无原生 | **1.0** | 到点后 2 个周期还没有 run 记录就告警（v4:1038「定时任务漏跑」） |
| S7 | 插件定时安全 | — | **1.0（已定）** | B-13：库里不存可执行目标，只存编号（v4:59） |

### 2.4 并发控制

| # | 能力 | 成熟实现 | 判定 | 理由 |
|---|---|---|---|---|
| C1 | 分布式锁 | Laravel `Cache::lock`；Symfony Lock（Pdo / PostgreSql / Redis） | **1.0（已在 v4:1380）** | PG 咨询锁 / MySQL `GET_LOCK` / Redis `SET NX PX` 三驱动，同一套契约测试；安全临界区 fail-closed；锁只做短临界区，**锁内禁止出网** |
| C2 | 乐观锁 | version 列 | **1.0（已在 v4:1158）** | 不重复计 |
| C3 | 全局限流 | Symfony RateLimiter（固定 / 令牌桶 / 滑窗）；Spring Gateway Redis 令牌桶；go-zero TokenLimiter | **1.0** | `RateLimiterInterface`：Redis 驱动（Lua 原子）+ DB 驱动（单条原子 UPDATE，AUTH:248）；**替换掉** main 的进程内 `WindowRateLimiter`；阈值用 v4:1520 |
| C4 | 本机并发闸 | Resilience4j Bulkhead；Quarkus `@Bulkhead` | **1.0（合并 v4:610 AC-7）** | 进程内信号量（没有协程时，一个进程同一时刻只处理一个请求，所以「每进程 N」实际上等于「本机回主库的在途数 ≤ 进程数」）。机器总上限用 webman/channel 或 APCu 计数，超限返回 503 |
| C5 | 熔断 | Resilience4j；go-zero Google SRE 自适应（K=1.5）；Kratos 客户端熔断 | **1.0（AI 服务商 + Webhook 端点）；1.1 全出站** | 用 Google SRE 公式（不用手调阈值）；状态按进程存，指标里上报各进程状态（v4:1381）。按进程存的代价是每个进程各自试探一次，可以接受 |
| C6 | 降级 / Fallback | Quarkus `@Fallback`；Sentinel | **1.0（只定接口和 P-6 表）** | 降级规则就是 v4:1957 的 P-6 Redis 故障表；通用的 `@Fallback` 注解式降级不做（TypePHP 禁运行期注解） |
| C7 | 自适应过载保护（load shedding） | go-zero CPU 90% + 通过率双门、返回 503；Kratos BBR | **1.1** | 多进程模型下，请求排队发生在内核 accept 队列，进程看不到。1.0 先靠「全局限流 + 并发闸 + 过载段压测 900 req/s 不雪崩」兜底；1.1 加事件循环延迟 + 在途数的简化 BBR |
| C8 | 出站超时 | 各框架均有 | **1.0（已在 v4:1303/1953）** | 不重复计 |
| C9 | 背压 | workerman `onBufferFull` / `pauseRecv`；Vert.x `writeQueueFull` | **1.0（流式已有）+ 队列 Q13** | — |

### 2.5 容量目标与压测验收 → 见 §9（单独成节）

### 2.6 进程与协程模型及升级路线

| # | 能力 | 判定 | 理由 |
|---|---|---|---|
| P1 | 进程组划分 + 每组独立配额 | **1.0** | Web / ai_stream / task(default、bulk、ai) / schedule / outbox_relay / log_retention / channel；用 `ProcessProfileInterface` 统一声明（v4:1317） |
| P2 | 常驻卫生（请求结束断言无残留、`max_requests`、内存水位） | **1.0** | v4:1318；main 目前没配 `max_request`，web 组建议 10000（workerman 官方示例值），流式组设 0 并靠内存水位回收 |
| P3 | 请求内并发 I/O（业务可用的 Parallel / Promise） | **不做（1.x）** | 业务开 Promise 会让函数分成同步和异步两类，以后换运行时就得改业务代码，违反 §8.2 的「可替换」目标。需要并发的出站请求由内核按场景提供（AI 流式已有） |
| P4 | Workerman 5 协程驱动（Swoole / Swow / Fiber） | **不做（1.x）；2.x 候选** | 1.x 写死不开协程；官方压测说开协程后「极限性能比阻塞式 IO 下降约 10%-20%」；Fiber 驱动遇到阻塞函数会卡住整个进程（PDO 就是阻塞的）。收益只在 I/O 密集的长等待场景，我们的长等待（AI 流式）已经用非阻塞 socket 解决了 |
| P5 | PHP 原生异步 | **不做（1.x）；试验分支评估** | §8 |

### 2.7 与主从读写分离和事务的配合

| # | 规则 | 判定 |
|---|---|---|
| T1 | 任务认领 / 续租 / 状态变更**一律主库**，不走读写分离路由 | 1.0 |
| T2 | 处理器默认读主库（任务往往紧跟在一次写之后，从库可能还没同步）；批量导出可声明 `read: replica`，并把「数据截至时间」写进产物 | 1.0 |
| T3 | 同连接时投递和业务写同事务；跨连接用 `afterCommit` | 1.0 |
| T4 | 进度 / 心跳用独立短事务，**禁止**写在业务长事务里（否则长事务里的心跳要到提交时才可见，租约照样会过期） | 1.0 |
| T5 | 处理器内的大批量处理分批提交（每批 ≤1000 行或 ≤2s），配合 `statement_timeout 5s`（v4:1953）；批量任务的连接可单独放宽到 60s | 1.0 |
| T6 | 分布式事务 / Saga 编排器 | 不做（v4:456 首期不做分布式事务）；用 outbox + 幂等 + 死信人工重放代替 |

### 2.8 故障与补偿

| 故障 | 1.0 的处理 |
|---|---|
| 任务进程崩溃 / 机器宕机 | master 重拉进程；租约到期后任务被其他进程接走，attempts+1；处理器幂等 |
| 毒消息（每次都失败） | 达到 max_attempts / deadline 进入 dead，告警；人工修复后重放 |
| 数据库不可用 | 任务进程退避重连（main `TaskWorker.php:46-50` 已有）；Web 请求按 fail-closed 返回 503 |
| Redis 不可用 | 按 v4 P-6 表（v4:1957-1964）；队列不依赖 Redis，所以不受影响 |
| 时钟漂移 | 租约、available_time、限流窗口都用库服务器时钟；发号器回拨按 v4:1281 |
| 重复执行（租约过期但原进程还活着） | 原进程写结果时带 `lease_token` 条件更新，影响 0 行就放弃提交并记录告警（防止旧进程覆盖新进程的结果） |
| 发版中途 | 平滑重载：Web 进程跑完当前请求再退出；任务进程 drain；SSE 断开后客户端带游标重连补发（v4:1297） |

---

## 3. 1.0 接口与分层

目录遵循 skills backend-structure.md §一（`Domain/` 契约、`Infra/` 实现、`Http/` 控制器，不机械加 Service / Repository）。下面只定签名，不写实现。

```
Domain/Task/
  TaskQueueInterface      dispatch(TaskSpec): TaskId
                          dispatchAfterCommit(TaskSpec): void
                          cancel(TaskId, ActorId): void
                          retryDead(TaskId, ActorId): void        // 需权限码 + sudo + 审计
  TaskSpec                type, payload(array<string,scalar|array>), queue, priority,
                          idempotencyKey?, availableTime?, deadlineTime?, maxAttempts?
  TaskHandlerInterface    handle(TaskContext): TaskResult         // 业务只实现这个
  TaskContext             payload(), attempt(), heartbeat(), reportProgress(int, string),
                          throwIfCancelled(), deadline()
  TaskResult              succeeded(?artifactRef) | failed(retryable: bool, reason)
Domain/Schedule/
  SchedulerInterface      due(DateTimeImmutable): list<ScheduleDue>   // 只算到期，不执行
  SchedulePolicy          catchUp(none|last_once|all), overlap(skip|queue)
Domain/Event/
  EventBusInterface       publish(DomainEvent): void              // 同步 + 按声明转 outbox
  OutboxInterface         append(OutboxMessage): void             // 必须在业务事务内调用
Domain/Concurrency/
  LockInterface           acquire(key, ttlMs, waitMs): ?LockHandle;  release(LockHandle)
  RateLimiterInterface    consume(key, policy): RateDecision      // 带 retryAfterSeconds
  ConcurrencyGateInterface enter(gate): GateTicket|null; leave(GateTicket)
  CircuitBreakerInterface call(name, callable): mixed              // SRE 自适应
Domain/Runtime/
  ContextStoreInterface   get/set 请求或任务上下文（现实现 = Request::$context / TaskContext；以后 = 协程 Context）
  ConnectionFactoryInterface forUnitOfWork(name): Connection      // 禁止业务持有连接
  ClockInterface          now(), sleep(ms)                        // 业务禁止直接调 sleep/usleep
```

进程（Infra/Runtime，自定义进程，`config/process.php` 声明）：

| 进程 | count 默认 | reloadable | 职责 |
|---|---|---|---|
| `task_default` / `task_bulk` / `task_ai` | 2 / 1 / 2（生产，按 CPU 调） | 是（drain） | 认领 → 执行 → 写结果 |
| `schedule` | 1/机 | 是 | 到点算 due → 写 schedule_run（唯一键）→ 投递任务 |
| `outbox_relay` | 1/机 | 是 | 读 outbox → 发布（任务桥 / Webhook 池）→ 标记已发 / 死信 |
| `channel` | 1/机 | 否 | 跨进程广播、机器级并发闸计数 |

分层规则（deptrac 落地）：业务模块只能依赖 `Domain/*` 接口；禁止依赖 `Workerman\*`、`Timer`、`webman\*` 进程原语（v4:1317 插件不直连 workerman 原语）；处理器禁止 new PDO / curl。

---

## 4. 存储（全部待编号表审批，未建表）

只新增 **1 张表**，另对 **1 张已有表**做字段补齐。outbox 和死信表已在 v4:1378 定义，不在本方案重复设计。

### 4.1 `sand_system_task_run`（已有，补字段）——不可预见增长 → 雪花 bigint

main 已使用此表，但 `PdoTaskStore` 用的是 `public_id` uuid（只读核实），这和 B-17「不可预见增长用雪花 bigint、对外链接用 nanoid」不一致，**需要对照编号表一起定**。目标字段（与现有 DDL 的差异由实现 PR 核对后再出迁移）：

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint | 雪花；接口输出字符串 |
| type | varchar(64) | 处理器编号（清单注册，不存类名，B-13） |
| queue | varchar(32) | default / bulk / ai / event |
| priority | smallint | 0 高 / 5 普通 / 9 低 |
| state | varchar(16) | queued / running / succeeded / failed / dead / canceled |
| payload | jsonb / json | 禁凭据键 |
| idempotency_key | varchar(128) | 与 type 组成唯一键 |
| attempt_count / max_attempts | smallint | |
| available_time / deadline_time | datetime(timestamptz) | 用库服务器时钟 |
| lease_token | char(21) | 每次认领新生成，写结果时做条件更新 |
| lease_expire_time | datetime | |
| progress_percent | smallint | 0–100 |
| progress_note | varchar(200) | |
| cancel_request_time | datetime | |
| last_error | varchar(1000) | 已脱敏 |
| owner_user_id / tenant_id | 按主表类型 | 用于每用户 / 每租户在途上限 |
| artifact_ref | varchar(64) | 产物文件编号 |
| create_time / update_time / finish_time | datetime | |

索引：`(queue, state, priority, available_time)` 认领；`(type, idempotency_key)` 唯一；`(state) WHERE state='dead'` 部分索引（MySQL 用普通索引）；按月分区（v4:1357，约 30 万行/月）。

### 4.2 `sand_system_schedule_run`（新增）——不可预见增长 → 雪花 bigint

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint | 雪花 |
| schedule_code | varchar(64) | 对应 `sand_system_schedule` 的编号 |
| period_key | varchar(32) | 例：`202611191030`；与 schedule_code 组成**唯一键**，保证多实例只跑一次 |
| task_run_id | bigint | 投递出去的任务 |
| trigger_kind | varchar(16) | normal / catch_up / manual |
| create_time | datetime | |

按月分区，默认保留 183 天（沿用普通日志口径 `log.retention.normal_days`）。词表依据：命名规范 NAME:127-128（`schedule` / `schedule_run`）。

### 4.3 不建表的项

- 锁：用咨询锁 / GET_LOCK / Redis。
- 限流计数：Redis；DB 驱动复用已有的 `sand_system_cache`。
- 指标：只在内存里，通过 channel 汇总后由 `/metrics` 输出。

---

## 5. 配置（`config/task.php`、`config/schedule.php`、`config/concurrency.php`；键名草案）

| 键 | 默认 | 说明 |
|---|---|---|
| `task.poll_interval_ms` | 1000 | 已有；空闲时退避到 5000 |
| `task.lease_seconds` | 60 | 已有 |
| `task.backoff.base_seconds` / `max_seconds` | 5 / 600 | 全抖动 |
| `task.max_attempts` | 3 | 已有 |
| `task.drain_seconds` | 30 | 停止 / 重载时等当前任务跑完的时间 |
| `task.queues.<q>.workers` / `max_queued` | 见 §3 / 10000 | 背压 |
| `task.types.<type>.concurrency` / `per_user_inflight` | 已有 4 / 2 | |
| `schedule.catch_up` / `overlap` | last_once / skip | |
| `concurrency.gate.authz_fallback.per_machine` | = web 进程数 × 0.5 | AC-7 初值，压测时调 |
| `ratelimit.driver` | redis | redis / db |
| `breaker.<name>.k` | 1.5 | Google SRE 推荐值 |
| `server.max_request`（web 组） | 10000 | workerman 官方示例值 |
| `runtime.memory_watermark_bytes` | 进程内存上限的 80% | |

所有配置在启动时校验（不合法就拒绝启动），不支持运行中热改；要动态开关的项走 v4:1385 `FeatureFlagInterface`。

---

## 6. 测试与验收标准（1.0）

**契约测试**（每个驱动都跑同一套，沿用 skills delivery-loop.md:62 的多驱动规则；PG 和 MySQL 双库都要跑）：

1. 投递：同事务回滚后任务不存在；`afterCommit` 回滚后也不投递；同一幂等键只生成一条任务。
2. 认领：开 20 个进程并发认领 1 万个任务，零重复、零遗漏（用 SKIP LOCKED 实测）；优先级生效；延迟任务不提前执行。
3. 重试：失败后按退避写 available_time（允许抖动范围）；到 max_attempts / deadline 进入 dead；dead 重放要有权限码，并写审计。
4. 租约：模拟进程 `kill -9`，租约到期后被接走；旧进程恢复后带旧 `lease_token` 写结果，影响 0 行。
5. 续租：60s 租约跑 180s，心跳续租期间不被接走（对应 v4:1312 的场景）。
6. 取消：排队中直接置 canceled；运行中在下一个检查点退出，状态为 canceled。
7. 调度：2 个实例同时调度同一分钟只产生一条 run 记录；停机 10 分钟后恢复，按 `last_once` 只补一次；漏跑告警会触发。
8. 事件：同步监听器抛异常导致整事务回滚；async 监听器经 outbox 恰好投递一次任务（重复投递按幂等键去重）。
9. 限流 / 锁 / 闸：三个锁驱动契约一致；Redis 断开时安全类限流 fail-closed、普通限流放行并告警（P-6）；并发闸超限返回 503。
10. 静态检查：新增 PHPStan 规则，禁止非白名单类出现可变 `static` 属性，禁止业务层引用 `Workerman\` 命名空间（§8.3）。

**集成 / 门禁**（并入 v4 P-4，不新开压测轮次）：

- 达标段内导出任务混跑：每分钟 30 个导出、每个 10 万行，Web 端 P95 不变差（相对不跑导出时 ≤ +10%）。
- 队列吞吐：空处理器认领 + 完成 ≥ 200 任务/秒/库（参考依据：任务表月量约 30 万行（v4:1357），日均约 0.1 任务/秒，峰值按 100 倍也只要 10/s，200/s 留 20 倍余量）。投递到开始执行 p95 ≤ 2s、p99 ≤ 5s（由 poll 1s 推出）。
- 拔机演练：2 台任务机拔掉 1 台，零丢失、零重复副作用（v4:1373）。
- 浸泡段 24h：期间做 3 次平滑重载，任务零丢失，Web 零失败（v4:1944）。

---

## 7. workerman 优势落到哪项能力

| workerman 优势 | 落到的框架能力 | 对应条目 |
|---|---|---|
| 常驻内存（文件只解析一次，连接可复用） | 启动时一次性加载路由 / 权限 / 清单注册表（构建期生成）；**每进程连接工厂复用连接**；弱密码表等不可变数据进程内常驻 | §3 `ConnectionFactoryInterface`、P2；§9 吞吐目标主要来自这里 |
| 多进程 + 每进程事件循环 | 进程组隔离（Web / 流式 / 任务 / 调度 / relay），一组出故障不拖其他组；单进程挂多路 SSE | P1、§3 进程表 |
| 内建 SSE / WebSocket | J-5 流式基础层（已有）：AI 流式、权限复查断流；WebSocket 推送放 1.1（v4:1068） | §9 H3 |
| 自定义进程 | task / schedule / outbox_relay / log_retention / channel 都是自定义进程，不需要外部 Supervisor（Laravel / Symfony 要靠 Supervisor） | §3 |
| 平滑重载（`reload`，跑完当前请求再退出）+ `max_request` | 发版不停服（单机 reload + 多实例滚动）、内存泄漏兜底 | §9 H4、Q20 |
| master 守护子进程 | 单进程崩溃自愈 + 任务租约接管 | §9 H5、§2.8 |
| webman/channel | 跨进程广播（配置变更、撤权断流）、机器级并发闸计数 | C4、v4:1384 |
| 非阻塞 TcpConnection + onBufferFull | 流式背压 | C9 |

---

## 8. PHP 原生异步路线（单列）

### 8.1 官方事实核对（2026-11-19 查证；只记官方来源）

| 项 | 状态 | 链接 |
|---|---|---|
| PHP 8.6.0 GA | **已发布，2026-11-19** | https://www.php.net/releases/8.6/en.php ；https://www.php.net/ChangeLog-8.php ；迁移指南 https://www.php.net/manual/en/migration86.php |
| 8.6 里与异步相关的唯一交付：Polling API `Io\Poll` | **Implemented（8.6）**，投票 33/1/4（2026-05-20 至 06-03）。发布页原文：「monitors many streams at once using … epoll, kqueue, event ports, or WSAPoll … designed as a common foundation that event loop libraries such as ReactPHP and AMPHP can build on」。只有 `StreamPollHandle`，没有 socket / timer / 进程句柄 | https://wiki.php.net/rfc/poll_api |
| Polling API Additions（补 Socket / Timer / Signal / Process 句柄） | **Under Discussion**，目标 8.7，未投票 | https://wiki.php.net/rfc/poll_api_additions |
| PHP True Async（协程 + await + 取消 + LibUV reactor C-API） | **Canceled**，投票未进行；取消原因页面没写（未查 internals 邮件列表，**不臆测**） | https://wiki.php.net/rfc/true_async |
| True Async: Scope & Structured Concurrency | Draft | https://wiki.php.net/rfc/true_async_scope |
| Concurrency Support in the PHP Engine（引擎级可插拔调度 ABI，不加任何用户态 API） | **Under Discussion**，目标「Next PHP 8.x（8.7）」，未投票，PR php/php-src#22561 | https://wiki.php.net/rfc/async_scheduler_abi |
| Fibers | Implemented 8.1：只提供挂起 / 恢复原语，RFC 原文明确不提供事件循环和调度器 | https://wiki.php.net/rfc/fibers |
| async / await 语法 RFC | **未找到**（`/rfc/async` 404） | https://wiki.php.net/rfc |

**结论**：「8.6 起核心逐步支持异步」这个方向成立，但 8.6 本身**不能让 PHP 业务代码异步执行**：没有协程调度器，PDO / curl / mysqlnd 也没有非阻塞化。最早可能到位的是 8.7 的调度 ABI，而且它不给用户态 API，最终还得靠 Revolt / AMPHP / workerman 这类库在上面封装。所以「完全支持」至少要等到 8.7 之后的若干个版本。这是根据 RFC 状态做的推断，没有官方时间表。

### 8.2 实现可替换：业务代码不改的做法

核心原则：**业务代码保持「同步写法」，不碰 Promise、回调、await；所有 I/O 都经过框架端口。** Fiber 类协程（以及 Swoole 的 hook）可以在不改调用方的前提下，让端口内部换成非阻塞实现，函数不会分成「同步 / 异步」两类。所以只要业务不直接写异步代码，换运行时就只需要换端口实现。

| 业务看到的接口 | 1.0 实现（多进程 + 队列） | 以后的原生异步实现（2.x 候选） | 业务代码 |
|---|---|---|---|
| `TaskQueueInterface` / `TaskHandlerInterface` | 库表队列 + 任务进程 | 同一张库表；任务进程内用协程并发跑 N 个处理器 | 不变 |
| `ConnectionFactoryInterface::forUnitOfWork` | 每进程一条，按单元取用 | 协程连接池（webman/coroutine `Pool` 那类），按协程绑定 | 不变 |
| `ContextStoreInterface` | `Request::$context` / `TaskContext` | 协程 Context | 不变 |
| `ClockInterface::sleep` | `usleep`（仅任务进程允许） | 协程让出 | 不变 |
| `OutboundHttpInterface` | curl 同步 + 超时 | 非阻塞 HTTP 客户端 | 不变 |
| `LockInterface` / `RateLimiterInterface` / `ConcurrencyGateInterface` | 咨询锁 / Redis / 进程信号量 | 同驱动；并发闸改为协程信号量 | 不变 |
| `StreamWriterInterface`（已有） | 非阻塞 socket + Timer | 协程写 | 不变 |

### 8.3 现在就要守的代码规矩（1.0 起纳入静态检查门禁）

1. **禁止可变的全局 / 静态状态**：`static` 属性和 `$GLOBALS` 只允许放不可变资源（配置快照、构建期注册表）。现有的 `StreamRuntime::$supervisors`、`WeakPasswordRuntime::$list` 列入白名单并注明原因；新增的一律不允许。PHPStan 自定义规则落地。
2. **上下文只经过 `ContextStoreInterface` 取**：禁止把「当前用户 / 租户 / 事务 / 请求号」存进单例属性。现在的 `RequestContextStore` 写法是对的（main:`Http/RequestContextStore.php:13-23`）。
3. **连接按工作单元取用，不跨请求、不跨任务持有，也不存进静态属性**；请求结束时断言没有未提交的事务（v4:1318）。
4. **业务层禁止直接做阻塞调用**：`sleep` / `usleep` / `curl_*` / `new PDO` / `file_get_contents(http…)` / `stream_socket_client`，一律经过端口（PHPStan 禁用函数清单）。
5. **业务层禁止引用 `Workerman\*` / `Timer` / `pcntl_*`**（deptrac）。
6. **不依赖「同一进程内顺序执行」来保证正确性**：所有「先查后改」都要用库的原子更新或锁。开了协程以后，同一个进程里也会出现交错执行。
7. **禁止 `exit` / `die`、禁止修改 `ini_set` 级全局配置、禁止依赖输出缓冲**（常驻 + 协程都会出问题）。
8. **锁内、事务内不出网，也不做长时间计算**（已有规则：skills database.md:12-13）。开了协程以后，锁内让出会放大持锁时间。
9. **处理器必须幂等**（至少一次语义），这和运行时无关。
10. 沿用 TypePHP 禁止清单（魔术方法、动态属性、`$$var`、闭包 bindTo 等，skills typephp.md:22-29）。这些写法在协程下同样会出问题。

### 8.4 CI：PHP 版本兼容矩阵（建议）

| Job | PHP | 数据库 | 必须通过 | 说明 |
|---|---|---|---|---|
| unit + contract | 8.4（最低版本，SCM-10）、8.5、**8.6** | PG 15 / 最新 LTS × MySQL 8.0 末版 / 8.4 LTS（skills delivery-loop.md:48） | 是 | 双库矩阵和 1b 的 CI 门禁行共用，不重复计 |
| phpstan max + deptrac + 自定义规则 | 8.6 | — | 是 | §8.3 的规则 |
| nightly | 8.7-dev（有 snapshot 后再加） | PG | 否（allow-failure） | 提前发现弃用项 |
| async-experiment | 8.6 + `exp/async-runtime` 分支 | PG | 否 | §8.5 |

`composer.json` 的 `php` 约束和 `config.platform.php` 要和 SCM-10 对齐：改成 `>=8.4`、platform 8.4.x。这是现有的不一致，不属于本方案新增的工作量，但必须在 1.0 发版前改好；归 1b 的依赖 / CI 行处理。

### 8.5 原生异步试验分支与评估里程碑

- 分支：`exp/async-runtime`。只替换 §8.2 表里的端口实现，**不准改业务模块**；业务模块有任何改动，就说明可替换性设计失败，必须回到 §8.2 修接口。
- **每个 PHP 小版本 GA 后评估一次**，出一页报告（结论：采纳 / 暂缓 / 放弃）。
- 进入下一个大版本框架（2.x）要**全部满足**以下标准：
  1. 官方核心有调度器（类似 async_scheduler_abi 的 RFC 已 Implemented），或者 workerman 官方驱动在该版本上稳定，且 PDO（pgsql / mysql）在其上是非阻塞的；
  2. main 全部契约测试在协程驱动下零失败，双库都过；
  3. I/O 密集基准（每请求 1 次 50ms 外部调用 + 1 次查库）单机吞吐 ≥ 多进程模式的 3 倍，p99 不变差；CPU 密集基准（JSON / 鉴权）下降 ≤ 20%（对照 workerman 官方自述的 10–20% 损耗）；
  4. 24h 浸泡内存平稳，没有串请求（每请求校验上下文 + 并发注入测试）；
  5. TypePHP 编译路径兼容（编译可选，BOSS:30 #19）；
  6. 连接预算：协程连接池总连接数不超过 v4:1290 的预算。
- 里程碑：
  - **M0（8.6，本次）**：只有 `Io\Poll`，结论预期「暂缓」。只做 PoC，用它给 `outbox_relay` 做多 socket 轮询，评估能不能替代 Timer 轮询。
  - **M1（8.7 GA）**：如果调度 ABI 和 Poll Additions 都被接受，在分支上跑标准 1–4。
  - **M2**：满足全部标准后，写 2.x 的 ADR，交老板拍板。

---

## 9. 高性能目标（有数字、写来源）

老板原话：「做一个安全、稳定、高效的框架，我以为高效能涵盖了高性能。」下面把「高性能」拆成可以验收的数字。数字分三类来源：v4 已定的业务门禁、TechEmpower 公开基准、成熟框架或官方文档值。凡是需要我们自己实测才能确认的，都标「基线 PR 实测确认」。

### 9.1 目标表

| # | 指标 | 1.0 目标 | 来源 / 推导 |
|---|---|---|---|
| H1a | 业务吞吐（首期部署） | 达标段 750 req/s + 300 路 AI 跑 30 分钟；过载段 900 req/s 返回 429 但不雪崩，降回负载后 1 分钟内恢复 | v4:1938-1947（容量模型 v4:1208） |
| H1b | 框架开销：整套中间件链（CSRF → Error → Auth → 上下文 → 日志）的 JSON 端点 | **≥ 同机裸 webman hello JSON 的 15%** | TFB R23：同为 workerman 上的全栈框架，laravel-workerman 157k / 裸 workerman 2.58M ≈ 6%，symfony-workerman 282k / 2.58M ≈ 11%；目标是优于 Symfony 档 |
| H1c | 框架开销：单查询端点（DBAL + 仓储 + 鉴权） | **≥ 同机裸 webman-pgsql 单查询的 25%** | TFB R23：laravel-workerman 76.8k / workerman-mysql 828k ≈ 9%，symfony-workerman 163k / 828k ≈ 20% |
| H1d | 绝对下限（参考机 4 核 8G，PG 同机房） | 单查询端点 ≥ 5,000 req/s / 机 | webman 官方：4 核 4G 原生 PDO 单查约 1.78 万 QPS，换 Laravel Db::table 后约 0.94 万；取 0.94 万再按鉴权 + 审计开销打约 5 折。**基线 PR 实测确认** |
| H2 | 延迟分位（服务端，达标段） | 登录 P95 500ms / P99 1s；列表 300 / 800ms；写入 500ms / 1s；同步批 1s / 3s；AI 首字 P95 2s / P99 5s | v4:1220-1226。TFB 不公布 p99（原始结果只有 avg / stdev / max），所以分位数只能由我们自己压测出 |
| H3 | AI 流式并发 | 每个 ai_stream 进程 ≥100 路（`config/stream.php` 现值）；全局 300 路，留 ≥2 倍余量（8 进程 × 100 = 800）；上游慢到 30s 时同机普通请求 P95 不变差 | v4:1297-1299、main `config/stream.php:15-35`；workerman 官方「单进程上万并发连接」是上限参考，我们按心跳 + 权限复查开销保守取值 |
| H4 | 发版不停服 | 单机 `reload` 期间零失败请求（浸泡段做 3 次）；多实例滚动「先加后删」不丢请求；SSE 断开后 ≤15s 带游标补发 | v4:1944、v4:1328、心跳 15s（v4:1310） |
| H5 | 单进程崩溃自愈 | `kill -9` 一个 web 进程：只有该进程上的在途请求失败，新进程 ≤3s 开始服务（master 立即重拉，3s 包含启动加载；**基线 PR 实测确认**）；任务进程崩溃后任务 ≤ 租约 60s + poll 1s 被接管 | workerman master 守护机制；`task.lease_seconds=60` |
| H6 | 队列 | 见 §6：≥200 任务/秒/库，投递到开始执行 p95 ≤2s / p99 ≤5s | §6 推导 |
| H7 | 资源 | 24h 浸泡内存斜率 ≈0（每进程增长 ≤ 水位的 10%）；主库 CPU ≤70%；连接 ≤70% | v4:1940-1944 |
| H8 | 可用性 | 登录 / 授权 / 同步 99.9%，AI 99.5% | v4:1214-1215 |

### 9.2 对标 Go / Java 一流框架（以 PHP FPM 框架为底线）

TFB R23（Citrine 物理机，40GbE；各并发档的峰值 req/s 由子代理从官方原始结果 JSON 计算，**发布前须复核**；来源：https://www.techempower.com/benchmarks/#section=data-r23 ，原始数据 https://www.techempower.com/benchmarks/results/round23/ph.json）：

| 框架 | JSON | 单查询 | Fortunes | 备注 |
|---|---|---|---|---|
| Laravel（FPM，底线） | 26,902 | 17,837 | 16,493 | |
| Symfony（FPM，底线） | 63,238 | 30,371 | 25,865 | |
| 裸 webman / webman-pgsql | 2,616,793 | 958,206 | 733,134 | 我们的「天花板」 |
| Gin / gin-gorm | 498,192 | 222,675 / 245,228 | 110,596 | |
| Spring（MVC） / WebFlux | 326,579 / 1,286,696 | 205,876 / 322,865 | 243,640 / 245,589 | |
| Vert.x | 2,889,592 | 1,315,264 | 1,040,600 | |
| Quarkus | 2,457,422 | 802,294 | 431,465 | |
| go-zero / Kratos / Hyperf | R23 无条目 | | | |

按 H1b / H1c 的目标折算：JSON ≈ 2.62M × 15% ≈ 39 万，单查询 ≈ 0.96M × 25% ≈ 24 万。也就是 **JSON 约为 Gin 的 0.8 倍、Spring MVC 的 1.2 倍；单查询和 gin-gorm、Spring MVC 同档；是 Laravel FPM 的 13–15 倍**。注意 TFB 测的是极简端点，我们的端点带鉴权和审计，所以对标的是**比例**，不是绝对数。

| 维度 | 一流框架怎么做 | 我们在 webman 上 1.0 能做到 | 约几成 | 差距原因 → 以后的补法 |
|---|---|---|---|---|
| 吞吐与延迟 | Vert.x / Quarkus 多 reactor + 非阻塞驱动；Gin goroutine | 常驻多进程，每进程同一时刻一个请求，阻塞 PDO | JSON / 单查询约为 Gin、Spring MVC 的 8–12 成（H1 推导）；I/O 长等待场景只有 Vert.x 的 2–3 成 | 没有协程，阻塞 I/O 占住进程 → 长等待已经用 SSE 非阻塞 + 后台任务规避；2.x 评估协程（§8.5） |
| 连接池 | HikariCP 线程共享池；Vert.x / Quarkus 反应式池 | 每进程固定 1 条主连接 + 按需从库连接（v4:1290）；不需要池，因为同一时刻只有一个请求 | 7 成 | 连接数随进程数线性增长（百万级 1,300–2,600 条，v4:1292）→ PgBouncer 事务模式 / ProxySQL（前提是会话无状态，§8.3 规矩 3）；2.x 用协程连接池 |
| 限流 | Spring Gateway Redis 令牌桶；go-zero TokenLimiter；Kratos BBR 自适应 | Redis / DB 共享计数，固定窗口 + 令牌桶，429 + Retry-After | 7 成 | 没有自适应 → 1.1 做简化 BBR（C7） |
| 熔断 | Resilience4j；go-zero 出站自动 SRE 熔断 | SRE 公式，覆盖 AI 服务商 + Webhook，按进程存状态 | 6 成 | 1.0 不是所有出站都自动接；各进程状态不共享 → 1.1 统一出站客户端自动接入 |
| 降级 / 卸载 | Quarkus `@Fallback`；go-zero CPU 90% 自适应卸载 | P-6 故障表固定降级 + 并发闸 + 过载段压测 | 5 成 | 没有自适应卸载，也没有注解式 fallback（TypePHP 禁运行期注解）→ 1.1 BBR；fallback 在清单里声明 |
| 隔离（Bulkhead） | Resilience4j Bulkhead | 进程组物理隔离 + 并发闸 | 9 成 | 进程隔离比线程隔离更彻底 |
| 可观测 | Actuator + Micrometer + OTel；go-zero Prometheus | 结构化日志 + 请求号 + traceparent 透传；`/healthz` 存活 / 就绪分离；`/metrics`（Prometheus 文本格式，channel 汇总各进程）覆盖四黄金信号 + 队列指标 | 5 成 | 没有自动埋点和分布式 trace 导出 → 1.1 OTel 导出（v4:1038「后续可接 OTel / Prometheus」） |
| 优雅停机 / 不停服发版 | Spring `server.shutdown=graceful`；Quarkus `shutdown.timeout` | workerman reload 跑完当前请求再退出；任务 drain；多实例滚动 | 8 成 | 长连接（SSE）在 reload 时会断 → 客户端游标补发（已有设计）；就绪探针先摘流量再 reload 由部署脚本做（运维暂缓，BOSS:73） |
| 配置管理 | Spring Cloud Config / Nacos 动态刷新；Kratos 多源 + watch | 文件 + env + 库配置；变更经 channel 广播清本地镜像（v4:1384-1385）；启动时校验 | 6 成 | 没有统一的动态刷新，大部分配置需要重载才生效 → 1.1 配置中心驱动（可换 Nacos / Apollo，v4:1385） |
| 进程守护 / 自愈 | 依赖 K8s / systemd | workerman master 内建 | 10 成 | 不需要额外组件，这是我们的优势 |

---

## 10. 工作量（1.0）与 v4 / 1b 的关系

PR 编号沿用 1b 的风格，暂定为 `3I-1`…`3I-7`。人天按区间给，中点排序。

| PR | 内容 | 人天 | 与现有行的关系 |
|---|---|---|---|
| 3I-1 | 任务队列补齐：Q2–Q15、Q20、§4.1 迁移、契约测试 1–6 | 4–6 | **替代并扩展** v4:1372/1374 中「任务表 + 租约」部分；A19 导入导出（v4:1160）的**业务接入不在这里**，A19 只做调用，扣掉 A19 里原来估的队列部分 |
| 3I-2 | 调度接线：`SchedulerInterface`、schedule 进程、§4.2 表、补跑 / 漏跑告警、契约测试 7 | 2–3.5 | **合并** 1b B11「定时调度器」（1b:754），B11 不再单独计 |
| 3I-3 | EventBus 构建期注册 + outbox relay 进程 + 事件转任务 + 契约测试 8 | 3–5 | **合并** v4:1378 的 outbox relay；Webhook 工作池 v4:1677 **不并入**，单独计 |
| 3I-4 | 并发原语：LockInterface 三驱动、RateLimiterInterface 替换进程内限流器、ConcurrencyGate、SRE 熔断、契约测试 9 | 3–5 | **合并** AC-7 本机并发闸（v4:610）、v4:1380 锁、v4:1381 熔断、1b B10 缓存与锁的「锁」部分；限流阈值是 v4:1520 已定的值，不重复计 |
| 3I-5 | 运行时卫生：`ConnectionFactoryInterface`（每进程复用 + 断线重连 + 残留事务断言）、`ContextStoreInterface`、`max_request` / 内存水位、`/healthz` 存活与就绪、§8.3 PHPStan / deptrac 规则 | 3–4.5 | **新增**（v4:1318 只定了规矩，没有对应的工作行）；`/healthz` 在 v4 公开清单里，但没有工作行 → 新增 |
| 3I-6 | 可观测：`/metrics` 汇总、队列 / 调度 / 闸 / 熔断指标、任务重试 / 取消 / 死信 API + CLI、告警接入 | 2–3 | **合并** J-14 告警接入点（v4:1409）中「任务堆积 / 漏跑」两项 |
| 3I-7 | 基准与 CI：压测脚本（随仓库发布供用户复现，放 `tools/bench/`）、裸 webman 基线对照、H1–H6 报告；PHP 8.4/8.5/8.6 矩阵 job；`exp/async-runtime` M0 PoC | 2–4 | **合并** v4 P-4 压测门禁中「框架基线」部分；双库 CI 矩阵归 1b CI 行，不重复计 |
| **合计** | 7 PR | **19–31** | 其中与 AC-7、B11、v4:1378/1380/1381/1409、P-4 重叠约 6–9 人天，**净新增约 13–22 人天** |

依赖顺序：3I-5 → 3I-1 → (3I-2 ∥ 3I-3 ∥ 3I-4) → 3I-6 → 3I-7。3I-4 里的并发闸依赖鉴权 WP1（1b:131 / 187 已标），可以先做锁和限流。

不在本方案里计的：审计异步批量写（1b 2-H2）、Webhook 工作池（v4:1677）、导入导出业务（A19）、流式层（J-5 已有）、双库 CI（1b）、`composer.json` PHP 约束对齐（1b 依赖行）。

---

## 11. 资料来源

- Laravel Queues / Horizon / Scheduling / Cache / Rate Limiting / Events：https://laravel.com/docs/12.x/queues 、/horizon 、/scheduling 、/cache 、/rate-limiting 、/events
- Symfony Messenger / Lock / RateLimiter / EventDispatcher：https://symfony.com/doc/current/messenger.html 、/components/lock.html 、/rate_limiter.html 、/event_dispatcher.html
- webman：redis-queue https://www.workerman.net/doc/webman/queue/redis.html ；event https://www.workerman.net/doc/webman/components/event.html ；crontab https://www.workerman.net/doc/webman/components/crontab.html ；limiter https://www.workerman.net/doc/webman/components/rate-limiter.html ；官方基准 https://www.workerman.net/doc/webman/others/benchmarks.html
- workerman：特性 https://www.workerman.net/doc/workerman/getting-started/feature.html ；协程 https://www.workerman.net/doc/workerman/coroutine/coroutine.html ；SSE https://www.workerman.net/doc/workerman/http/SSE.html ；reload 原理 https://www.workerman.net/doc/workerman/faq/reload-principle.html ；max_request https://www.workerman.net/doc/workerman/faq/max-requests.html
- Hyperf：https://github.com/hyperf/hyperf/blob/master/docs/en/async-queue.md 、amqp.md 、crontab.md 、rate-limit.md
- go-zero 熔断 / 限流 / 自适应卸载：https://go-zero.dev/components/resilience/
- Kratos：https://go-kratos.dev/docs/component/ （ratelimit、circuitbreaker、metrics、tracing、config）
- Gin 优雅停机：https://gin-gonic.com/en/docs/server-config/graceful-restart-or-stop/
- Spring Boot 优雅停机：https://docs.spring.io/spring-boot/reference/web/graceful-shutdown.html ；HikariCP：https://docs.spring.io/spring-boot/reference/data/sql.html ；Actuator：https://docs.spring.io/spring-boot/reference/actuator/index.html
- Spring Cloud CircuitBreaker（Resilience4j）：https://docs.spring.io/spring-cloud-circuitbreaker/reference/spring-cloud-circuitbreaker-resilience4j.html ；Config：https://docs.spring.io/spring-cloud-config/reference/server.html
- Vert.x：https://vertx.io/docs/vertx-core/java/
- Quarkus：https://quarkus.io/guides/smallrye-fault-tolerance 、https://quarkus.io/guides/lifecycle 、https://quarkus.io/guides/config
- TechEmpower R23：https://www.techempower.com/benchmarks/#section=data-r23
- PHP 官方：见 §8.1 表内链接

---

## 12. 减掉的清单（一行一项）

| # | 减了什么 | 为什么 | 什么时候补 |
|---|---|---|---|
| R1 | 业务可用的请求内并发（Promise / Parallel / async-await） | 会让函数分成同步和异步两类，以后换运行时就得改业务代码；1.x 不开协程 | 2.x，前提是 §8.5 标准全部达到 |
| R2 | Workerman 5 协程驱动（Swoole / Swow / Fiber） | 1.x 写死不开协程；极限性能下降 10–20%；Fiber 遇到阻塞 PDO 会卡住整个进程 | 2.x 候选，和 R1 一起评估 |
| R3 | PHP 原生异步 | 8.6 只有 `Io\Poll`，调度器 RFC 还没投票 | 每个 PHP 小版本评估一次（§8.5），8.7 GA 时做 M1 |
| R4 | webman/redis-queue 作为主队列 | 自动 ack，进程崩溃丢消息；不能和业务写在同一事务 | 不补；Redis 驱动改用 Streams（R5） |
| R5 | Redis Streams 队列驱动 | 首期量级（日均 0.1 任务/秒，峰值 ≤10/s）库表队列有 20 倍余量 | 1.1；触发条件：任务 >50/s 持续，或任务表认领 SQL 占主库 CPU >10% |
| R6 | RabbitMQ / Kafka 等独立消息队列 | 首期不需要，还会多一个要运维的组件（运维暂缓，BOSS:73） | 百万级档（v4:1235），1.x 内只保留接口 |
| R7 | 任务链 / 批量（chain / batch） | 1.0 的业务都是单任务 | 1.1，或第一个真实需求出现时 |
| R8 | 任务管理页（重试 / 取消 / 死信可视化） | 1.0 先给 API + CLI，够运维使用 | 1.1 |
| R9 | 任务进度实时推送（WebSocket） | v4:1068 已定为后续版本；1.0 用轮询读 progress | 1.1 |
| R10 | 秒级定时任务 | 后台业务不需要，还容易压库 | 不补，除非有真实需求 |
| R11 | 自适应过载保护（BBR / CPU 卸载） | 多进程模型下排队发生在内核 accept 队列，需要新的采样方式；1.0 用限流 + 并发闸 + 过载段压测兜底 | 1.1 |
| R12 | 所有出站请求自动熔断 | 1.0 只有 AI 服务商和 Webhook 是高风险出站 | 1.1，等统一出站客户端 |
| R13 | 注解式 `@Fallback` / `@Retry` | TypePHP 禁运行期注解 | 不补；改为在清单里声明 |
| R14 | OTel trace / 指标自动导出 | 1.0 先做 traceparent 透传 + Prometheus 文本 | 1.1（v4:1038） |
| R15 | 动态配置刷新 / 配置中心驱动 | 1.0 用启动校验 + 广播清镜像，覆盖关键开关 | 1.1（v4:1385 接口已留） |
| R16 | 事件溯源 / 事件重放存储 | 后台管理框架用不到 | 不补 |
| R17 | Saga 编排 / 分布式事务 | v4:456 首期不做；outbox + 幂等 + 死信重放可以覆盖 | 不在 1.x |
| R18 | 载荷加密 | 已禁止载荷存凭据，加密收益很低 | 不补 |
| R19 | 单独的死信表 | 死信用 `state='dead'` + 部分索引，少一张表 | 不补（outbox 的死信表按 v4 保留） |
