# 12d：M-08 三条禁令 + trusted_proxies（队列 1-10）

日期 2026-11-20。交付物：**未合并 PR #115**（supdger/sandadmin），分支 `feat/input-hardening`。

- 基线 SHA（origin/main）：`5360129459241f4e0531fe3483f7c1f19119f7cd`（Merge PR #110）
- 提交：`c6e701f`（trusted_proxies + ClientIpResolver）、`a42b332`（三条禁令门禁）
- PR：https://github.com/supdger/sandadmin/pull/115 （state=OPEN，不合并；#81 仍 OPEN，只卡合并不卡开工）
- 仓库工作副本：`repo-sandadmin-tmp/`（本任务目录内）

## 需求逐项回答

需求来源：`results/10g-fix-plan-review2-1119/RESULT.md` M-08 行（清单外 / ASVS V5；内容=输入输出三条禁令 + `trusted_proxies`，验收=三条禁令进静态门禁、trusted_proxies 有实现和测试）；底层出处 3k 审计 S3 行（"在 v4 补输入输出三条禁令：禁 unserialize 外部数据、禁数组直灌模型、前端禁 v-html 渲染未清洗内容并进静态门禁；trusted_proxies 1.0 实现"）。

| 项 | 做了什么 | 证据 |
|---|---|---|
| 禁 unserialize 外部数据 | `server/tests/security/scan-input-bans.php` token 级扫描宿主 PHP（排除 vendor/runtime/plugin/tests/node_modules），`unserialize()` 内建调用即 FAIL；方法调用/`new`/注释字符串不误伤。当前仓零命中 | `InputBanScanTest` unserialize 正反例；真实扫描 PASS |
| 禁数组直灌模型 | 同一扫描器：持久化方法（save/insert/update/create/fill/forceFill，`->`/`?->`/`::`）参数列表内直接出现 `$request->post()/get()/all()` 即 FAIL；`$body=$request->post()` 赋值后经 `OrgInput` 逐字段校验再入库的合规链路不命中 | `InputBanScanTest` 直灌正反例 + 合规链路用例 |
| 前端禁未清洗 HTML 渲染 | 同一扫描器 diff 模式：`web/src`、`sandadmin-artd/src` 及两个 index.html 的新增行出现 `v-html`/`innerHTML`/`dangerouslySetInnerHTML` 且文件全文无消毒标记（dompurify/sanitize-html/xss/escapeHtml）→ FAIL；存量约 10 处命中不受影响（非新增行） | `InputBanScanTest` 前端用例；批次约束"不碰前端"——本项只加门禁，前端源码零改动 |
| 门禁落点 | `tools/pre-merge.sh --fast` 新增第 5 步 `php tests/security/scan-input-bans.php`，步骤重编号 1/9–9/9 | pre-merge --fast 全过（见下） |
| trusted_proxies | `Sandadmin\Application\Security\ClientIpResolver`（`clientIp()`/`resolve()`）：对端不在 `security.trusted_proxies` CIDR 名单 → 只认 TCP 对端，伪造转发头无效；对端可信 → `Forwarded`（RFC 7239 `for=`）优先、XFF 兜底，从右往左剥可信段取第一个不可信地址，全可信取最左端；名单为空（含生产未配置）→ 转发头一律忽略。CIDR 匹配用 `Symfony\HttpFoundation\IpUtils`（既有依赖） | `ClientIpResolverTest` 8 用例；40+ 处 `getRemoteIp()` 客户端 IP 取值改走 resolver |
| 配置 | `config/security.php` 新增 `trusted_proxies`（env `SAND_TRUSTED_PROXIES` 逗号分隔 IP/CIDR，IPv4/IPv6）；`.env.example` 补注释段 | 见 diff |
| 保留 getRemoteIp 的例外 | `DevLoginGate`、`McpGate` 判定的是 TCP 对端直连回环语义（且自带转发头拒绝），保留并注释理由 | diff 注释 |

## #109 清单编号逐项回答

清单 skill 已在 main（PR #109 merged，`.agents/skills/sandadmin1x-design-standard/SKILL.md`）。与本任务相关编号：

- **X1 鉴权**：trusted_proxies 是身份可信链的输入侧——审计/登录日志 IP 现在只经 `ClientIpResolver` 取，不可信来源伪造 XFF 拿不到伪造 IP（对应验收条款）。已做。
- **T1 限流**：IP 维限流的取值正确性依赖本项；限流本体不在本任务范围，IP 解析已收口为唯一入口，后续限流直接复用。
- **Q3 契约**：本任务未新增对外错误码/接口，`contract/openapi.yaml` 未动，contract-build --check 通过；若后续加错误码须走契约链。
- **E3 国际化**：无面向用户新文案（门禁 CLI 输出与内部异常消息不算用户文案）；语言包机制未触发。
- 三条禁令本身对应 ASVS V5（10g M-08 行标注"清单外"），清单 54 条无独立条目。

## 验收硬指标复核

| 验收条款 | 结果 | 证据 |
|---|---|---|
| 每条禁令有一个反例测试被拦下 | ✅ | `InputBanScanTest` 12 用例：`unserialize($data)`、`$model->save($request->post())`、`Repo::create($request->all())` fixture 均断言 FAIL；前端无消毒 `v-html` 新增行断言 FAIL。`vendor/bin/phpunit --filter 'ClientIpResolverTest\|InputBanScanTest'` → OK (20 tests, 28 assertions) |
| 用不可信来源伪造 XFF 拿不到伪造的 IP | ✅ | `ClientIpResolverTest`：对端不在名单 + `X-Forwarded-For: 6.6.6.6` → 返回对端 IP；空名单 + 转发头 → 返回对端；另有 Forwarded 优先、CIDR v4/v6、全可信取最左端等用例 |

## 门禁命令与结果（仓根 / server/）

| 命令 | 退出码 | 关键输出 |
|---|---|---|
| `cd server && composer install` | 0 | vendor 齐全；sand-publish 覆盖 `server/webman`、`server/windows.php` 两处跟踪文件已 `git checkout` 还原，未入库 |
| `vendor/bin/phpunit --testsuite unit` | 0 | OK（757 tests, 6905 assertions） |
| `vendor/bin/phpstan analyse --no-progress` | 0 | No errors（level max） |
| `tools/pre-merge.sh --fast` | 0 | 9/9 全过（含新增第 5 步禁令扫描、契约一致性、dev-only、TypePHP diff、命名自查） |
| `python3 tools/naming_check.py code/notes/terms --diff` | 0/0/0 | code 有 WARN×1：`MeController.php:70 $newPassword` 为既有契约字段（changePassword 签名），本次仅同行替换调用，非新增命名，保留 |
| `php tests/security/scan-input-bans.php`（server/ 下真实跑） | 0 | `PASS 输入输出三条禁令扫描通过`，零命中 |

Integration/Contract 套件未跑（需 `SAND_TEST_DB_URL` 连库，按约束不建库不启库）；Contract 套件不受影响（无契约改动）。

## 派发与模型记录

- `dv-scout` ×2 派发均失败：`Protocol error (not_found): This model is no longer available for your account`（swe-2-high 档案模型不可用，疑似额度/可用性问题）。
- 改用 `subagent_general`（跟随主代理 SWE-2 Flash Medium，非 Claude）完成：调研（只读）、trusted_proxies 实现、禁令门禁实现三个工作包。本机 dv-* 档案全部为 SWE-2 系，未派任何 Claude 模型。
- 主代理复核了全部 diff（ClientIpResolver 语义、扫描器规则、pre-merge 编号、getRemoteIp 替换点与保留点），未发现越权改动。

## 遗留与边界

- mass-assignment 禁令是启发式：只判"同一调用表达式内"持久化参数直接嵌套 `$request->post/get/all()`；`call_user_func`、字符串回调、`$$fn()` 等间接调用不覆盖；接收端只认名为 `request` 的变量/属性（`$req->post()` 不命中）。存量合规（OrgInput 逐字段校验链路）。
- 前端禁令为 diff 模式：只卡新增行；文件级消毒标记（同文件任一处出现 dompurify 等即放行该文件全部新增行），不解析模板语法。
- trusted_proxies 只认 `Forwarded` 与 `X-Forwarded-For` 两级（按 security-auth.md 契约字面），不做 X-Real-IP/Client-IP/Via 回落。
- `server/plugin/`（sand-core/sand-package 运行时安装产物）不在本仓，门禁与 resolver 替换覆盖宿主自有代码；插件面另行协调。
- 合并前置：#81（merge-gates CI）仍 OPEN；按规则先确认 #81 合并 → merge main → CI phpunit/phpstan 绿 → Fusion 审计后才合。
