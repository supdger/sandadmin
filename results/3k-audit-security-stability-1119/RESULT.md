**结论：按现在的设计，还不能称为“安全、稳定的高水准框架”。** v4 设计稿的条文对 ASVS L2 的大部分领域都写到了，覆盖面达到成熟框架水平；但有三类问题还在：信任根、威胁建模、崩溃自愈与停机这几处设计没定或漏写；条文到代码之间没有门禁闭环（CI 不跑测试，main 不设保护）；主仓里不少关键能力只有接口或只在仓外。最关键的 10 项缺口（按严重度排）：
1. 【高】插件供应链信任根没定：v4 顶层:405 自己写着“信任根细则待定”，公钥靠 `--pubkey` 传入，没有 kid、吊销表和部署方白名单；PackageOps 还有“环境变量绕过编译闸”的问题（1d M1）。
2. 【高】设计阶段没有威胁建模和“安全不变量”清单：PackageOps 一次查出 3 个漏洞（环境变量绕过、报错回显口令、卸载跟随软链误删），都是设计层可以预防的同类问题。
3. 【高】门禁不闭环：CI 没有 PHPUnit/PHPStan，main 没有分支保护（1d:96、1d:139）。SCM-01~11 写在纸上，执行面实际为零。
4. 【高】生产鉴权链与数据权限没在主仓落地：生产用 `UnavailablePrincipalProvider` 恒返回 null；登录锁定、2FA、刷新轮换都在仓外，无法证实；`data_scope` 已建模，但查询侧不过滤（鉴权:372 自认是“过渡口径”），存在水平越权风险。
5. 【高】进程崩溃自愈、守护、健康检查基本缺失：v4 没写 systemd/supervisor 守护和子进程拉起时限，`/healthz` 推到 P2（顶层:1058）；main 没有健康检查接口。
6. 【高】优雅停机与不停服发版只到“原则”：`stop_timeout=2`，没有排空流程，没有“先就绪摘流”；`max_requests=0`；发版排空标为运维暂缓（顶层:1936）。
7. 【高】限流与连接池在代码里形同没有：限流在进程内，且每次请求都新建一个；DB 连接每次新建、不复用（3i:16-17）。生产 API 和登录路径都没有限流。
8. 【中高】数据库故障切换没有设计（顶层:1951 暂缓），读写分离只在设计里，代码只有单连接；迁移执行器和回滚只有设计、没有代码（3d:70-74）。
9. 【中高】可观测性离一流框架差距最大：日志是行格式，不是 JSON，request id 没注入每条日志；没有 `/metrics`，没有 trace，告警全部暂缓。
10. 【中】零散的 ASVS L2 条款没写：通用输出编码/XSS 规约、反序列化与批量赋值禁令、`trusted_proxies`（IP 可被伪造，限流和审计会被绕过）、zip bomb 上限、审计日志防篡改（哈希链在 1.1）。

建议 1.0 补齐：上面 1–7 项全部，加上 8、9、10 的最小集。本报告新增约 **24–38 人天**，不含 3i、3j 已计的部分（见第 5 节）。

---

# 1.x 架构设计安全与稳定专项审计（3k，只读）

- 审计日期：2026-11-19
- 输入：`~/agent-work/grokbot/review-1117-r3/` 下的顶层架构设计-v4.md（下称“顶层”）、鉴权P0-v4.md（“鉴权”）、分段上传方案-1119.md（“分段”）、审计-遗漏.md、差异表-audit.md、待老板确认.md、SandAI-1x位置和计划.md（“SA”）；`plan-xref-1117/老板已定事项总表.md`（“总表”）；supdger/sandadmin `origin/main = 977427a`（只读，用 `git show/grep origin/main`）；1d、3i、3j（进行中，只读现状）、3a、3d、2b、2d、3h 的结果。
- 口径：“v4 覆盖”只说明文档写了；“main 实况”是代码证据。登录、验签、安装器、迁移执行器都在 sand-core/sand-package，仓外内容标“仓外不可证”。
- 已有结论只引用、不重算：异步/并发/队列/限流/连接池（3i），成熟框架对照表 54 条（3j），PackageOps M1–M4（1d）。

## 1. 安全（ASVS 4.0.3 L2 为基线，对照 5.0 章号）

| # | 领域（ASVS 章） | v4 是否覆盖（行号） | main 实况 | 缺口 | 严重度 | 建议补法 | 版本 | 人天 |
|---|---|---|---|---|---|---|---|---|
| S1 | 认证与会话（4.0 V2/V3；5.0 V6/V7） | 覆盖较好：Argon2id+重哈希 鉴权:197/739；密码策略 鉴权:652；三维度锁定 鉴权:226-243；TOTP+恢复码 鉴权:656-664；会话 12h+30min 鉴权:202；会话固定/轮换 鉴权:171；找回凭证只存摘要 鉴权:705；吊销与 authz_version 鉴权:195 | Argon2id、弱密码表、会话哈希、改密吊销都有（UserService.php:939/745-756）；生产 Authenticator 只有接口（BizKernel.php:80 TODO #76）；登录锁定与 2FA 在仓外不可证 | ① 生产认证链没进主仓门禁，无法用测试证明 L2 条款；② 宿主没有刷新令牌，轮换/重放检测只适用 SandIAM（鉴权:100），两层口径要写清；③ 短信找回（总表 #9）的号码变更、SIM 换卡风险没写 | 高 | 认证契约测试套件进 main CI（锁定/2FA/吊销/会话固定逐条做 ASVS 用例）；短信找回加“换号冷却期+旧号通知” | 1.0 | 3–5 |
| S2 | 授权与数据权限（V4 / 5.0 V8） | 覆盖：默认拒绝 鉴权:347；授权不越级 鉴权:617；DBAL 全局作用域+可选 RLS 鉴权:805；授权读主库 鉴权:191 | 控制器 `permit()` 做 401/403（OrgController.php:295-305），关默认路由；data_scope 已建模（Role.php:12），**查询不按 scope 过滤** | 行级数据权限是“过渡口径”（鉴权:372），没有统一的强制机制；中间件未挂载，鉴权靠每个控制器自觉调用，漏调就是越权 | 高 | ① 1.0 落地 DBAL 作用域强制（Repository 层没有 scope 上下文就拒查）；② 静态门禁：每个路由必须声明 x-permission，且控制器入口必须调 permit；③ IDOR 用例模板 | 1.0 | 4–6 |
| S3 | 输入校验与注入（V5 / 5.0 V1/V2/V3） | SQL 参数化与白名单 顶层:1790/1452；CSRF/CORS 鉴权:346；七个安全头+CSP 灰度 顶层:884/889；CSV 公式注入 顶层:924 | 参数绑定与 fieldMap 白名单落实（Repository.php:52/268），没发现拼接；安全头与 CSRF 在 PR #107，**未合并**（3a） | 没写：通用输出编码/XSS 规约（只写了富文本清洗）、`unserialize` 禁令、批量赋值（入参 DTO 白名单）；`trusted_proxies` 未做（3a:N2） | 中 | 在 v4 补“输入输出三条禁令”（禁 unserialize 外部数据、禁数组直灌模型、前端禁 v-html 渲染未清洗内容）并进静态门禁；trusted_proxies 1.0 实现 | 1.0 | 1.5–2.5 |
| S4 | 文件上传与路径（V12 / 5.0 V5） | 覆盖：双白名单+finfo 分段:41/344；web 根外随机键 分段:346；DISALLOW_LINKS 分段:346；下载鉴权 顶层:1456 | 上传链路完整（UploadService.php:61-75，LocalFileStorage.php:38-46） | ① 上传侧防了软链，**删除/卸载侧没写同一条不变量**，正是 1d M3 的成因；② zip slip 与 zip bomb 没写进 v4（1d:117 发现 bomb 无上限、`.ENV` 大小写可绕过） | 高 | v4 补“文件系统操作不变量”：所有删除/移动用 lstat、不跟随链接，路径 realpath 后必须落在根内，解包设条目数/总大小/压缩比上限，名单比较大小写不敏感；做成公共 `SafeFs` 一处实现 | 1.0 | 2–3 |
| S5 | 秘密与配置（V6/V14 / 5.0 V11/V13） | 覆盖：信封加密 KeyProvider 鉴权:73；AES-256-GCM+keyver 顶层:906；轮换 顶层:911；生产不带堆栈 鉴权:345 | ProductionDebugGuard 生产开 debug 拒启；DB 口令没默认值；KeyProvider 在 PR #102 **未合并**（2b） | ① 只写了“生产不带堆栈”，**没有“任何输出（HTTP/CLI/日志）都经过统一脱敏”的硬要求**，CLI 报错回显 DSN（1d M2）就是这个漏项；② 主密钥轮换周期没定；③ 配置项没有“密钥类”标记，无法自动防回显 | 高 | 定一条不变量：DSN/口令类对象一律用 `SensitiveString`（`__toString` 返回 [REDACTED]），CLI 与日志共用脱敏器，加回归测试；定主密钥年度轮换 | 1.0 | 1.5–2 |
| S6 | 插件供应链与签名（V10/V14 / 5.0 V15） | 先验签后解包、Ed25519 顶层:383/405；生产不安装 顶层:50；SBOM/禁带 vendor 顶层:1636；**信任根“待定”顶层:405** | 主仓没有验签代码（在 sand-package）；主仓 PackageGate 生产恒 compiled（PackageGate.php:26-31）；PR #94 侧有 M1 环境变量绕过 | 信任根、kid、吊销表、部署方白名单没定（2d:55-60）；验签与解包之间的 TOCTOU（1d:79）；插件没有沙箱，隔离只在内核边界；没有插件版本锁与降级拒绝（1d:137） | 高 | ① 公钥内嵌内核发行包、带 kid，吊销表随发行包下发并支持离线；② 生产判定只看构建期标记文件（编译产物不可改），**任何环境变量都不能放宽**，加反向测试；③ 先拷进隔离 work 目录再验再解；④ 拒绝降级和反向依赖缺失；⑤ 写明“插件 = 完全信任代码”，只装签名可信来源 | 1.0 | 4–6 |
| S7 | 日志与审计（V7 / 5.0 V16） | 覆盖：审计字段 鉴权:220/602；脱敏器 鉴权:632；保留 ≥6 个月 顶层:65；防篡改“部分采纳” 顶层:631 | 审计表含 request_id，与业务同事务（OrgAudit.php:21-43）；security 通道 183 天 | 防篡改只靠账号权限，哈希链在 1.1；登录审计在仓外；没有“安全事件→告警”的最小集 | 中 | 1.0 先定审计库只追加账号（DB-02），加每日摘要哈希外存（成本低）；1.1 做哈希链 | 1.0/1.1 | 1 + 2 |
| S8 | 出网与 SSRF（V12.6 / 5.0 V12/V15） | 覆盖好：统一出网 Client、禁 verify=false 顶层:1623；拒私网/元数据、锁定解析 IP 顶层:1675；webhook 禁重定向 鉴权:77 | 主仓没有出网代码，不适用 | 统一出网 Client 还没实现，SandAI/webhook 落地前必须先有 | 中 | 1.0 先交付 `OutboundHttpClient`（超时、IP 校验、禁重定向、响应体上限），再接 AI 与 webhook | 1.0 | 2–3 |
| S9 | 依赖与发版（V14.2 / 5.0 V15） | 覆盖完整：SCM-01~11 顶层:1628-1639 | 锁文件入库，`dependency-audit.yml` 有；**CI 不跑测试/静态检查，main 没有分支保护**（1d:96/139） | 条文完整，执行面缺；没有 SBOM 和 provenance | 高 | 1.0 发版前：main 分支保护+必过 job（PHPUnit、PHPStan、安全回归、依赖审计）；SBOM 与 provenance 随首个正式版 | 1.0 | 1.5–2.5 |
| S10 | 常驻进程串数据（5.0 V15.4 并发） | 覆盖：static 只存不可变资源、请求末断言 顶层:1318；连接池会话无状态 顶层:1292；3i §8.3 十条规矩 | 上下文放 `Request::$context`（RequestContextStore.php:12），没发现存用户态的高危 static | 还没有静态门禁落实这十条；“请求末无残留事务/上下文”断言没实现 | 中 | 按 3i §8.3 做 PHPStan 自定义规则+请求末断言（3i 已计人天，这里不重复） | 1.0 | 引 3i |
| S11 | **设计方法本身（5.0 V15.1 架构/威胁建模）** | 没有：v4 没有按模块的威胁模型或滥用用例要求 | — | 本次 PackageOps 三个问题的共同根因：设计只写了正向功能，没列“攻击者会怎么用” | 高 | 新模块方案模板加一节“威胁与不变量”（STRIDE 五问+不变量清单+对应负向测试），与“成熟框架对照表”（总表 #40）并列为方案准入门槛；对已定稿的 PackageOps、上传、鉴权、MCP 补一次 | 1.0 | 2–3 |

## 2. 稳定

| # | 领域 | v4 是否覆盖（行号） | main 实况 | 缺口 | 严重度 | 建议补法 | 版本 | 人天 |
|---|---|---|---|---|---|---|---|---|
| R1 | 主从与故障切换 | 读写分离纪律 顶层:212/1338，授权读主 鉴权:191；Redis 哨兵与演练 顶层:1324；**DB 主库切换“暂缓”顶层:1951** | 单 pgsql 连接，没有读写分离 | DB failover 没有设计：切换期写失败语义、连接重建、从库延迟摘除阈值都没写；切换后长连接常驻进程如何感知也没写 | 中高 | 1.0 写最小语义：连接错误分类、断线重连、可重试只读、写失败 fail-closed 503，从库延迟超阈值摘除回主；1.1 做演练 | 1.0/1.1 | 2 + 2 |
| R2 | 多实例无状态 | 覆盖好：LocalStateGuard 顶层:1327；≥2 实例门禁 顶层:1328；锁与调度 顶层:1374/1380 | session/cache 默认 file（session.php:20、cache.php:4）；v1 会话在 DB 表 | Guard 未实现，默认配置违背自己的设计 | 中 | 落地 LocalStateGuard，生产默认 redis/db（3j H1 已登记） | 1.0 | 引 3j |
| R3 | 平滑重载/不停服发版 | 原则有 顶层:1318/1328/1944；**排空与手册暂缓 顶层:1936/1974** | `stop_timeout=2`（server.php:20），没有 onWorkerStop 排空 | 缺“先摘流→排空→reload -g→就绪再接流”的标准流程；SSE 长连接发版处理只在 3i 提到 | 高 | 定发版序列：readiness 置 0 → 等 LB 摘除 → `reload -g`（web 组 stop_timeout 30s，任务组按 3i Q20）→ readiness 恢复；指标“发版窗口 5xx=0”进门禁 | 1.0 | 2–3 |
| R4 | 进程崩溃自愈 | **基本没写**：只有租约接管 顶层:1373；healthz 放 P2 顶层:1058 | 没有健康检查接口 | 没有守护方式（systemd 单元 Restart=always）、master 自身崩溃兜底、拉起时限（3i H5 ≤3s 只是目标）、崩溃循环熔断 | 高 | 1.0 交付 systemd 单元模板+`/livez` `/readyz`（就绪检查 DB/Redis/迁移版本），并加“kill -9 worker 3s 内恢复、kill master 由 systemd 拉起”验收用例 | 1.0 | 2–3 |
| R5 | 限流与背压 | 覆盖好 顶层:1381/1326/1298/1953 | 只有 dev MCP 用的进程内限流器 | 代码不能用（3i ③）；队列长度上限没有数字 | 高 | 按 3i C3/Q13（已计）；本报告只要求登录与公开端点限流先于其他接口上线 | 1.0 | 引 3i |
| R6 | 超时与重试/熔断降级 | 覆盖好：AI 超时表 顶层:1301-1310；DB 语句超时 顶层:1953；退避抖动 顶层:1378；熔断入口 顶层:1381 | 只有任务 max_attempts=3；没有熔断 | DB/Redis 连接级超时与读超时没有数值；没有“重试预算”（防重试风暴） | 中 | 补连接超时 3s、读超时随 statement_timeout；全局重试预算 ≤10%；熔断按 3i C5 | 1.0 | 0.5–1 |
| R7 | 备份恢复 | RTO 1h/RPO 5min 顶层:1198；恢复点接口 顶层:221；**演练 P2 顶层:1056-1058** | — | 演练与 runbook 暂缓；总表 #53 把运维暂缓，可以接受，但“没演练过的备份”不能作为发版回退依据 | 中 | 1.0 只做一次恢复演练脚本（拿最近备份恢复到临时环境并校验行数）；PITR 与跨地域 1.1 | 1.0/1.1 | 1 + 3 |
| R8 | 迁移回滚 | 覆盖好：三条纪律 顶层:221/398/525；expand/contract 顶层:398 | **没有迁移执行器**（3d:70-74，1d:138），含 migrations 的包能 PASS 但表不存在 | 设计完整，代码为零，是 1.0 的稳定性硬缺口 | 高 | 执行器（全局锁、可重入、半成品拒继续）先于任何插件上线；没实现前安装器拒绝含 migrations 的包 | 1.0 | 引 3d（未计则 3–5） |
| R9 | 监控告警/可观测 | 骨架：四黄金信号+traceparent 顶层:1382；**OTel/Prometheus/告警放后续 顶层:1034-1040** | X-Request-Id 有，日志行格式；有 metric 表，没有导出端点 | 1.0 连排障所需的最小可观测都不足 | 中高 | 1.0：JSON 日志+request id 自动注入+`/metrics`（Prometheus 文本，进程聚合）+5 条最小告警（5xx 率、P99、DB 连接、任务堆积、进程重启次数）；OTel trace 1.1 | 1.0/1.1 | 3–4 + 3 |
| R10 | 容量与压测 | 覆盖好：P-4 三段门禁 顶层:1940-1947；P95/P99 顶层:1220-1226 | 没有压测脚本 | 框架级基线（与裸 webman 比）还没测过（3i H1b-d 待测） | 中 | 按 3i 基线 PR 执行（已计） | 1.0 | 引 3i |
| R11 | 常驻内存泄漏治理 | 覆盖 顶层:1318/1944 | `max_requests=0`、`memory_limit_bytes=0`（stream.php:22/33），内存监控只在非 daemon 开 | 设计写了，配置反着设；水位阈值没有数字 | 中 | web 组 max_requests 10000（3i P2）+内存阈值 256MB 软回收；浸泡 24h 曲线作门禁 | 1.0 | 0.5 |

## 3. 对标 Go / Java 一流框架（老板 11-19 要求）

### 3.1 吞吐与延迟（只用公开数字，附来源）

TechEmpower R23 的原始结果（tfb-status.techempower.com）本机网络持续不可达（3 条独立路径失败），**不估算**，下面用第三方 The Benchmarker 数字（简单路由，16 核，zrk，数据日期 2026-11-05，抓取 2026-11-19）。

| 框架 | RPS c64 / c512 | P99 c64 / c512 (ms) | 相对 webman |
|---|---|---|---|
| vert.x 5.2 | 199,842 / 173,571 | 0.60 / 4.15 | 1.80× |
| fiber 3.6 | 187,551 / 158,406 | 1.05 / 5.37 | 1.69× |
| workerman 5.2（裸） | 173,597 / 154,189 | 0.84 / 4.01 | 1.56× |
| quarkus 3.39 | 151,114 / 138,319 | 0.88 / 4.79 | 1.36× |
| gin 1.12 | 111,896 / 101,885 | 2.04 / 12.36 | 1.01× |
| **webman 2.2** | **110,952 / 100,555** | **1.50 / 6.47** | 1.00× |
| spring 4.1 | 58,750 / 61,449 | 3.00 / 16.25 | 0.53× |
| symfony 8.1（FPM 系底线） | 3,393 / 3,552 | 49.72 / 347.18 | 0.03× |
| laravel 13.4（FPM 系底线） | 1,741 / 1,777 | 57.05 / 423.38 | 0.016× |

来源：`https://web-frameworks-benchmark.vercel.app/frameworks/{php/webman,php/workerman,php/laravel,php/symfony,go/gin,go/fiber,java/vertx,java/quarkus,java/spring}/`。TFB R23 Fortunes（含 DB）只拿到前排摘录：vertx-postgres 1,040,599、quarkus+vert.x 1,028,408 responses/s（`https://www.techempower.com/benchmarks/`，转引自 3j sources.md:19-20），webman 的 R23 Fortunes 数值取不到。webman 官方页宣称的 DB 单查询 1–2 万 QPS（4 核）属厂商口径（`https://www.workerman.net/doc/webman/others/benchmarks.html`）。

判断：**纯 HTTP 层 webman ≈ Gin，约为 Vert.x 的 55%，约为 Spring MVC 的 1.9 倍，是 FPM 框架的 30–60 倍**。真实业务差距在 DB 路径：我们是阻塞 PDO、每进程一个连接、请求内不能并发 I/O，Vert.x/Quarkus reactive 是单线程多路复用加异步池，DB 密集场景预计差距会拉大，但没有压测数字之前不下定量结论（按 3i H1b-d 做基线后回填）。我们框架叠在 webman 上的额外开销目标是 ≤15%（3i）。

### 3.2 六项能力对标

成数是本审计的定性判断（满分 10 成 = 该项一流框架的默认能力），不是测量值。

| 能力 | 一流框架怎么做（来源） | 现在（main） | 按 v4 做完 | 做不到的原因 | 以后补法 |
|---|---|---|---|---|---|
| 吞吐与延迟 | Vert.x/Quarkus 事件循环+reactive 驱动；Gin/go-zero goroutine 每请求 | HTTP 层 10 成（≈Gin）；DB 路径未测 | 同左 | PHP 没有原生异步 I/O（8.6 只有 Io\Poll，3i:19），协程驱动与阻塞扩展不兼容 | 2.x 原生异步；1.x 靠多进程+PgBouncer 横向扩展 |
| 连接池 | HikariCP（Spring Boot 默认，`docs.spring.io/spring-boot/reference/data/sql.html`）、database/sql SetMaxOpenConns（`pkg.go.dev/database/sql`）、Vert.x PoolOptions、Agroal | 2 成（DBAL 每次新建，3i:17） | 6 成（每进程常驻连接+预算自检+PgBouncer 事务模式，顶层:1290-1292） | webman 连接池只在 swoole/swow 协程驱动下有效（`workerman.net/doc/webman/db/config.html`）；不开协程时每进程最多 1 连接 | 进程级持久连接+健康探测+PgBouncer；池指标进 /metrics |
| 服务治理（限流/熔断/降级） | go-zero 自适应熔断（SRE 公式）+CPU 降载 shedding（`go-zero.dev/components/resilience/load-shedding/`）；Kratos BBR 限流；Resilience4j；Sentinel | 1 成 | 6 成（共享存储限流、熔断入口、降级接口，顶层:1381；自适应降载 1.1） | 进程间不共享内存，熔断状态只能按进程或放 Redis；没有成熟的 PHP 自适应限流库 | 1.0 Redis 原子限流+进程内 SRE 熔断（可参考 ackintosh/ganesha）；1.1 仿 go-zero 用 CPU+在途数做降载 |
| 可观测 | Actuator+Micrometer、OTel Go/Java、Kratos tracing 默认 W3C 传播 | 2 成（只有 request id） | 4 成（1.0 骨架）→ 1.1 后 7 成 | OTel PHP 自动插桩依赖扩展与 preload；常驻进程要手动插桩 | 1.0 按 R9 最小集；1.1 OTel 手动插桩（HTTP/DB/出网三处）+OTLP 导出 |
| 优雅停机/不停服发版 | Go `Server.Shutdown`；Spring `server.shutdown=graceful`+liveness/readiness；Kratos StopTimeout；K8s 滚动 | 3 成（有 reload，stop_timeout=2，没有就绪探针） | 5 成（发版排空暂缓） | workerman `reload -g`（`workerman.net/doc/workerman/install/start-and-stop.html`）机制够用，差的是我们没定流程；server.php/process.php 改动必须 restart | 按 R3、R4 补齐后可达 8 成 |
| 配置管理 | Spring Cloud Config/Nacos 推送；Kratos 多源+watch；Quarkus profile+构建期配置 | 4 成（静态 config/，改了要 reload，`workerman.net/doc/webman/config.html`） | 6 成（库+缓存失效+广播，可换 Nacos，顶层:1384-1385） | 静态配置是常驻进程的固有约束；没有 schema 校验与分层 | 1.0 补配置 schema 启动校验（3j G1 已计）；1.1 动态配置走广播 |

## 4. 与已有审计的关系（只引用，不重复）

- 1d PackageOps M1–M4：本报告 S4/S5/S6/S11 是这三个漏洞在设计层的根因与防复发补法，修复本身归 1d。
- 3i：限流、连接池、健康端点、队列、常驻规矩、性能目标都已列为 1.0 必补，本报告 R5/R10/S10 直接引用，人天不重复计。
- 3j：54 条对照表中 H1/H3/H4/H5/O3/O4/M1/P4/P5/G1 与本报告 R2/R1/R4/R3/R9/R7 重叠；本报告增加的是安全侧（S1–S11）和对“暂缓项”可接受程度的判断。
- 3a（安全头/CSRF，PR #107）、2b（KeyProvider，PR #102）、2d（SECURITY.md，PR #103）都**未合并**，合并前这些能力在 main 上不存在。
- 发现一处口径差异：代码取证显示 **main** 的 PackageGate 在 `APP_ENV=production` 时直接短路，不可被环境变量绕过；1d M1 针对的是 PR #94 的 sand-package 侧 PackageGate 与 tools/package-ops。两者不冲突，但合并 #94 前要确认最终只保留一个生产判定点，并按 S6② 只认构建期标记。

## 5. 人天汇总（本报告新增，不含 3i/3j/1d/3d 已计）

| 版本 | 项 | 人天 |
|---|---|---|
| 1.0 | S1 3–5、S2 4–6、S3 1.5–2.5、S4 2–3、S5 1.5–2、S6 4–6、S7 1、S8 2–3、S9 1.5–2.5、S11 2–3；R1 2、R3 2–3、R4 2–3、R6 0.5–1、R7 1、R9 3–4、R11 0.5 | 约 34–48（其中 S8、R9 与 3j O1/O2 可能部分重叠，去重后约 **24–38**） |
| 1.1 | S7 哈希链 2、R1 演练 2、R7 PITR/跨地域 3、R9 OTel 3、自适应降载（3i 已计） | 约 10 |

## 6. 未覆盖与限制

- 登录锁定、生产 JWT/刷新、验签、安装器、迁移执行器、定时执行器在 sand-core/sand-package，本次没读那些仓，只能标“仓外不可证”。
- v4 两份主文件按关键词定位后摘读上下文，没有逐行通读；“未写”的结论都已用中英文关键词全文检索确认。
- TFB R23 原始数据在本网络环境不可达，相关数字在恢复后要按 `tfb-status.techempower.com/unzip/results.2025-02-05-22-40-37-331.zip/results/20250130184741/results.json` 复核。
- ASVS 5.0.0 已于 2025-05-30 发布（`github.com/OWASP/ASVS/releases`），本报告按任务要求以 4.0.3 L2 为基线，括号里注 5.0 章号；建议 1.0 定稿时改用 5.0 L2。
- 3j 仍在进行中，引用的是 10:31 落盘的现状。
