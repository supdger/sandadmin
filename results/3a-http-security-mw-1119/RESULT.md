# RESULT — 1.x HTTP 安全中间件（N3 响应头 + v1 Origin/CSRF + CORS 白名单）

日期：2026-11-19　仓库：supdger/sandadmin　分支：`feat/http-security-mw`
基线 SHA（origin/main）：`977427acc285da074a443e8206309289a9bd94fb`
最终 SHA：`977c396721e4ad475d85f111cbbe0b45b59ae89a`（含返修轮 `77ed4e4`、微修轮 `977c396`，见对应小节）
PR：https://github.com/supdger/sandadmin/pull/107 （目标 main，未合并）

## 变更清单（14 个文件）

新增：
- `server/src/Http/Middleware/SecurityHeadersMiddleware.php`（N3 七头）
- `server/src/Http/Middleware/CsrfMiddleware.php`（A-5 + 缺口 14）
- `server/src/Http/Middleware/CorsMiddleware.php`（v1 跨域白名单）
- `server/src/Application/Security/{SecurityHeadersConfig,CsrfConfig,CorsConfig}.php`（配置类型化视图，`fromArray`/`fromConfig`，沿用 `TaskConfig`/`StorageConfig` 先例）
- `server/tests/Unit/Http/Middleware/` 3 个测试类（28 用例，仿 `ErrorMiddlewareTest` 手工构造 `Webman\Http\Request`）
- `tools/assert-security-headers.sh`（curl 断言，默认 19107，限 19100–19199，trap 停服）

修改：
- `server/config/middleware.php`：`''` 全局键 `SecurityHeaders → Cors → Csrf`（webman 语义：列表先列者最外层，已实测验证）
- `server/config/security.php`：新增 `site_origin` / `headers` / `csrf` / `cors` 四组键
- `server/config/route.php`：fallback 挂路由级 `[SecurityHeaders, Cors]`
- `server/.env.example`：新增 `SAND_SITE_ORIGIN`/`SAND_CSRF_*`/`SAND_CORS_*`/headers 相关变量占位与说明（仅变量名，无值）

## 响应头取值与配置键

| 响应头 | 取值 | 配置键 / env | 备注 |
| --- | --- | --- | --- |
| X-Content-Type-Options | `nosniff` | 固定 | |
| X-Frame-Options | `DENY` | 固定 | |
| Referrer-Policy | `strict-origin-when-cross-origin`（默认） | `security.headers.referrer_policy` / `SAND_REFERRER_POLICY` | **单值待总监确认** |
| Strict-Transport-Security | `max-age=15552000`（默认） | `security.headers.hsts_max_age` / `SAND_HSTS_MAX_AGE` | **仅 HTTPS 输出**；判定只看 `$request->connection->transport === 'ssl'`，不信 `x-forwarded-proto` 等转发头（可信代理 trusted_proxies 属 N2 工作包，未实现） |
| Content-Security-Policy(-Report-Only) | JSON 响应 `default-src 'none'`；其他 `default-src 'self'` | `security.headers.csp.{api,page}` / `SAND_CSP_{API,PAGE}`；`security.headers.csp.enforce` / `SAND_CSP_ENFORCE`（默认 false） | enforce=false → `Content-Security-Policy-Report-Only`（NET-01 灰度）；true → 强制头 |
| Cross-Origin-Opener-Policy | `same-origin` | 固定 | |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | `security.headers.permissions_policy` / `SAND_PERMISSIONS_POLICY` | |

**覆盖语义**：同名头一律覆盖为配置值（下游差异化取值应由配置项表达）。403/204 等中间件短路响应同样带头（SH 最外层挂载的意义，已实测）。

## Origin 校验三态（CsrfMiddleware，A-5）

仅对 `path` 以 `/api/admin/v1` 开头且非 GET/HEAD/OPTIONS 的请求；公开端点不豁免。

1. `Origin` 存在 → 规范化（trim/去尾斜杠/小写）后与 `security.site_origin` 严格相等才放行；`Origin: null` 字面量必不等 → 403。
2. `Origin` 缺失 → 解析 `Referer` 的 scheme+host[:port] 重建源，同规则比对；解析不出 → 403。
3. 两者皆无 → 403。

**追加自定义头规则**（缺口检查 14，A-5 未定义头名）：写操作必须携带 `security.csrf.required_header_name`（默认 `X-Requested-With`），缺/空 → 403；`security.csrf.required_header_value` 非空时还要求值相等（默认空=只要求存在）。

**site_origin 未配置**：跳过 Origin/Referer 校验但仍要求标记头（开发期 curl 可跑通；生产必填策略待总监确认）。

**拒绝响应**：HTTP 403 + 契约信封 `{code:"CSRF_REJECTED", message, fields:{}, request_id}`（复用 `ErrorBody`+`JsonErrorResponse`+`RequestIds`+`RequestContextStore`，request_id 沿用/生成规则与 ErrorMiddleware 一致）。

**⚠️ `CSRF_REJECTED` 未登记进 `contract/openapi.yaml` `x-error-catalog`**（已核实全仓无命中）。按任务约束未改 openapi.yaml；规格指定该码故照用，需契约负责人补登。

## CORS 白名单语义（CorsMiddleware）

- 作用域 `/api/admin/v1`。请求无 Origin → 不加任何 CORS 头、直通。
- `security.cors.allowed_origins`（env `SAND_CORS_ALLOWED_ORIGINS` 逗号分隔，默认 `[]`=仅同源）：规范化后**精确相等**判定，不反射、不子串、不通配。
- 白名单内普通请求：响应加 `Access-Control-Allow-Origin: <请求 Origin 原值>` + `Vary: Origin`。
- 白名单内 OPTIONS 预检：**204 独立应答不进下游** + ACAO + `Allow-Methods`（配置 `allowed_methods`，默认 GET/POST/PUT/PATCH/DELETE）+ `Allow-Headers`（配置 `allowed_headers` 并自动并入 `csrf.required_header_name`，大小写去重）+ `Access-Control-Max-Age`（默认 600）。
- 白名单外 OPTIONS 预检：**204 不带 ACAO**（择一实现，理由：与"预检独立应答"口径一致，浏览器因缺 ACAO 拦截实际请求；403 会把预检当错误处理）。
- `Vary: Origin` 在所有带 Origin 的 v1 响应上输出（含非白名单），防共享缓存串发。
- **永不输出 `Access-Control-Allow-Credentials`**（A-5：不任意带凭据）。
- 与 CSRF 的语义边界：白名单只放行跨源读；写操作仍须满足 `Origin===site_origin`（白名单≠CSRF 放行）。

## 挂载与作用域实现方式（关键取舍）

1. `config/middleware.php` `''` 键（宿主全局）：`SecurityHeaders → Cors → Csrf`。v4 口径"v1 组及后台页面统一输出"，全局挂载是其超集（dev-login/MCP 响应也带头，无害）。
2. main 上 v1 路由由 `contract-build` 生成表**平铺注册、无 group/`->middleware()`**，CSRF/CORS 只能全局挂载后在中间件内按 `/api/admin/v1` 前缀自限作用域——**待架构复审**。
3. **fallback 不经全局中间件**（webman `Route::getFallback` 固定 `withGlobalMiddleware=false`，`App::getCallback` 只在路由命中时合并全局链）：不处理则 SPA 页面/404 无安全头、且 v1 OPTIONS 预检在 FastRoute 层因方法未注册即落 fallback、CorsMiddleware 永不可达。已把 `[SecurityHeadersMiddleware, CorsMiddleware]` 经 `Route::fallback(...)->middleware()` 挂为路由级中间件；Csrf 不挂 fallback（未匹配路径本就该 404，无写操作可护）。实测：OPTIONS 白名单 → 204+ACAO+三 Allow 头，404 响应带头。
4. 静态文件响应（`findFile`/`__static__`）不经中间件，无安全头——记录为边界。
5. A-5 链 CSRF→Error→Auth 中 Error/Auth 中间件在 main 上均未注册，不属本任务；CsrfMiddleware 的 request_id 处理与 ErrorMiddleware 同构，未来链式共存不冲突。
6. 中间件实例化：webman `Webman\Container::get` 是 `new $class()` 无自动装配——构造器仅一个可空 `?XxxConfig` 参数（缺省 `fromConfig()` 读全局），辅助对象（`JsonErrorResponse`/`RequestIds`/`RequestContextStore`/`RequestHeaders` 皆无状态）构造内自建；测试直接注入配置对象。
7. TypePHP 可变调用扫描会 FAIL `$handler(...)`，仓内惯例是括号包裹 `($handler)($request)`（`MemoryStreamChannel` 等先例），已套用——既有 Auth/Error 中间件是门禁前存量。

## 门禁结果（逐条）

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `python3 tools/naming_check.py code <10 个新/改 php+sh>` | 0 | 0 FAIL / 0 WARN |
| `python3 tools/naming_check.py notes <同上>` | 0 | INFO 注释占比 10.1%，无 FAIL/WARN |
| `python3 ~/agent-work/grokbot/devin-queue/naming_check.py code <新 php>` | 0 | 无输出（通过） |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (436 tests, 2679 assertions)`；新增 28 用例 `--filter` 复跑 `OK (28 tests, 66 assertions)` |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `level max, [OK] No errors` |
| `bash tools/pre-merge.sh --fast` | 0 | 7/7：php -l、contract-build --check `PASS 产物与契约源一致`、危险函数 `PASS`、dev-only `PASS 1422 个文件`、typephp diff `FAIL=0 WARN=0`、phpunit、phpstan |
| `bash tools/pre-merge.sh --fast --typephp` | 0 | `未找到 TypePHP 编译器，跳过可选编译`（按设计跳过） |
| `bash tools/assert-security-headers.sh 19107` | 0 | 14/14 PASS（v1 API 与 `/` 各 6 头 + HSTS 缺席 + CSP Report-Only 两档值） |
| 端到端实测（19108，SAND_CORS_ALLOWED_ORIGINS/SAND_SITE_ORIGIN 注入） | — | 白名单 OPTIONS → `204 + ACAO + Allow-Methods + Allow-Headers + Max-Age + Vary`；POST 无 Origin/标记头 → `403` + JSON 信封 + 安全头（CSP=`default-src 'none'`）；POST Origin+标记头齐 → 穿透到控制器（无库 500，证明中间件放行）；POST 未知 v1 路径 → `404` + 安全头 |

## 测试覆盖（28 用例）

- SecurityHeaders：HTTP 下 6 头齐出无 HSTS、JSON→csp.api、enforce→强制头名、HTTPS（reflection 造 `TcpConnection`+`transport=ssl`）→HSTS、自定义取值、短路响应带头、覆盖下游冲突头。
- Csrf：GET 放行；Origin 匹配+标记头放行；`Origin:null`→403；公开端点不豁免；Origin 缺失回落匹配 Referer→放行；跨站 Referer→403；皆无→403；Origin 存在即以 Origin 为准（不回落）→403；缺标记头→403；`required_header_value` 配置时错值→403/对值→放行；非 v1 直通；site_origin 未配置→跳过源校验但标记头仍必查；403 信封 `code=CSRF_REJECTED` + `request_id` 回显。
- Cors：无 Origin 无 ACAO；白名单内 GET→ACAO+Vary 无 Credentials；白名单外→Vary 无 ACAO；预检白名单→204+三 Allow 头不进下游、Allow-Headers 含 X-Requested-With/自定义标记头名；预检白名单外→204 无 ACAO；非 v1 直通；空白名单=仅同源。

## 待总监/契约确认项

1. **CSRF_REJECTED 需登记 `x-error-catalog`**（置顶，按任务约束未改 openapi.yaml）。
2. Referrer-Policy 单值：v4 标「建议 `no-referrer` 或 `strict-origin-when-cross-origin`，单值待确认」——当前取后者。
3. 自定义头名 `X-Requested-With` 与取值语义（A-5 未定义头名；缺口检查 14 只要求"自定义请求头"）。
4. CSP 完整指令集（当前按 v4 原文起步值，插件前端页面后续扩展指令）。
5. `security.site_origin` 未配置策略（当前：跳过源校验、仍要求标记头）。
6. v1 挂载机制（全局挂载+前缀自限）待架构复审。
7. 头数口径以 N3 正文 7 项为准（缺口检查 S1 曾写 6 项）。
8. 源比较口径：scheme+host[:port] 规范化后严格相等；不折叠默认端口（`https://h` ≠ `https://h:443`），部署配置与浏览器 Origin 序列化一致即匹配。

## 剩余项 / 阻塞

- 无阻塞。剩余项均属后续工作包：N2 trusted_proxies（HTTPS 判定当前只看连接 transport，反代场景待 N2）；CSRF_REJECTED 契约登记；Error/Auth 中间件注册（另一工作包）；插件路由面（`/core` 旧插件链）不在本范围。
- 边界记录：静态文件响应无安全头；插件 `''`/`@` 中间件键未动。

---

# 返修轮（PR#107 审计「有条件通过」→ 修复）

- 返修提交：`77ed4e4783bfb33e66bcbcab7ac505f126dbfda0`（`d4e8bb0..77ed4e4`，分支 `feat/http-security-mw`）
- PR 状态核实：`gh pr view 107` → `state=OPEN`，`headRefOid=77ed4e4783bfb33e66bcbcab7ac505f126dbfda0`（已反映新提交）
- 变更面：15 文件 +388/-100；新增 14 个单测用例（28→42 个中间件用例，全套 436→450）
- ⚠️ 上文「待确认项 8：不折叠默认端口」口径已被本轮取代——审计要求统一规范化，现折叠 `http:80`/`https:443`，规范化后 `https://h` ≡ `https://h:443`。

## 审计项逐条处置（1–11）

| # | 审计发现 | 处置 | 证据 |
| --- | --- | --- | --- |
| 1 | 静态文件响应无安全头（`config/static.php` 中间件为空） | **已修**：`static.php` 挂 `SecurityHeadersMiddleware`；断言脚本补 `/favicon.ico` 目标 | curl 实测 `/favicon.ico` 7 项断言全 PASS（见下） |
| 2 | `str_starts_with($path,'/api/admin/v1')` 误匹配 `/api/admin/v1x` | **已修**：两中间件改为 `path===V1_ROOT \|\| str_starts_with(path,'/api/admin/v1/')` | `testV1PrefixDoesNotOvermatch`（Csrf/Cors 各一）：v1x→204 直通、v1 根→403 |
| 3 | ACAO 回显请求 Origin 原值 | **已修**：`CorsConfig::match()` 归一化请求 Origin 后返回白名单规范条目 | `testAcaoEmitsCanonicalWhitelistEntry`：大写/尾斜杠/`:443` 三种请求形均回写 `https://admin.example.com` |
| 4 | 默认 `allowed_headers` 缺 `Idempotency-Key`、`X-Request-Id` | **已修**：`security.php` 与 `CorsConfig` 默认头补齐 5 项；并入标记头的大小写去重保留 | `testAllowHeadersDefaultsCoverContractHeaders` |
| 5 | `Vary: Origin` 覆盖下游既有 Vary | **已修**：`varyOn()` 按逗号段合并，已含 origin（不区分大小写）则不动 | `testVaryMergesInsteadOfOverwriting`：`Accept-Encoding`→`Accept-Encoding, Origin`；`accept-encoding, origin`→原样 |
| 6 | `env(...) ?: default` 吞掉显式 `0`（HSTS/CORS max_age） | **已修**（微修轮改为终版）：config 透传 env 原值，类型化 `readInt` 用 `FILTER_VALIDATE_INT` 解析——空串/非整数/缺失落默认，`'0'` 保留（初版 `env(name, default)` 空串仍被 `(int)` 吞 0 的问题由本轮一并解决） | `security.php` 两行透传 + `readInt` 字符串解析；`SecurityConfigFileTest` 8 例 |
| 7 | 大小写不同键致同名头双份；CSP 只认 `application/json` | **已修**：`setHeader()` 写前按 `strcasecmp` 清旧键再落新值；`resolveCsp` 改 `str_contains(ct,'json')` | `testSameNameHeadersDeduplicatedCaseInsensitively`（小写旧键→1 份）；`testJsonLikeContentTypesGetApiCsp`（`vnd.api+json`、`text/json`→API 档） |
| 8 | 断言脚本：字面 `\t`、STARTED 时机、trap 误停他主、0.0.0.0 | **已修**：`printf` 输出；启动命令成功才计 `STARTED`（微修轮前移到启动成功后，覆盖就绪超时收尸）；停服前核对 pid 文件进程确为 php；`SANDADMIN_SERVER_LISTEN` env 覆盖 listen（`process.php`），脚本绑 `127.0.0.1`；端口被占直接拒跑 | 脚本全文；实测 19120 端口被**他任务** `php -S` 占用时脚本不误碰，本轮用 19107 通过 |
| 9 | route.php 新增注释超 3 行 | **已修**：压为 2 行独立注释块（与既有 SPA 注释空行分隔） | `naming_check notes` 该块不再 WARN；残留 `route.php:54` 5 行 WARN 为 main 存量块（`git diff origin/main` 证实未改） |
| 10 | `required_header_value` 未 trim | **已修**：配置载入即 `trim`（非空才作期望值） | `testMarkerValueConfigIsTrimmed`：配置 `'  fetch\t'`→期望 `fetch`，请求 `fetch`→放行 |
| 11 | site_origin/CORS 源无形态校验、未归一化 | **已修**：新增 `CsrfConfig::parseStrictOrigin`（拒 userinfo/路径/query/fragment），`site_origin`、`allowed_origins`、请求 `Origin` 三方共用；非法配置在配置装载时抛 `InvalidArgumentException`（中间件惰性实例化，实际表现为受影响路由请求 500 + 日志诊断，非起服即崩）；`parseOrigin` 统一小写/去尾斜杠/折叠默认端口 | `testInvalidSiteOriginConfigIsDiagnosable`、`testUserinfoSiteOriginConfigThrows`、`testSiteOriginFoldsDefaultPort`、`testUserinfoOriginRejected`、`testNormalizedOriginFormsStillMatch` |

## 明确不修项（审计已列，口径不变）

| 项 | 结论 |
| --- | --- |
| B1 `web/` 前端缺 `X-Requested-With` | 不修：前端不在本任务授权面 |
| S2 `site_origin` 缺省 fail-open/fail-close | 不裁决：本轮只加非法配置诊断；缺省语义维持「跳源校验、标记头必查」待架构定 |
| S3 `CSRF_REJECTED` 未登记 openapi | 不改 `contract/openapi.yaml`（任务硬约束），待契约负责人补登 |
| S4 插件命名空间 v1 路由链 | 归 R45，不在本包 |
| CSP report-uri 端点 | 配置项保留，端点后续工作包 |
| H4 Referer `\@` 解析 | 按审计指示不处理 |

## 返修轮门禁（全部复跑）

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (450 tests, 2709 assertions)` |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max） |
| `bash tools/pre-merge.sh --fast`（仓库根） | 0 | 7/7 全过：php -l、contract-build --check、危险函数、dev-only、typephp diff `FAIL=0`、phpunit、phpstan |
| `python3 tools/naming_check.py code <15 个改动文件>` | 0 | 无输出（0 FAIL 0 WARN） |
| `python3 tools/naming_check.py notes <同批>` | 0 | 0 FAIL；WARN 2 条均为存量/性质项——`route.php:54` 5 行注释块（main 存量，未改）、config 注释占比 32.3%（配置文件性质） |
| `bash tools/assert-security-headers.sh 19107` | 0 | **21/21 PASS**：v1 API、`/`、`/favicon.ico` 各 7 项（6 头 + HSTS 缺席 + CSP Report-Only 对应档） |
| `php -l` × 改动 php + `bash -n` 脚本 | 0 | 全部 `No syntax errors` |

## 返修轮遗留/风险

- `route.php:54` 与「注释占比」两条 WARN 属 main 存量与配置文件性质，非本轮引入，留档不处理。
- `SecurityHeadersConfig::resolveCsp` 现为「Content-Type 含 json 即 API 档」：text/html 等不含 json 的页面走 page 档，无回归面；极端自定义 ct 含 `json` 子串也按 API 档（更严方向，可接受）。
- 19120 端口存在**他任务** `php -S` 常驻监听（`pkgops-probe-1118`），本任务未触碰；断言脚本自带端口占用预检。

---

# 微修轮（Fable 复验「通过」后的 7 条提示级发现）

- 微修提交：`977c396721e4ad475d85f111cbbe0b45b59ae89a`（`77ed4e4..977c396`，分支 `feat/http-security-mw`）
- PR 状态核实：`gh pr view 107` → `state=OPEN`，`headRefOid=977c396721e4ad475d85f111cbbe0b45b59ae89a`（已反映新提交）
- 变更面：9 文件 +217/−17；新增 `SecurityConfigFileTest`（env 解析 dataProvider 8 例）+ 白名单校验用例（450→463）

## 提示项逐条处置（N1–N7）

| # | 审计发现 | 处置 | 证据 |
| --- | --- | --- | --- |
| N1 | 断言脚本起服带起 `0.0.0.0:2206`（channel 进程段外端口） | **已修**：channel 端口走 `SANDADMIN_CHANNEL_PORT` env（`config/plugin/webman/channel/process.php` 确认读取），脚本导出 `PORT+1` 并对两个端口做占用预检；就绪后新增「实例全监听口在 19100–19199」断言（master pid 文件 → pgrep 子孙 → lsof 过滤本实例监听） | 实测输出 `PASS listen 实例全部监听口均在 19100–19199（HTTP 19107 + channel 19108）` |
| N2 | `SAND_HSTS_MAX_AGE`/`SAND_CORS_MAX_AGE` 空串/非数字被 `(int)` 吞成 0 | **已修**：config 文件透传 env 原值；`SecurityHeadersConfig::readInt` 与 `CorsConfig::readInt` 扩展为整数字符串（`FILTER_VALIDATE_INT`）→ int，空串/非整数/缺失落默认，`'0'` 保留；初版方案是 config 文件内 `$readEnvInt` 闭包，被 TypePHP 门禁打回（可变调用），遂把解析收敛进类型化配置类 | `SecurityConfigFileTest` dataProvider 全链路 8 例（含 `'0'`→0、`''`→默认、`'abc'`→默认） |
| N3 | `SANDADMIN_SERVER_LISTEN=` 留空不监听 | **已修**：`process.php` 改 `env(...) ?:` 写法，空串按未配置回落默认拼装；`SANDADMIN_SERVER_PORT` 同法兜底 | 代码 `process.php:28`；单测未覆盖（include process.php 会触发 `Log::channel('default')` 读取日志配置，非本测试可测面） |
| N5 | CORS 白名单静默接受 `*`/`ftp://`/`https://*.x.com` | **已修**：`parseStrictOrigin` 增 scheme 白名单（仅 http/https）与 `*` 拒绝；`site_origin`、`allowed_origins`、请求 Origin 同口径 | `testWildcardWhitelistEntryThrows`、`testBareWildcardWhitelistEntryThrows`、`testNonHttpWhitelistEntryThrows`（CorsConfig）；`testNonHttpSiteOriginThrows`、`testWildcardSiteOriginThrows`（CsrfConfig） |
| N7 | 就绪超时分支留下本脚本进程 | **已修**：`STARTED=1` 前移到 `start.php` 启动命令成功之后（启动成功即证明进程属本脚本，就绪超时 exit 亦经 trap 收尸）；启动失败直接退出不清场 | 脚本 `if ! ... start -d; then exit` + `STARTED=1`；实测正常路径清理无残留（lsof 段内无监听） |
| N4 | 上文「非法 site_origin 起服即抛错」表述不准 | **已修**：改为事实表述——服务可启动，受影响路由请求 500 + 日志诊断（中间件惰性实例化，异常在配置装载时抛出） | 返修轮审计表第 11 行措辞已更正；本提交 commit message 如实描述 |
| N6 | 下游小写 `content-type` 时 CSP 判档读不到 | **不修**（按派发指示）：`Response::getHeader('Content-Type')` 按字面键查，下游若写小写键会读空走 page 档；现状无触发面（JSON 响应统一经 `JsonErrorResponse`/控制器写规范键），记为已知边界 | 本条即留档 |

## 微修轮门禁（全部复跑）

| 命令 | 退出码 | 关键输出 |
| --- | --- | --- |
| `cd server && vendor/bin/phpunit --testsuite unit` | 0 | `OK (463 tests, 2722 assertions)`（459→463 净增，含 env 解析 dataProvider 8 例与白名单校验例） |
| `cd server && vendor/bin/phpstan analyse --no-progress` | 0 | `[OK] No errors`（level max） |
| `bash tools/pre-merge.sh --fast`（仓库根） | 0 | 7/7 全过；TypePHP diff `FAIL=0`（闭包方案已改为配置类内 `readInt` 字符串解析） |
| `python3 tools/naming_check.py code <9 个改动文件>` | 0 | 0 FAIL 0 WARN |
| `python3 tools/naming_check.py notes <同批>` | 0 | 0 FAIL 0 WARN（INFO 注释占比 6.8%） |
| `bash tools/assert-security-headers.sh 19107` | 0 | **22/22 PASS**：段内监听断言 + v1 API、`/`、`/favicon.ico` 各 7 项 |
| `bash -n` 脚本 + `php -l` 改动 php | 0 | 全部 `No syntax errors` |

## 微修轮遗留/边界

- channel 进程绑定地址仍为 `0.0.0.0`（webman channel 插件配置，端口已在段内；审计只约束端口区间）。
- N6 边界：下游写非规范大小写 `content-type` 时 CSP 会走 page 档，现状无触发面。
- `process.php` listen 回落逻辑无单测（include 会触发 `Log::channel` 配置读取），改动仅一行 `?:`，风险面极低。
