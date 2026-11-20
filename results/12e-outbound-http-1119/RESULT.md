# 12e：M-10 OutboundHttpClient（队列 1-11）

日期 2026-11-20。交付物：**未合并 PR #117**（supdger/sandadmin）。

- 仓库：`~/agent-work/pub-sandadmin`（supdger/sandadmin）
- 分支：`feat/outbound-http`，基线 SHA（origin/main）：`5360129459241f4e0531fe3483f7c1f19119f7cd`（Merge PR #110；#109 清单 skill 已随 e325e0e 进 main）
- 提交：`b09a74c` feat(http): 统一出站 HTTP 客户端与 SSRF/超时/追踪门禁；`41670cd` fix(http): 代理禁用与请求头 CRLF 拦截返工
- PR：https://github.com/supdger/sandadmin/pull/117 （OPEN，不合并；#81 merge-gates CI 仍 OPEN，只卡合并不卡开工，本任务本就不合并）
- 实现与独立验收档案偏差说明：dv-scout / dv-implementer / dv-verifier 均报 `model is no longer available for your account`（两次探针同错），按"派发档案不可用时报证据、继续协调"处理，调研/实现/独立验收改用跟随主模型的 `subagent_general`（SWE-2）完成；验收为全新上下文代理，未参与实现。

## 需求逐项回答（10g RESULT M-10 行 + #109 清单编号）

M-10 原文：「`OutboundHttpClient`：有超时（连接 3s，3k R6）、校验 IP、禁止重定向、响应体有上限，测试覆盖内网地址和重定向；出网时透传 traceparent 和 request_id」。

| 编号 | 条目 | 本 PR 做了什么 | 剩什么 |
|---|---|---|---|
| T3 | 超时（一切外呼必带超时） | connect/read 停滞/total 三段超时分开（默认 3000/10000/15000ms，`SAND_OUTBOUND_*` 可配）；读超时用 LOW_SPEED 判定停滞 | 自适应超时不做（清单本就"不做"） |
| O3 | 链路追踪（1.0 部分=traceparent 透传） | W3C traceparent/tracestate 入站续接、无上下文生成新 trace；出站恒带 traceparent+tracestate+X-Request-Id；`RequestTraceResolver` 在 Task/CLI 无请求上下文时安全降级 | OTel SDK 接入属 1.1 L-02 |
| T2 | 熔断降级（部分做） | 出网统一走本客户端=外呼 wrap 的统一入口；通用熔断组件按清单"不做" | 下游熔断器属后续（清单原文：依赖少，逐个特判） |
| （3k S8 / M-10 验收点） | SSRF、重定向、响应体上限、重试退避 | `OutboundAddressGate`：仅 http/https，DNS 全结果逐一校验，拒回环/私网/链路本地（含 169.254.169.254）/组播/保留/未指定及 IPv6 等价段（::1、fc00::/7、fe80::/10、::ffff 映射、2001:db8::/32）；白名单 `allow_private_hosts`（精确 host / *.后缀）；`CURLOPT_RESOLVE` 钉住解析地址防 DNS rebinding；`FOLLOWLOCATION=false`；`max_body_bytes` 默认 10MB 截断标记；限次重试+指数退避+抖动，幂等方法默认重试、POST 不重试 | — |
| 门禁 | 禁止直接 curl/Guzzle | `tests/security/scan-outbound-http.php`（token 扫描）挂进 `tools/pre-merge.sh --fast` 第 5/9 步，扫 src/app/config/support；curl_*/GuzzleHttp\\Client/SoapClient/原始 socket/stream_context_create/http(s) 文件函数全拦，唯一豁免 `src/Infra/Http/` 实现目录；`GuzzleHttp\\Psr7`（McpHttpBridge 入站用）不误伤——已实测 | plugin/（sand-core/sand-package 上游包运行目录）不在本仓扫描与改造范围 |

## 现有出站调用改造情况

宿主 tracked 源码唯一出站调用 `src/Module/Package/CurlProbeTransport.php`（更新探测回环请求）已改走 `CurlOutboundClient`，保持旧语义（connect 1s / total 3s / 禁代理 / 仅回环 / 不重试），并补 `proxy=''` 显式禁环境代理。

**边界（如实声明）**：真实出站调用集中在 gitignored `server/plugin/`（sand-core 的 CrontabLogic Guzzle 任意 URL+verify=false、SystemAttachmentLogic file_get_contents、sandpackage 的 GithubRepositoryClient curl_multi/SystemUpdate/DependencyCommandSupport/system-update-worker）。这些源码权威在 supdger/sand-core、supdger/sand-package 仓库，本 PR 无可迁移对象；需上游包后续接入本客户端（可经 `CurlOutboundClient::fromConfig` 或接口注入）。

## 验收映射（全部用 127.0.0.1:19100-19199 假服务 `tests/Support/FakeHttpServer.php`，不访问外网）

| 验收点 | 测试 |
|---|---|
| read timeout | `testReadTimeoutAbortsStalledResponse`（hang 服务） |
| total timeout | `testTotalTimeoutAbortsSlowResponse` |
| connect timeout | 无确定性本地单测（本机无可靠 TCP 黑洞，10.255.255.1 行为漂移）；`CURLOPT_CONNECTTIMEOUT_MS` 与已验证的 TIMEOUT_MS 同 options 同路径，探测路径 1s connect 有覆盖——独立验收判定可接受 |
| SSRF 拦截 | `OutboundAddressGateTest` 19 个 IPv4 + 11 个 IPv6 用例 + scheme/端口/白名单/DNS 钉住矩阵；`CurlOutboundClientTest` 端到端断言 |
| traceparent 透传 | `TraceContextTest`（8 种非法输入拒绝）、`RequestTraceResolverTest`、`CurlOutboundClientTest` 断言假服务收到原 traceparent/tracestate/x-request-id |
| 重试退避 | 注入 sleeper/jitter 替身断言尝试次数与 `[20000,40000]`us 退避序列；POST 默认不重试/可覆盖 |
| 响应体上限 | `testMaxBodyBytesTruncates` |
| 重定向 | `testRedirectNotFollowed`（302 原样返回、仅 1 次请求） |
| 代理 | `testProxyAddressRoutesThroughProxy`、`testEmptyProxyDisablesEnvironmentProxy`、`testLoopbackProbeBypassesEnvironmentProxy` |
| CRLF 注入 | `OutboundRequestTest` DataProvider 4 组 |

## 门禁命令与结果

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | OK（798 tests, 7048 assertions） |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | No errors |
| `tools/pre-merge.sh --fast`（仓根） | 0 | 9/9 全过（含新出站扫描；naming 自查 INFO 注释占比 7.1%） |
| `python3 tools/naming_check.py code/notes`（新增文件） | 0 | 无 FAIL/WARN |
| 门禁实测 | — | 临时探针含 `curl_init`/`GuzzleHttp\\Client`/`SoapClient`/`file_get_contents(http)` 在豁免外被拦（exit 1 点名），放 `src/Infra/Http/` 不误报；`GuzzleHttp\\Psr7` 不误伤；探针已删 |

独立验收（subagent_general 新上下文）：**通过**，12 项逐项 ✅，无严重/一般问题；4 条提示级观察（LOW_SPEED_TIME 整秒粒度、LOW_SPEED 为平均速度近似、NAT64 段未覆盖风险极低、白名单对主机名全解析 IP 放行属设计口径）。

## 违规检查

未建库/启库；未碰前端；未新增豁免/白名单/baseline 条目（`naming-allow.txt`、dangerous-functions 白名单、phpstan ALLOWLIST 均未动；出站扫描的 `src/Infra/Http/` 豁免是新门禁脚本自身的唯一实现位点，属门禁机制而非既有豁免文件新增）；未 git add -A；未读秘密；测试端口全在 19100-19199；单分支规整 commit；未合并。

## 遗留

- plugin 侧真实出站调用迁移 → sand-core / sand-package 上游包后续工单。
- connect_timeout 无确定性单测（见上）。
- 合并前置：#81 OPEN；按规则合并前需 #81 已合并 + merge main + CI phpunit/phpstan 双绿 + Fusion 审计。

## 任务记录

- 已修改：pub-sandadmin `feat/outbound-http`（20+1 文件，+2434/-40 量级）
- 已验证：phpunit unit / phpstan / pre-merge --fast / naming_check / 门禁探针实测，均 exit 0
- 已提交：是（本地 2 commit）；已推送：是；已建 PR：#117（未合并）
- 未完成：合并（等 #81 与 CI/审计流程，本任务不合并）
